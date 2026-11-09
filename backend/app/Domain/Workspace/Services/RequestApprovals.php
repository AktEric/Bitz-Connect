<?php

namespace App\Domain\Workspace\Services;

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\RequestApproval;
use App\Models\RequestFlow;
use App\Models\User;
use App\Models\WorkspaceRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fluxo de aprovação das solicitações: a empresa define, por tipo, uma sequência de etapas
 * (gestor, RH, financeiro, diretoria). Cada pedido guarda uma cópia do fluxo e anda etapa por etapa.
 *
 * Quem decide cada etapa:
 *  - gestor: o gestor direto (e quem o substitui), chefe da área, gestores acima na hierarquia e o RH;
 *  - RH / financeiro / diretoria: quem tem esse papel de acesso;
 *  - o dono pode decidir qualquer etapa (e assume quando ninguém tem o papel).
 * Ninguém decide o próprio pedido. Etapa sem ninguém que possa decidir é pulada e registrada.
 */
class RequestApprovals
{
    public const ROLES = ['manager' => 'Gestor', 'hr' => 'RH', 'finance' => 'Financeiro', 'director' => 'Diretoria'];
    public const TYPES = ['vacation' => 'Férias', 'document' => 'Documento', 'reimbursement' => 'Reembolso', 'schedule' => 'Escala / horário', 'other' => 'Outro'];
    public const DEFAULT_STEPS = ['manager'];

    public function __construct(private readonly WorkspaceAccess $access) {}

    /** @return string[] */
    public function stepsFor(Hotel $hotel, string $type): array
    {
        $steps = RequestFlow::where('hotel_id', $hotel->id)->where('type', $type)->value('steps');

        return $this->sanitize(is_array($steps) ? $steps : self::DEFAULT_STEPS);
    }

    /** Só papéis conhecidos, sem repetir; o gestor é sempre a primeira etapa. */
    public function sanitize(array $steps): array
    {
        $steps = array_values(array_unique(array_filter($steps, fn ($s) => isset(self::ROLES[$s]) && $s !== 'manager')));

        return array_merge(['manager'], array_slice($steps, 0, 3));
    }

    /** Cria as etapas do pedido (cópia do fluxo atual) e ativa a primeira que tenha quem decida. */
    public function start(WorkspaceRequest $r, Hotel $hotel): void
    {
        DB::transaction(function () use ($r, $hotel) {
            foreach ($this->stepsFor($hotel, $r->type) as $i => $role) {
                RequestApproval::create(['workspace_request_id' => $r->id, 'position' => $i, 'role' => $role, 'status' => 'waiting']);
            }
            $this->activateFrom($r, $hotel, 0);
        });
        $r->unsetRelation('approvals');
    }

    /** Marca como atual a primeira etapa, a partir de $from, que tenha quem decida (pula as demais). Devolve null se acabaram. */
    private function activateFrom(WorkspaceRequest $r, Hotel $hotel, int $from): ?RequestApproval
    {
        $rows = RequestApproval::where('workspace_request_id', $r->id)->where('position', '>=', $from)->orderBy('position')->get();
        foreach ($rows as $row) {
            if ($this->approversOf($row, $r, $hotel)->isNotEmpty()) {
                $row->update(['status' => 'pending']);

                return $row;
            }
            $row->update(['status' => 'skipped', 'decided_at' => now(), 'note' => 'Etapa sem responsável na empresa: pulada.']);
        }

        return null;
    }

    /** Garante etapas em pedidos criados antes dos fluxos existirem. */
    public function ensureFlow(WorkspaceRequest $r, Hotel $hotel): void
    {
        if ($r->type === 'ticket' || $r->approvals()->exists()) {
            return;
        }
        if ($r->status === 'pending') {
            $this->start($r, $hotel);

            return;
        }
        // pedido antigo já decidido: uma única etapa de gestor com o resultado que já existia
        if (in_array($r->status, ['approved', 'rejected'], true)) {
            RequestApproval::create(['workspace_request_id' => $r->id, 'position' => 0, 'role' => 'manager', 'status' => $r->status,
                'decided_by' => $r->decided_by, 'decided_at' => $r->decided_at, 'note' => $r->decision_note]);
            $r->unsetRelation('approvals');
        }
    }

    public function current(WorkspaceRequest $r): ?RequestApproval
    {
        return $r->status === 'pending' ? $r->approvals()->where('status', 'pending')->first() : null;
    }

