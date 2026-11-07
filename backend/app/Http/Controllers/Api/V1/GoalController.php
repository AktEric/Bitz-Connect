<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Goal;
use App\Models\GoalUpdate;
use App\Models\Hotel;
use App\Models\HotelUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Metas individuais: definidas pelo gestor/RH, acompanhadas pelo colaborador e pelo gestor. */
class GoalController extends WorkspaceBaseController
{
    /** Status efetivo: meta ativa com prazo vencido e sem atingir conta como não batida. */
    private function effectiveStatus(Goal $g): string
    {
        if ($g->status === 'cancelled') {
            return 'cancelled';
        }
        if ($g->current_value >= $g->target_value) {
            return 'achieved';
        }

        return $g->period_end->isPast() && ! $g->period_end->isToday() ? 'missed' : 'active';
    }

    private function format(Goal $g, bool $withHistory = false): array
    {
        $data = [
            'uuid'          => $g->uuid,
            'title'         => $g->title,
            'description'   => $g->description,
            'unit'          => $g->unit,
            'target_value'  => $g->target_value,
            'current_value' => $g->current_value,
            'progress'      => $g->progressPercent(),
            'period_start'  => $g->period_start->toDateString(),
            'period_end'    => $g->period_end->toDateString(),
            'status'        => $this->effectiveStatus($g),
            'owner'         => $this->access->person($g->owner),
            'set_by'        => $this->access->person($g->setter),
        ];

        if ($withHistory) {
            $data['updates'] = $g->updates()->with('user')->latest()->limit(50)->get()->map(fn ($u) => [
                'value' => (float) $u->value, 'note' => $u->note,
                'created_at' => $u->created_at?->toISOString(), 'author' => $this->access->person($u->user),
            ])->values();
        }

        return $data;
    }

    private function persistStatus(Goal $g): void
    {
        $status = $this->effectiveStatus($g);
        if ($status !== $g->status) {
            $g->update(['status' => $status]);
        }
    }

    private function scopedQuery(Request $request, Hotel $hotel, HotelUser $m, string $scope)
    {
        $q = Goal::where('hotel_id', $hotel->id);

        return match ($scope) {
            'all'  => $this->access->isAdmin($m) ? $q : null,
            'team' => $this->access->isAdmin($m)
                ? $q->where('owner_id', '!=', $request->user()->id)
                : $q->whereIn('owner_id', $this->access->reportIds($request->user(), $hotel) ?: [0]),
            default => $q->where('owner_id', $request->user()->id),
        };
    }

    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $f = $request->validate([
            'scope'  => ['sometimes', Rule::in(['mine', 'team', 'all'])],
            'status' => ['sometimes', Rule::in(['active', 'achieved', 'missed', 'cancelled'])],
            'user'   => ['sometimes', 'uuid'],
        ]);

        $q = $this->scopedQuery($request, $hotel, $m, $f['scope'] ?? 'mine');
        if (! $q) {
            return $this->forbidden('Somente o RH pode ver todas as metas.');
        }
        if (isset($f['user'])) {
            $q->whereHas('owner', fn ($o) => $o->where('uuid', $f['user']));
        }

        $goals = $q->with(['owner', 'setter'])->orderByDesc('period_end')->limit(300)->get();
        if (isset($f['status'])) {
            $goals = $goals->filter(fn ($g) => $this->effectiveStatus($g) === $f['status']);
        }

