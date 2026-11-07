<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\Delegation;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\HotelUser;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Áreas (setores) com chefe e substituições temporárias de gestores. */
class AreaController extends WorkspaceBaseController
{
    private function today(): string
    {
        return CarbonImmutable::now('America/Sao_Paulo')->toDateString();
    }

    private function delegationPayload(Delegation $d): array
    {
        return [
            'uuid'      => $d->uuid,
            'from'      => $this->access->person($d->from),
            'to'        => $this->access->person($d->to),
            'starts_on' => $d->starts_on->toDateString(),
            'ends_on'   => $d->ends_on->toDateString(),
            'active'    => $d->starts_on->toDateString() <= $this->today() && $d->ends_on->toDateString() >= $this->today(),
        ];
    }

    /** GET /workspaces/{slug}/areas — áreas com chefe, pessoas e substituições vigentes/futuras (dono e RH). */
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $this->access->isAdmin($this->membership($request, $hotel))) {
            return $this->forbidden('Somente o dono ou o RH gerenciam áreas e substituições.');
        }

        $members = HotelUser::with('user')->where('hotel_id', $hotel->id)->where('status', 'approved')->get();
        $counts  = $members->whereNotNull('department_id')->groupBy('department_id')->map->count();

        $areas = Department::with('head')->where('hotel_id', $hotel->id)->orderBy('name')->get()->map(fn (Department $d) => [
            'uuid'          => $d->uuid,
            'name'          => $d->name,
            'members_count' => (int) ($counts[$d->id] ?? 0),
            'head'          => $this->access->person($d->head),
        ])->values();

        $delegations = Delegation::with(['from', 'to'])->where('hotel_id', $hotel->id)
            ->whereDate('ends_on', '>=', $this->today())->orderBy('starts_on')->get()
            ->map(fn ($d) => $this->delegationPayload($d))->values();

        return $this->success('Áreas carregadas com sucesso.', [
            'areas'       => $areas,
            'delegations' => $delegations,
            'people'      => $members->map(fn (HotelUser $hu) => $this->access->person($hu->user, $hu))->sortBy('name')->values(),
        ]);
    }

    /** PATCH /workspaces/{slug}/areas/{uuid}  { head_uuid: uuid|null } */
    public function setHead(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if (! $this->access->isAdmin($this->membership($request, $hotel))) {
            return $this->forbidden('Somente o dono ou o RH definem o chefe da área.');
        }
        $data = $request->validate(['head_uuid' => ['nullable', 'uuid']]);

        $area = Department::where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();
        if (! $area) {
            return $this->notFound('Área não encontrada.');
        }

        $head = null;
        if (! empty($data['head_uuid'])) {
            $head = $this->access->memberByUuid($hotel, $data['head_uuid']);
            if (! $head) {
                return $this->notFound('Pessoa não encontrada na empresa.');
            }
        }

        $area->update(['head_id' => $head?->user_id]);
        AuditLog::record($request, $hotel, 'area.head_changed', $head?->user, ['area' => $area->name]);

        if ($head) {
            $this->notifications->workspaceNotice(
                $head->user, $request->user(), 'workspace_area_head',
                "Você agora chefia a área {$area->name}", 'Você enxerga e aprova as solicitações, o ponto e as metas das pessoas da área.',
                $this->link($hotel, 'equipe'),
            );
        }

        return $this->success('Chefe da área atualizado.', ['uuid' => $area->uuid, 'head' => $this->access->person($head?->user)]);
    }

    /** POST /workspaces/{slug}/delegations  { from_user_uuid?, to_user_uuid, starts_on, ends_on } */
    public function delegate(Request $request, Hotel $hotel): JsonResponse
    {
        $me = $this->membership($request, $hotel);
        if (! $me) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $data = $request->validate([
            'from_user_uuid' => ['nullable', 'uuid'],
            'to_user_uuid'   => ['required', 'uuid'],
            'starts_on'      => ['required', 'date_format:Y-m-d', 'after_or_equal:' . $this->today()],
            'ends_on'        => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);

        $isAdmin = $this->access->isAdmin($me);
        $from    = $request->user();
        if (! empty($data['from_user_uuid']) && $data['from_user_uuid'] !== $from->uuid) {
            if (! $isAdmin) {
                return $this->forbidden('Só o dono ou o RH registram substituição por outra pessoa.');
            }
            $fromMember = $this->access->memberByUuid($hotel, $data['from_user_uuid']);
            if (! $fromMember) {
                return $this->notFound('Pessoa não encontrada na empresa.');
            }
            $from = $fromMember->user;
        }
        if (! $this->access->isManager($from, $hotel)) {
            return $this->error('Só quem tem equipe ou área pode ter substituto.', [], 422);
        }

        $to = $this->access->memberByUuid($hotel, $data['to_user_uuid']);
        if (! $to || $to->user_id === $from->id) {
            return $this->error('Escolha outra pessoa da empresa como substituto.', ['to_user_uuid' => ['Substituto inválido.']], 422);
        }

        $d = Delegation::create([
            'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'from_user_id' => $from->id,
            'to_user_id' => $to->user_id, 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'],
            'created_by' => $request->user()->id,
        ]);
        AuditLog::record($request, $hotel, 'delegation.created', $to->user, ['from' => $from->name, 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on']]);

        $this->notifications->workspaceNotice(
            $to->user, $request->user(), 'workspace_delegation',
            "Você vai substituir {$from->name}", "De {$data['starts_on']} a {$data['ends_on']} você aprova e acompanha a equipe dele(a).",
            $this->link($hotel, 'solicitacoes?aba=aprovar'),
        );

        return $this->created('Substituição registrada.', $this->delegationPayload($d->load(['from', 'to'])));
    }

    /** DELETE /workspaces/{slug}/delegations/{uuid} — dono/RH ou o próprio gestor. */
    public function undelegate(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $me = $this->membership($request, $hotel);
        $d  = $me ? Delegation::where('hotel_id', $hotel->id)->where('uuid', $uuid)->first() : null;
        if (! $d || ($d->from_user_id !== $request->user()->id && ! $this->access->isAdmin($me))) {
            return $this->notFound('Substituição não encontrada.');
        }

        AuditLog::record($request, $hotel, 'delegation.removed', $d->to, ['from' => $d->from?->name]);
        $d->delete();

        return $this->success('Substituição removida.');
    }
}
