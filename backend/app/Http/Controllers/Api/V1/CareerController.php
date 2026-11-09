<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\CareerEntry;
use App\Models\Hotel;
use App\Models\HotelUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Histórico de carreira na empresa. Qualquer colega lê a trajetória (cargo e período); só o RH/dono edita.
 * Contratações por vaga interna alimentam o histórico sozinhas (ver WorkspaceJobController).
 */
class CareerController extends WorkspaceBaseController
{
    private function format(CareerEntry $e): array
    {
        return [
            'uuid' => $e->uuid, 'title' => $e->title, 'department' => $e->department,
            'started_on' => $e->started_on->toDateString(), 'ended_on' => $e->ended_on?->toDateString(),
            'current' => $e->ended_on === null, 'note' => $e->note, 'source' => $e->source,
        ];
    }

    private function rules(): array
    {
        return [
            'title'      => ['required', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:80'],
            'started_on' => ['required', 'date_format:Y-m-d', 'after:1950-01-01', 'before_or_equal:today'],
            'ended_on'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:started_on', 'before_or_equal:today'],
            'note'       => ['nullable', 'string', 'max:300'],
        ];
    }

    private function timeline(Hotel $hotel, int $userId): array
    {
        return CareerEntry::where('hotel_id', $hotel->id)->where('user_id', $userId)
            ->orderByDesc('started_on')->orderByDesc('id')->get()->map(fn ($e) => $this->format($e))->all();
    }

    /** GET /workspaces/{slug}/people/{uuid}/career */
    public function index(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $me     = $this->membership($request, $hotel);
        $target = $me ? $this->access->memberByUuid($hotel, $uuid) : null;
        if (! $target) {
            return $this->notFound('Pessoa não encontrada.');
        }

        return $this->success('Carreira carregada.', [
            'items'    => $this->timeline($hotel, $target->user_id),
            'can_edit' => $this->access->isAdmin($me),
        ]);
    }

    /** POST /workspaces/{slug}/people/{uuid}/career — só RH/dono. */
    public function store(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $target = $this->editableTarget($request, $hotel, $uuid);
        if ($target instanceof JsonResponse) {
            return $target;
        }
        $data = $request->validate($this->rules());

        $e = CareerEntry::create($data + ['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'user_id' => $target->user_id, 'source' => 'manual']);
        AuditLog::record($request, $hotel, 'career.created', $target->user, ['entry' => $e->uuid]);

        return $this->created('Registro adicionado.', ['items' => $this->timeline($hotel, $target->user_id), 'can_edit' => true]);
    }

    /** PUT /workspaces/{slug}/people/{uuid}/career/{entry} */
    public function update(Request $request, Hotel $hotel, string $uuid, string $entry): JsonResponse
    {
        $target = $this->editableTarget($request, $hotel, $uuid);
        if ($target instanceof JsonResponse) {
            return $target;
        }
        $e = CareerEntry::where('hotel_id', $hotel->id)->where('user_id', $target->user_id)->where('uuid', $entry)->first();
        if (! $e) {
            return $this->notFound('Registro não encontrado.');
        }
        $e->update($request->validate($this->rules()));
        AuditLog::record($request, $hotel, 'career.updated', $target->user, ['entry' => $e->uuid]);

        return $this->success('Registro atualizado.', ['items' => $this->timeline($hotel, $target->user_id), 'can_edit' => true]);
    }

    /** DELETE /workspaces/{slug}/people/{uuid}/career/{entry} */
    public function destroy(Request $request, Hotel $hotel, string $uuid, string $entry): JsonResponse
    {
        $target = $this->editableTarget($request, $hotel, $uuid);
        if ($target instanceof JsonResponse) {
            return $target;
        }
        $e = CareerEntry::where('hotel_id', $hotel->id)->where('user_id', $target->user_id)->where('uuid', $entry)->first();
        if (! $e) {
            return $this->notFound('Registro não encontrado.');
        }
        $e->delete();
        AuditLog::record($request, $hotel, 'career.deleted', $target->user, ['entry' => $entry]);

        return $this->success('Registro removido.', ['items' => $this->timeline($hotel, $target->user_id), 'can_edit' => true]);
    }

    private function editableTarget(Request $request, Hotel $hotel, string $uuid): HotelUser|JsonResponse
    {
        if (! $this->access->isAdmin($this->membership($request, $hotel))) {
            return $this->forbidden('Somente o dono ou o RH editam o histórico de carreira.');
        }

        return $this->access->memberByUuid($hotel, $uuid) ?? $this->notFound('Pessoa não encontrada.');
    }
}
