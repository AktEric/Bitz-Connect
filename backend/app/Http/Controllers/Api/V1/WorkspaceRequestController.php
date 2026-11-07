<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\WorkspaceRequest;
use App\Models\WorkspaceRequestComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Solicitações internas ao RH/gestor: férias, documentos, reembolso, escala. */
class WorkspaceRequestController extends WorkspaceBaseController
{
    private const TYPES = ['vacation', 'document', 'reimbursement', 'schedule', 'other'];

    private function format(WorkspaceRequest $r, ?HotelUser $viewerMembership, int $meId, bool $withDetails = false): array
    {
        $data = [
            'uuid'          => $r->uuid,
            'type'          => $r->type,
            'title'         => $r->title,
            'description'   => $r->description,
            'starts_on'     => $r->starts_on?->toDateString(),
            'ends_on'       => $r->ends_on?->toDateString(),
            'amount_cents'  => $r->amount_cents,
            'status'        => $r->status,
            'decision_note' => $r->decision_note,
            'decided_at'    => $r->decided_at?->toISOString(),
            'decided_by'    => $r->relationLoaded('decider') ? $this->access->person($r->decider) : null,
            'requester'     => $this->access->person($r->requester),
            'created_at'    => $r->created_at?->toISOString(),
            'is_mine'       => $r->requester_id === $meId,
        ];

        if ($withDetails) {
            $data['comments'] = $r->comments->map(fn ($c) => [
                'uuid' => $c->id, 'body' => $c->body, 'created_at' => $c->created_at?->toISOString(),
                'author' => $this->access->person($c->author),
            ])->values();
        }

        return $data;
    }

    /** Pedidos que o usuário pode analisar (admin: todos; gestor: da equipe direta). */
    private function reviewableQuery(Request $request, Hotel $hotel, HotelUser $m)
    {
        $q = WorkspaceRequest::where('hotel_id', $hotel->id)->where('requester_id', '!=', $request->user()->id);

        if (! $this->access->isAdmin($m)) {
            $q->whereIn('requester_id', $this->access->reportIds($request->user(), $hotel) ?: [0]);
        }

        return $q;
    }

    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $filters = $request->validate([
            'scope'  => ['sometimes', Rule::in(['mine', 'review', 'all'])],
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'type'   => ['sometimes', Rule::in(self::TYPES)],
        ]);
        $scope = $filters['scope'] ?? 'mine';

        if ($scope === 'all') {
            if (! $this->access->isAdmin($m)) {
                return $this->forbidden('Somente o RH pode ver todas as solicitações.');
            }
            $q = WorkspaceRequest::where('hotel_id', $hotel->id);
        } elseif ($scope === 'review') {
            $q = $this->reviewableQuery($request, $hotel, $m);
        } else {
            $q = WorkspaceRequest::where('hotel_id', $hotel->id)->where('requester_id', $request->user()->id);
        }