        return $this->success('Metas carregadas com sucesso.', $goals->map(fn ($g) => $this->format($g))->values());
    }

    /** Resumo por pessoa para o gestor: batidas, não batidas, em andamento e média de atingimento. */
    public function summary(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $q = $this->scopedQuery($request, $hotel, $m, 'team');
        $goals = $q->with('owner')->where('status', '!=', 'cancelled')->limit(2000)->get()->groupBy('owner_id');

        $rows = $goals->map(function ($list) {
            $statuses = $list->map(fn ($g) => $this->effectiveStatus($g));
            $owner = $list->first()->owner;

            return [
                'person'    => $this->access->person($owner, HotelUser::where('user_id', $owner->id)->where('hotel_id', $list->first()->hotel_id)->first()),
                'total'     => $list->count(),
                'achieved'  => $statuses->filter(fn ($s) => $s === 'achieved')->count(),
                'missed'    => $statuses->filter(fn ($s) => $s === 'missed')->count(),
                'active'    => $statuses->filter(fn ($s) => $s === 'active')->count(),
                'avg_progress' => round($list->avg(fn ($g) => min($g->progressPercent(), 100)), 1),
            ];
        })->values();

        return $this->success('Resumo de metas carregado com sucesso.', $rows);
    }

    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $data = $request->validate([
            'owner_uuid'   => ['required', 'uuid'],
            'title'        => ['required', 'string', 'max:160'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'unit'         => ['nullable', 'string', 'max:30'],
            'target_value' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $owner = $this->access->memberByUuid($hotel, $data['owner_uuid']);
        if (! $owner) {
            return $this->error('Pessoa não faz parte desta empresa.', ['owner_uuid' => ['Pessoa inválida.']], 422);
        }
        $self = $owner->user_id === $request->user()->id;
        if (! $self && ! $this->access->canManagePerson($request->user(), $m, $hotel, $owner->user)) {
            return $this->forbidden('Você só pode definir metas para você ou para sua equipe.');
        }

        $g = Goal::create([
            'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'owner_id' => $owner->user_id,
            'set_by' => $request->user()->id, 'title' => $data['title'], 'description' => $data['description'] ?? null,
            'unit' => $data['unit'] ?? '%', 'target_value' => $data['target_value'], 'current_value' => 0,
            'period_start' => $data['period_start'], 'period_end' => $data['period_end'], 'status' => 'active',
        ]);
        $g->load(['owner', 'setter']);

        if (! $self) {
            $this->notifications->workspaceNotice(
                $owner->user, $request->user(), 'workspace_goal', 'Nova meta para você', $g->title, $this->link($hotel, 'metas'),
            );
        }

        return $this->created('Meta criada com sucesso.', $this->format($g));
    }

    private function findGoal(Request $request, Hotel $hotel, ?HotelUser $m, string $uuid): ?Goal
    {
        if (! $m) {
            return null;
        }
        $g = Goal::with(['owner', 'setter'])->where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();

        return $g && $this->access->canViewPerson($request->user(), $m, $hotel, $g->owner) ? $g : null;
    }

    public function show(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $g = $this->findGoal($request, $hotel, $this->membership($request, $hotel), $uuid);

        return $g ? $this->success('Meta carregada com sucesso.', $this->format($g, true)) : $this->notFound('Meta não encontrada.');
    }

    /** Registra evolução (valor acumulado atual). Dono ou gestor/RH. */
    public function addUpdate(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $g = $this->findGoal($request, $hotel, $this->membership($request, $hotel), $uuid);
        if (! $g) {
            return $this->notFound('Meta não encontrada.');
        }
        if ($g->status === 'cancelled') {
            return $this->error('Meta cancelada não pode receber atualizações.', [], 409);
        }

        $data = $request->validate([
            'value' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'note'  => ['nullable', 'string', 'max:500'],
        ]);

        GoalUpdate::create(['goal_id' => $g->id, 'user_id' => $request->user()->id, 'value' => $data['value'], 'note' => $data['note'] ?? null]);
        $g->update(['current_value' => $data['value']]);
        $this->persistStatus($g);

        if ($g->owner_id !== $request->user()->id) {
            $this->notifications->workspaceNotice(
                $g->owner, $request->user(), 'workspace_goal', 'Meta atualizada', $g->title, $this->link($hotel, 'metas'),
            );
        }

        return $this->success('Progresso registrado com sucesso.', $this->format($g->fresh(['owner', 'setter']), true));
    }

    /** Edita/cancela: somente quem pode gerir a pessoa (ou o próprio dono em metas que ele mesmo criou). */
    public function update(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $g = $this->findGoal($request, $hotel, $m, $uuid);
        if (! $g) {
            return $this->notFound('Meta não encontrada.');
        }
        $own = $g->owner_id === $request->user()->id && $g->set_by === $request->user()->id;
        if (! $own && ! $this->access->canManagePerson($request->user(), $m, $hotel, $g->owner)) {
            return $this->forbidden('Você não pode editar esta meta.');
        }

        $data = $request->validate([
            'title'        => ['sometimes', 'string', 'max:160'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'target_value' => ['sometimes', 'numeric', 'gt:0', 'max:999999999'],
            'period_end'   => ['sometimes', 'date', 'after_or_equal:' . $g->period_start->toDateString()],
            'cancelled'    => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('cancelled', $data)) {
            $data['status'] = $data['cancelled'] ? 'cancelled' : 'active';
            unset($data['cancelled']);
        }
        $g->update($data);
        $this->persistStatus($g);

        return $this->success('Meta atualizada com sucesso.', $this->format($g->fresh(['owner', 'setter'])));
    }
}
