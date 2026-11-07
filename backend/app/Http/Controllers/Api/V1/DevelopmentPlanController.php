<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\DevelopmentItem;
use App\Models\DevelopmentNote;
use App\Models\DevelopmentPlan;
use App\Models\Hotel;
use App\Models\HotelUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** PDI — Plano de Desenvolvimento Individual: objetivos, ações e anotações de acompanhamento. */
class DevelopmentPlanController extends WorkspaceBaseController
{
    private const ITEM_TYPES = ['course', 'mentoring', 'project', 'reading', 'other'];

    private function formatItem(DevelopmentItem $i): array
    {
        return [
            'uuid' => $i->uuid, 'title' => $i->title, 'type' => $i->type,
            'due_on' => $i->due_on?->toDateString(), 'status' => $i->status, 'notes' => $i->notes,
        ];
    }

    private function format(DevelopmentPlan $p, bool $detail = false): array
    {
        $items = $p->items;
        $done = $items->where('status', 'done')->count();

        $data = [
            'uuid'         => $p->uuid,
            'title'        => $p->title,
            'objective'    => $p->objective,
            'period_start' => $p->period_start?->toDateString(),
            'period_end'   => $p->period_end?->toDateString(),
            'status'       => $p->status,
            'owner'        => $this->access->person($p->owner),
            'items_total'  => $items->count(),
            'items_done'   => $done,
            'progress'     => $items->count() ? round($done / $items->count() * 100) : 0,
        ];

        if ($detail) {
            $data['items'] = $items->sortBy('id')->map(fn ($i) => $this->formatItem($i))->values();
            $data['notes'] = $p->notes->sortByDesc('id')->map(fn ($n) => [
                'uuid' => $n->id, 'body' => $n->body, 'created_at' => $n->created_at?->toISOString(),
                'author' => $this->access->person($n->author),
            ])->values();
        }

        return $data;
    }

    private function find(Request $request, Hotel $hotel, ?HotelUser $m, string $uuid): ?DevelopmentPlan
    {
        if (! $m) {
            return null;
        }
        $p = DevelopmentPlan::with(['owner', 'items', 'notes.author'])->where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();

        return $p && $this->access->canViewPerson($request->user(), $m, $hotel, $p->owner) ? $p : null;
    }

    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $scope = $request->validate(['scope' => ['sometimes', Rule::in(['mine', 'team', 'all'])]])['scope'] ?? 'mine';
        $q = DevelopmentPlan::where('hotel_id', $hotel->id);

        if ($scope === 'all') {
            if (! $this->access->isAdmin($m)) {
                return $this->forbidden('Somente o RH pode ver todos os PDIs.');
            }
        } elseif ($scope === 'team') {
            $this->access->isAdmin($m)
                ? $q->where('user_id', '!=', $request->user()->id)
                : $q->whereIn('user_id', $this->access->reportIds($request->user(), $hotel) ?: [0]);
        } else {
            $q->where('user_id', $request->user()->id);
        }

        $plans = $q->with(['owner', 'items'])->orderByDesc('created_at')->limit(200)->get();

