<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\RequestApprovals;
use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\WorkspaceRequest;
use App\Models\WorkspaceRequestComment;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Solicitações internas ao RH/gestor: férias, documentos, reembolso, escala. */
class WorkspaceRequestController extends WorkspaceBaseController
{
    private const TYPES = ['vacation', 'document', 'reimbursement', 'schedule', 'other', 'ticket'];
    private const CATEGORIES = ['time', 'payroll', 'benefits', 'documents', 'other'];
    private const SLA_HOURS = 48; // prazo para o RH responder um chamado

    public function __construct(WorkspaceAccess $access, NotificationService $notifications, private readonly RequestApprovals $flow)
    {
        parent::__construct($access, $notifications);
    }

    private function format(WorkspaceRequest $r, ?HotelUser $viewerMembership, int $meId, bool $withDetails = false, ?Hotel $hotel = null): array
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
            'category'      => $r->category,
            'assigned_to'   => $r->relationLoaded('assignee') ? $this->access->person($r->assignee) : null,
            'due_at'        => $r->due_at?->toISOString(),
            'first_response_at' => $r->first_response_at?->toISOString(),
            'overdue'       => $r->type === 'ticket' && $r->due_at && $r->due_at->isPast() && in_array($r->status, ['pending', 'in_progress'], true),
        ];

        if ($r->type !== 'ticket' && $hotel) {
            $data += $this->flow->summary($r);
            $data['can_decide'] = (bool) $this->flow->actionableFor(request()->user(), $viewerMembership, $r, $hotel);
            if ($withDetails) {
                $data['approvals'] = $this->flow->present($r, $hotel);
            }
        }

        if ($withDetails) {
            $data['comments'] = $r->comments->map(fn ($c) => [
                'uuid' => $c->id, 'body' => $c->body, 'created_at' => $c->created_at?->toISOString(),
                'author' => $this->access->person($c->author),
            ])->values();
        }

        return $data;
    }

    /**
     * Pedidos que o usuário pode acompanhar/analisar: RH/dono veem todos; gestor, os da sua equipe; e quem tem papel de etapa
     * (financeiro, diretoria) vê os que passam pela sua etapa. Chamados ao RH são confidenciais e ficam só com o RH.
     */
    private function reviewableQuery(Request $request, Hotel $hotel, HotelUser $m)
    {
        $q = WorkspaceRequest::where('hotel_id', $hotel->id)->where('requester_id', '!=', $request->user()->id);

        if (! $this->access->isAdmin($m)) {
            $roles = $this->flow->rolesOf($m);
            $q->where('type', '!=', 'ticket')->where(fn ($w) => $w->whereIn('requester_id', $this->access->reportIds($request->user(), $hotel) ?: [0])
                ->when($roles, fn ($x) => $x->orWhereHas('approvals', fn ($ap) => $ap->whereIn('role', $roles))));
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
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'resolved', 'approved', 'rejected', 'cancelled'])],
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

        $page = $q->with(['requester', 'decider', 'assignee', 'approvals'])->orderByRaw("case when status in ('pending', 'in_progress') then 0 else 1 end")
            ->orderByDesc('created_at')->paginate(20);
        $meId = $request->user()->id;

        return $this->success('Solicitações carregadas com sucesso.', [
            'items'     => $page->getCollection()->map(fn ($r) => $this->format($r, $m, $meId, false, $hotel))->values(),
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
            'category'     => ['nullable', Rule::in(self::CATEGORIES), 'required_if:type,ticket'],
        ]);
        $isTicket = $data['type'] === 'ticket';
        if (! $isTicket) {
            unset($data['category']);
        }

        $r = WorkspaceRequest::create($data + [
            'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id,
            'requester_id' => $request->user()->id, 'status' => 'pending',
        ] + ($isTicket ? ['due_at' => now()->addHours(self::SLA_HOURS)] : []));
        $r->load(['requester', 'decider', 'assignee']);
        if (! $isTicket) {
            $this->flow->start($r, $hotel);
        }

        // Avisa quem vai analisar a PRIMEIRA etapa do fluxo (gestor, na maioria); chamados vão direto para o RH.
        $first = $isTicket ? null : $this->flow->current($r);
        $reviewers = $isTicket ? $this->access->adminUsers($hotel)->reject(fn ($u) => $u->id === $request->user()->id)->values() : ($first ? $this->flow->approversOf($first, $r, $hotel) : collect());
        foreach ($reviewers->filter() as $reviewer) {
            $this->notifications->workspaceNotice(
                $reviewer, $request->user(), 'workspace_request',
                $isTicket ? "Novo chamado de {$request->user()->name}" : "Nova solicitação de {$request->user()->name}", $r->title, $this->link($hotel, 'solicitacoes'),
            );
        }

        return $this->created('Solicitação enviada com sucesso.', $this->format($r, $m, $request->user()->id, false, $hotel));
    }

    private function findVisible(Request $request, Hotel $hotel, HotelUser $m, string $uuid): ?WorkspaceRequest
    {
        $r = WorkspaceRequest::with(['requester', 'decider', 'comments.author'])
            ->where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();

        if (! $r) {
            return null;
        }

        if ($r->type === 'ticket') {
            // chamado ao RH: só quem abriu e o RH
            return ($r->requester_id === $request->user()->id || $this->access->isAdmin($m)) ? $r : null;
        }

        return $this->access->canViewPerson($request->user(), $m, $hotel, $r->requester)
            || ($this->flow->rolesOf($m) && $r->approvals()->whereIn('role', $this->flow->rolesOf($m))->exists())
            ? $r : null;
    }

    /** POST /requests/{uuid}/assign — o RH assume o chamado. */
    public function assign(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $r = $m ? $this->findVisible($request, $hotel, $m, $uuid) : null;
        if (! $r || $r->type !== 'ticket' || ! $this->access->isAdmin($m)) {
            return $this->notFound('Chamado não encontrado.');
        }
        if (! in_array($r->status, ['pending', 'in_progress'], true)) {
            return $this->error('Este chamado já foi encerrado.', [], 409);
        }
        $r->update(['assigned_to' => $request->user()->id, 'status' => 'in_progress', 'first_response_at' => $r->first_response_at ?? now()]);
        AuditLog::record($request, $hotel, 'ticket.assigned', $r->requester, ['title' => $r->title]);
        $r->load(['requester', 'decider', 'assignee']);
        $this->notifications->workspaceNotice($r->requester, $request->user(), 'workspace_request_decision', 'Seu chamado está em atendimento', $r->title, $this->link($hotel, 'solicitacoes'));

        return $this->success('Chamado assumido.', $this->format($r, $m, $request->user()->id, true, $hotel));
    }

    /** POST /requests/{uuid}/resolve  { note } — encerra o chamado com justificativa obrigatória. */
    public function resolve(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $r = $m ? $this->findVisible($request, $hotel, $m, $uuid) : null;
        if (! $r || $r->type !== 'ticket' || ! $this->access->isAdmin($m)) {
            return $this->notFound('Chamado não encontrado.');
        }
        if (! in_array($r->status, ['pending', 'in_progress'], true)) {
            return $this->error('Este chamado já foi encerrado.', [], 409);
        }
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:1000']]);
        $r->update(['status' => 'resolved', 'decided_by' => $request->user()->id, 'decided_at' => now(), 'decision_note' => $data['note'], 'assigned_to' => $r->assigned_to ?? $request->user()->id, 'first_response_at' => $r->first_response_at ?? now()]);
        AuditLog::record($request, $hotel, 'ticket.resolved', $r->requester, ['title' => $r->title, 'justification' => $data['note']]);
        $r->load(['requester', 'decider', 'assignee']);
        $this->notifications->workspaceNotice($r->requester, $request->user(), 'workspace_request_decision', 'Chamado resolvido', $data['note'], $this->link($hotel, 'solicitacoes'));

        return $this->success('Chamado resolvido.', $this->format($r, $m, $request->user()->id, true, $hotel));
    }

    public function show(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $r = $m ? $this->findVisible($request, $hotel, $m, $uuid) : null;

        return $r
            ? $this->success('Solicitação carregada com sucesso.', $this->format($r, $m, $request->user()->id, true, $hotel))
            : $this->notFound('Solicitação não encontrada.');
    }

    public function decide(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $r = $m ? $this->findVisible($request, $hotel, $m, $uuid) : null;
        if (! $r) {
            return $this->notFound('Solicitação não encontrada.');
        }
        if ($r->type === 'ticket') {
            return $this->error('Chamados ao RH são assumidos e resolvidos, não aprovados.', [], 422);
        }
        if ($r->status !== 'pending') {
            return $this->error('Esta solicitação já foi analisada.', [], 409);
        }
        $this->flow->ensureFlow($r, $hotel);
        $step = $this->flow->actionableFor($request->user(), $m, $r, $hotel);
        if (! $step) {
            return $this->forbidden('Esta solicitação não está na sua etapa de análise.');
        }

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note'     => ['nullable', 'string', 'max:1000', 'required_if:decision,rejected'],
        ]);

        $result = $this->flow->decide($r, $hotel, $step, $request->user(), $data['decision'], $data['note'] ?? null);
        AuditLog::record($request, $hotel, 'request.decided', $r->requester, ['decision' => $data['decision'], 'title' => $r->title, 'step' => $step->role]);
        $r->refresh()->load(['requester', 'decider', 'approvals']);
        $label = RequestApprovals::ROLES[$step->role] ?? $step->role;
        $link = $this->link($hotel, 'solicitacoes');

        if ($result['final']) {
            $this->notifications->workspaceNotice($r->requester, $request->user(), 'workspace_request_decision',
                $result['final'] === 'approved' ? 'Solicitação aprovada' : 'Solicitação recusada', $r->title, $link);
        } elseif ($result['next']) {
            // avisa o solicitante do andamento e a próxima etapa de que chegou a vez dela
            $nextLabel = RequestApprovals::ROLES[$result['next']->role] ?? $result['next']->role;
            $this->notifications->workspaceNotice($r->requester, $request->user(), 'workspace_request_decision', "Etapa de {$label} aprovada", "Agora com: {$nextLabel} · {$r->title}", $link);
            foreach ($this->flow->approversOf($result['next'], $r, $hotel) as $u) {
                $this->notifications->workspaceNotice($u, $request->user(), 'workspace_request', "Solicitação aguardando sua análise ({$nextLabel})", "{$r->requester->name}: {$r->title}", $link);
            }
        }

        return $this->success('Solicitação analisada com sucesso.', $this->format($r, $m, $request->user()->id, true, $hotel));
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
        $r->approvals()->whereIn('status', ['pending', 'waiting'])->update(['status' => 'skipped']);

        return $this->success('Solicitação cancelada com sucesso.', $this->format($r, $m, $request->user()->id, false, $hotel));
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
        if ($r->type === 'ticket' && $r->requester_id !== $request->user()->id && ! $r->first_response_at) {
            $r->update(['first_response_at' => now()]);
        }
        if ($r->type === 'ticket' && $r->requester_id === $request->user()->id) {
            $r->loadMissing('assignee');
            $other = $r->assignee ?? $this->access->adminUsers($hotel)->first();
        } else {
            $other = $r->requester_id === $request->user()->id ? $r->decider ?? null : $r->requester;
        }
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