    /** Quem é esperado nesta etapa (para avisar e para mostrar "em análise por"). */
    public function approversOf(RequestApproval $a, WorkspaceRequest $r, Hotel $hotel): Collection
    {
        $requester = $r->requester ?? User::find($r->requester_id);
        $exclude   = fn (Collection $c) => $c->filter(fn ($u) => $u && $u->id !== $r->requester_id)->unique('id')->values();

        if ($a->role === 'manager') {
            $member = HotelUser::where('hotel_id', $hotel->id)->where('user_id', $r->requester_id)->first();

            return $member ? $exclude($this->access->reviewersFor($member, $hotel)) : collect();
        }

        $holders = HotelUser::with('user')->where('hotel_id', $hotel->id)->where('status', 'approved')->where('access_role', $a->role)->get()->pluck('user');
        $holders = $exclude($holders);
        if ($holders->isNotEmpty()) {
            return $holders;
        }

        // ninguém tem o papel: o dono assume
        return $exclude(HotelUser::with('user')->where('hotel_id', $hotel->id)->where('status', 'approved')->get()
            ->filter(fn (HotelUser $hu) => $this->access->isOwner($hu))->pluck('user'));
    }

    /** A etapa atual do pedido, se $user pode decidi-la agora. */
    public function actionableFor(User $user, ?HotelUser $membership, WorkspaceRequest $r, Hotel $hotel): ?RequestApproval
    {
        if (! $membership || $r->requester_id === $user->id || $r->status !== 'pending') {
            return null;
        }
        $a = $this->current($r);
        if (! $a) {
            return null;
        }
        $requester = $r->requester ?? User::find($r->requester_id);
        $ok = $this->access->isOwner($membership)
            || ($a->role === 'manager'
                ? $requester && $this->access->canManagePerson($user, $membership, $hotel, $requester)
                : $this->access->accessRole($membership, $hotel) === $a->role);

        return $ok ? $a : null;
    }

    /** Papéis de etapa que $user exerce (para ver pedidos que passam pelo seu setor sem ser gestor do solicitante). */
    public function rolesOf(?HotelUser $membership): array
    {
        $role = $membership?->access_role;

        return in_array($role, ['hr', 'finance', 'director'], true) ? [$role] : [];
    }

    /**
     * Registra a decisão da etapa atual. Aprovada: avança (ou aprova o pedido, se foi a última). Recusada: encerra recusando.
     *
     * @return array{final: ?string, next: ?RequestApproval, step: RequestApproval}
     */
    public function decide(WorkspaceRequest $r, Hotel $hotel, RequestApproval $step, User $by, string $decision, ?string $note): array
    {
        return DB::transaction(function () use ($r, $hotel, $step, $by, $decision, $note) {
            $step->update(['status' => $decision, 'decided_by' => $by->id, 'decided_at' => now(), 'note' => $note]);

            if ($decision === 'rejected') {
                $r->update(['status' => 'rejected', 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note]);
                RequestApproval::where('workspace_request_id', $r->id)->where('status', 'waiting')->update(['status' => 'skipped']);

                return ['final' => 'rejected', 'next' => null, 'step' => $step];
            }

            $next = $this->activateFrom($r, $hotel, $step->position + 1);
            if (! $next) {
                $r->update(['status' => 'approved', 'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note]);

                return ['final' => 'approved', 'next' => null, 'step' => $step];
            }

            return ['final' => null, 'next' => $next, 'step' => $step];
        });
    }

    /** Linha do tempo para a API: cada etapa, quem decidiu e quem ainda vai analisar. */
    public function present(WorkspaceRequest $r, Hotel $hotel): array
    {
        $this->ensureFlow($r, $hotel);
        $rows = $r->approvals()->with('decider')->get();

        return $rows->map(function (RequestApproval $a) use ($r, $hotel) {
            $open = in_array($a->status, ['pending', 'waiting'], true) && $r->status === 'pending';

            return [
                'position' => $a->position, 'role' => $a->role, 'label' => self::ROLES[$a->role] ?? $a->role, 'status' => $a->status,
                'is_current' => $a->status === 'pending' && $r->status === 'pending',
                'decided_by' => $a->decider ? $this->access->person($a->decider) : null,
                'decided_at' => $a->decided_at?->toISOString(), 'note' => $a->note,
                'approvers' => $open ? $this->approversOf($a, $r, $hotel)->take(5)->map(fn ($u) => $this->access->person($u))->values()->all() : [],
            ];
        })->values()->all();
    }

    /** Resumo curto para listas: "Aguardando Financeiro" e quantas etapas já passaram. */
    public function summary(WorkspaceRequest $r): array
    {
        $rows = $r->relationLoaded('approvals') ? $r->approvals : $r->approvals()->get();
        $total = $rows->count();
        $done  = $rows->whereIn('status', ['approved', 'skipped'])->count();
        $cur   = $rows->firstWhere('status', 'pending');

        return [
            'steps_total' => $total, 'steps_done' => $done,
            'stage' => $r->status === 'pending' && $cur ? 'Aguardando ' . mb_strtolower(self::ROLES[$cur->role] ?? $cur->role) : null,
            'stage_role' => $r->status === 'pending' ? $cur?->role : null,
        ];
    }
}