        return $this->success('PDIs carregados com sucesso.', $plans->map(fn ($p) => $this->format($p))->values());
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
            'objective'    => ['nullable', 'string', 'max:3000'],
            'period_start' => ['nullable', 'date'],
            'period_end'   => ['nullable', 'date', 'after_or_equal:period_start'],
        ]);

        $owner = $this->access->memberByUuid($hotel, $data['owner_uuid']);
        if (! $owner) {
            return $this->error('Pessoa não faz parte desta empresa.', ['owner_uuid' => ['Pessoa inválida.']], 422);
        }
        $self = $owner->user_id === $request->user()->id;
        if (! $self && ! $this->access->canManagePerson($request->user(), $m, $hotel, $owner->user)) {
            return $this->forbidden('Você só pode criar PDI para você ou para sua equipe.');
        }

        $p = DevelopmentPlan::create([
            'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'user_id' => $owner->user_id,
            'created_by' => $request->user()->id, 'title' => $data['title'], 'objective' => $data['objective'] ?? null,
            'period_start' => $data['period_start'] ?? null, 'period_end' => $data['period_end'] ?? null,
        ]);
        $p->load(['owner', 'items', 'notes.author']);

        if (! $self) {
            $this->notifications->workspaceNotice(
                $owner->user, $request->user(), 'workspace_pdi', 'Novo PDI para você', $p->title, $this->link($hotel, 'desenvolvimento'),
            );
        }

        return $this->created('PDI criado com sucesso.', $this->format($p, true));
    }

    public function show(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $p = $this->find($request, $hotel, $this->membership($request, $hotel), $uuid);

        return $p ? $this->success('PDI carregado com sucesso.', $this->format($p, true)) : $this->notFound('PDI não encontrado.');
    }

    public function update(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $p = $this->find($request, $hotel, $m, $uuid);
        if (! $p) {
            return $this->notFound('PDI não encontrado.');
        }
        $own = $p->created_by === $request->user()->id;
        if (! $own && ! $this->access->canManagePerson($request->user(), $m, $hotel, $p->owner)) {
            return $this->forbidden('Você não pode editar este PDI.');
        }

        $p->update($request->validate([
            'title'        => ['sometimes', 'string', 'max:160'],
            'objective'    => ['nullable', 'string', 'max:3000'],
            'period_start' => ['nullable', 'date'],
            'period_end'   => ['nullable', 'date'],
            'status'       => ['sometimes', Rule::in(['active', 'completed', 'archived'])],
        ]));

        return $this->success('PDI atualizado com sucesso.', $this->format($p->fresh(['owner', 'items', 'notes.author']), true));
    }

    public function addItem(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $p = $this->find($request, $hotel, $this->membership($request, $hotel), $uuid);
        if (! $p) {
            return $this->notFound('PDI não encontrado.');
        }

        $data = $request->validate([
            'title'  => ['required', 'string', 'max:160'],
            'type'   => ['required', Rule::in(self::ITEM_TYPES)],
            'due_on' => ['nullable', 'date'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);
        $item = $p->items()->create($data + ['uuid' => (string) Str::uuid(), 'status' => 'todo']);

        return $this->created('Ação adicionada com sucesso.', $this->formatItem($item));
    }

    public function updateItem(Request $request, Hotel $hotel, string $uuid, string $item): JsonResponse
    {
        $p = $this->find($request, $hotel, $this->membership($request, $hotel), $uuid);
        $i = $p?->items()->where('uuid', $item)->first();
        if (! $i) {
            return $this->notFound('Ação não encontrada.');
        }

        $i->update($request->validate([
            'title'  => ['sometimes', 'string', 'max:160'],
            'type'   => ['sometimes', Rule::in(self::ITEM_TYPES)],
            'due_on' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['todo', 'doing', 'done'])],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]));

        return $this->success('Ação atualizada com sucesso.', $this->formatItem($i->fresh()));
    }

    public function destroyItem(Request $request, Hotel $hotel, string $uuid, string $item): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $p = $this->find($request, $hotel, $m, $uuid);
        $i = $p?->items()->where('uuid', $item)->first();
        if (! $i) {
            return $this->notFound('Ação não encontrada.');
        }
        if (! $this->access->canManagePerson($request->user(), $m, $hotel, $p->owner) && $p->created_by !== $request->user()->id) {
            return $this->forbidden('Você não pode remover esta ação.');
        }
        $i->delete();

        return $this->success('Ação removida com sucesso.');
    }

    public function addNote(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $p = $this->find($request, $hotel, $this->membership($request, $hotel), $uuid);
        if (! $p) {
            return $this->notFound('PDI não encontrado.');
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $n = DevelopmentNote::create(['development_plan_id' => $p->id, 'author_id' => $request->user()->id, 'body' => $data['body']]);

        if ($p->user_id !== $request->user()->id) {
            $this->notifications->workspaceNotice(
                $p->owner, $request->user(), 'workspace_pdi', 'Nova anotação no seu PDI', Str::limit($data['body'], 100), $this->link($hotel, 'desenvolvimento'),
            );
        }

        return $this->created('Anotação registrada com sucesso.', [
            'uuid' => $n->id, 'body' => $n->body, 'created_at' => $n->created_at?->toISOString(),
            'author' => $this->access->person($request->user()),
        ]);
    }
}