        if (isset($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (isset($filters['type'])) {
            $q->where('type', $filters['type']);
        }

        $page = $q->with(['requester', 'decider'])->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByDesc('created_at')->paginate(20);
        $meId = $request->user()->id;

        return $this->success('Solicitações carregadas com sucesso.', [
            'items'     => $page->getCollection()->map(fn ($r) => $this->format($r, $m, $meId))->values(),
            'page'      => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $data = $request->validate([
            'type'         => ['required', Rule::in(self::TYPES)],
            'title'        => ['required', 'string', 'max:160'],
            'description'  => ['nullable', 'string', 'max:3000'],
            'starts_on'    => ['nullable', 'date', 'required_if:type,vacation'],
            'ends_on'      => ['nullable', 'date', 'after_or_equal:starts_on', 'required_if:type,vacation'],
            'amount_cents' => ['nullable', 'integer', 'min:1', 'max:100000000', 'required_if:type,reimbursement'],
        ]);

        $r = WorkspaceRequest::create($data + [
            'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id,
            'requester_id' => $request->user()->id, 'status' => 'pending',
        ]);
        $r->load(['requester', 'decider']);

        // Avisa quem vai analisar: gestor direto; sem gestor, os admins (RH).
        $reviewers = $m->manager_id
            ? collect([$m->manager])->filter()
            : $this->access->adminUsers($hotel);
        foreach ($reviewers->filter() as $reviewer) {
            $this->notifications->workspaceNotice(
                $reviewer, $request->user(), 'workspace_request',
                "Nova solicitação de {$request->user()->name}", $r->title, $this->link($hotel, 'solicitacoes'),
            );
        }

        return $this->created('Solicitação enviada com sucesso.', $this->format($r, $m, $request->user()->id));
    }

    private function findVisible(Request $request, Hotel $hotel, HotelUser $m, string $uuid): ?WorkspaceRequest
    {
        $r = WorkspaceRequest::with(['requester', 'decider', 'comments.author'])
            ->where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();

        if (! $r) {
            return null;
        }

        return $this->access->canViewPerson($request->user(), $m, $hotel, $r->requester) ? $r : null;
    }

    public function show(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $r = $m ? $this->findVisible($request, $hotel, $m, $uuid) : null;

        return $r
            ? $this->success('Solicitação carregada com sucesso.', $this->format($r, $m, $request->user()->id, true))
            : $this->notFound('Solicitação não encontrada.');
    }

    public function decide(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $r = $m ? $this->findVisible($request, $hotel, $m, $uuid) : null;
        if (! $r) {
            return $this->notFound('Solicitação não encontrada.');
        }
        if (! $this->access->canManagePerson($request->user(), $m, $hotel, $r->requester)) {
            return $this->forbidden('Você não pode analisar esta solicitação.');
        }
        if ($r->status !== 'pending') {
            return $this->error('Esta solicitação já foi analisada.', [], 409);
        }

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note'     => ['nullable', 'string', 'max:1000', 'required_if:decision,rejected'],
        ]);

        $r->update([
            'status' => $data['decision'], 'decided_by' => $request->user()->id,
            'decided_at' => now(), 'decision_note' => $data['note'] ?? null,
        ]);
        $r->load('decider');

        $this->notifications->workspaceNotice(
            $r->requester, $request->user(), 'workspace_request_decision',
            $data['decision'] === 'approved' ? 'Solicitação aprovada' : 'Solicitação recusada',
            $r->title, $this->link($hotel, 'solicitacoes'),
        );

        return $this->success('Solicitação analisada com sucesso.', $this->format($r, $m, $request->user()->id, true));
    }

    public function cancel(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $r = $m ? $this->findVisible($request, $hotel, $m, $uuid) : null;
        if (! $r || $r->requester_id !== $request->user()->id) {
            return $this->notFound('Solicitação não encontrada.');
        }
        if ($r->status !== 'pending') {
            return $this->error('Só é possível cancelar solicitações pendentes.', [], 409);
        }
        $r->update(['status' => 'cancelled']);

        return $this->success('Solicitação cancelada com sucesso.', $this->format($r, $m, $request->user()->id));
    }

    public function comment(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $r = $m ? $this->findVisible($request, $hotel, $m, $uuid) : null;
        if (! $r) {
            return $this->notFound('Solicitação não encontrada.');
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        $c = WorkspaceRequestComment::create([
            'workspace_request_id' => $r->id, 'author_id' => $request->user()->id, 'body' => $data['body'],
        ]);

        // Avisa a outra ponta da conversa.
        $other = $r->requester_id === $request->user()->id ? $r->decider ?? null : $r->requester;
        if ($other) {
            $this->notifications->workspaceNotice(
                $other, $request->user(), 'workspace_request_comment',
                "{$request->user()->name} comentou em “{$r->title}”", Str::limit($data['body'], 100),
                $this->link($hotel, 'solicitacoes'),
            );
        }

        return $this->created('Comentário criado com sucesso.', [
            'uuid' => $c->id, 'body' => $c->body, 'created_at' => $c->created_at?->toISOString(),
            'author' => $this->access->person($request->user()),
        ]);
    }
}
