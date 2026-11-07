<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\Payslip;
use App\Models\WorkspaceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Entrada do Workspace (intranet da empresa).
 */
class WorkspaceController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly WorkspaceAccess $access) {}

    /** GET /me/workspaces — empresas em que o usuário tem vínculo aprovado. */
    public function mine(Request $request): JsonResponse
    {
        $data = HotelUser::with(['hotel', 'department'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'approved')
            ->whereHas('hotel')
            ->get()
            ->map(fn (HotelUser $hu) => [
                'hotel' => [
                    'uuid'       => $hu->hotel->uuid,
                    'slug'       => $hu->hotel->slug,
                    'name'       => $hu->hotel->name,
                    'segment'    => $hu->hotel->segment,
                    'avatar_url' => $hu->hotel->logo_path ? asset('storage/' . $hu->hotel->logo_path) : null,
                ],
                'role'       => $hu->role,
                'job_title'  => $hu->job_title,
                'department' => $hu->department?->name,
            ])
            ->values();

        return $this->success('Empresas carregadas com sucesso.', $data);
    }

    private const ACCESS_ROLES = ['owner', 'hr', 'finance', 'director', 'manager', 'employee'];

    /** GET /workspaces/{hotel:slug}/roles — pessoas e papéis de acesso (dono e RH). */
    public function roles(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->access->membership($request->user(), $hotel);
        if (! $this->access->isAdmin($m)) {
            return $this->forbidden('Somente o dono ou o RH podem ver os papéis de acesso.');
        }

        $items = HotelUser::with('user')->where('hotel_id', $hotel->id)->where('status', 'approved')->get()
            ->map(fn ($hu) => [
                'user'        => $this->access->person($hu->user, $hu),
                'email'       => $hu->user?->email,
                'access_role' => $this->access->accessRole($hu, $hotel),
            ])->sortBy(fn ($r) => $r['user']['name'])->values();

        return $this->success('Papéis carregados com sucesso.', [
            'items'    => $items,
            'can_edit' => $this->access->isOwner($m),
        ]);
    }

    /** PATCH /workspaces/{hotel:slug}/roles/{uuid} — define o papel de acesso (somente o dono). */
    public function updateRole(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->access->membership($request->user(), $hotel);
        if (! $this->access->isOwner($m)) {
            return $this->forbidden('Somente o dono pode alterar papéis de acesso.');
        }

        $data   = $request->validate(['access_role' => ['required', 'string', \Illuminate\Validation\Rule::in(self::ACCESS_ROLES)]]);
        $target = $this->access->memberByUuid($hotel, $uuid);
        if (! $target) {
            return $this->notFound('Pessoa não encontrada.');
        }

        if ($this->access->isOwner($target) && $data['access_role'] !== 'owner') {
            $owners = HotelUser::where('hotel_id', $hotel->id)->where('status', 'approved')->get()
                ->filter(fn ($hu) => $this->access->isOwner($hu))->count();
            if ($owners <= 1) {
                return $this->error('A empresa precisa ter pelo menos um dono.', [], 422);
            }
        }

        $before = $this->access->accessRole($target, $hotel);
        $target->update(['access_role' => $data['access_role']]);
        AuditLog::record($request, $hotel, 'role.changed', $target->user, ['from' => $before, 'to' => $data['access_role']]);

        return $this->success('Papel atualizado com sucesso.', [
            'uuid' => $uuid, 'access_role' => $this->access->accessRole($target, $hotel),
        ]);
    }

    private function privilegedMembers(Hotel $hotel)
    {
        return HotelUser::with('user')->where('hotel_id', $hotel->id)->where('status', 'approved')->get()
            ->filter(fn (HotelUser $hu) => $hu->user && in_array($this->access->accessRole($hu, $hotel), ['owner', 'hr', 'finance', 'director'], true));
    }

    /** GET /workspaces/{hotel:slug}/dashboard — números agregados da empresa (dono, RH e diretoria). */
    public function dashboard(Request $request, Hotel $hotel, \App\Domain\Workspace\Services\DashboardService $dashboard): JsonResponse
    {
        if (! $this->access->canSeeDashboard($this->access->membership($request->user(), $hotel))) {
            return $this->forbidden('Somente o dono, o RH e a diretoria veem o painel.');
        }

        return $this->success('Painel carregado com sucesso.', $dashboard->build($hotel));
    }

    /** GET /workspaces/{hotel:slug}/security — política de segurança da empresa (dono e RH). */
    public function security(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->access->membership($request->user(), $hotel);
        if (! $this->access->isAdmin($m)) {
            return $this->forbidden('Somente o dono ou o RH veem a política de segurança.');
        }
        $privileged = $this->privilegedMembers($hotel);

        return $this->success('Segurança carregada com sucesso.', [
            'require_2fa'            => (bool) ($hotel->security_settings['require_2fa'] ?? false),
            'privileged_total'       => $privileged->count(),
            'privileged_without_2fa' => $privileged->filter(fn ($hu) => ! $hu->user->hasTwoFactor())
                ->map(fn ($hu) => $this->access->person($hu->user, $hu))->values(),
            'can_edit'               => $this->access->isOwner($m),
        ]);
    }

    /** PUT /workspaces/{hotel:slug}/security  { require_2fa } — somente o dono. */
    public function updateSecurity(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->access->membership($request->user(), $hotel);
        if (! $this->access->isOwner($m)) {
            return $this->forbidden('Somente o dono altera a política de segurança.');
        }
        $data = $request->validate(['require_2fa' => ['required', 'boolean']]);
        if ($data['require_2fa'] && ! $request->user()->hasTwoFactor()) {
            return $this->error('Ative a sua verificação em duas etapas antes de exigi-la da empresa; senão você mesmo ficaria sem acesso.', ['require_2fa' => ['Ative primeiro o seu 2FA.']], 422);
        }
        $hotel->forceFill(['security_settings' => array_merge($hotel->security_settings ?? [], ['require_2fa' => (bool) $data['require_2fa']])])->save();
        \App\Models\AuditLog::record($request, $hotel, 'security.policy_changed', null, ['require_2fa' => (bool) $data['require_2fa']]);

        return $this->success('Política atualizada.', ['require_2fa' => (bool) $data['require_2fa']]);
    }

    /** GET /workspaces/{hotel:slug}/audit — trilha de auditoria (dono e RH). */
    public function audit(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->access->membership($request->user(), $hotel);
        if (! $this->access->isAdmin($m)) {
            return $this->forbidden('Somente o dono ou o RH podem ver a auditoria.');
        }

        $q = AuditLog::with(['actor', 'target'])->where('hotel_id', $hotel->id);
        if ($request->filled('action')) {
            $q->where('action', (string) $request->query('action'));
        }
        $page = $q->orderByDesc('id')->paginate(50);

        return $this->success('Auditoria carregada com sucesso.', [
            'items' => $page->getCollection()->map(fn ($l) => [
                'action'     => $l->action,
                'actor'      => $this->access->person($l->actor),
                'target'     => $this->access->person($l->target),
                'meta'       => $l->meta,
                'ip'         => $l->ip,
                'created_at' => $l->created_at?->toISOString(),
            ])->values(),
            'page'      => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'actions'   => AuditLog::where('hotel_id', $hotel->id)->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    /** GET /workspaces/{hotel:slug}/audit/export — CSV das últimas 5000 ações (dono e RH). A própria exportação é registrada. */
    public function auditExport(Request $request, Hotel $hotel): \Illuminate\Http\Response|JsonResponse
    {
        $m = $this->access->membership($request->user(), $hotel);
        if (! $this->access->isAdmin($m)) {
            return $this->forbidden('Somente o dono ou o RH exportam a auditoria.');
        }

        $esc = fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"';
        $lines = [implode(';', ['Data/hora (Brasília)', 'Ação', 'Quem fez', 'Sobre quem', 'IP', 'Detalhes'])];
        AuditLog::with(['actor', 'target'])->where('hotel_id', $hotel->id)->orderByDesc('id')->limit(5000)->get()->each(function ($l) use (&$lines, $esc) {
            $lines[] = implode(';', [
                $esc($l->created_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s')), $esc($l->action),
                $esc($l->actor?->name), $esc($l->target?->name), $esc($l->ip), $esc($l->meta ? json_encode($l->meta, JSON_UNESCAPED_UNICODE) : ''),
            ]);
        });
        AuditLog::record($request, $hotel, 'audit.exported');

        return response("\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n", 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="auditoria.csv"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    /** GET /workspaces/{hotel:slug} — dados da empresa + meu perfil + contadores. */
    public function show(Request $request, Hotel $hotel): JsonResponse
    {
        $user       = $request->user();
        $membership = $this->access->membership($user, $hotel);

        if (! $membership) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $membership->load(['department', 'manager']);

        $isAdmin   = $this->access->isAdmin($membership);
        $isManager = $this->access->isManager($user, $hotel);

        // Solicitações que dependem de mim: admin vê todas as pendentes; gestor, as da equipe.
        $reviewQuery = WorkspaceRequest::where('hotel_id', $hotel->id)->where('status', 'pending')
            ->where('requester_id', '!=', $user->id);
        if (! $isAdmin) {
            $reviewQuery->whereIn('requester_id', $this->access->reportIds($user, $hotel) ?: [0]);
        }

        return $this->success('Workspace carregado com sucesso.', [
            'hotel' => [
                'uuid'       => $hotel->uuid,
                'slug'       => $hotel->slug,
                'name'       => $hotel->name,
                'segment'    => $hotel->segment,
                'city'       => $hotel->city,
                'state'      => $hotel->state,
                'avatar_url' => $hotel->logo_path ? asset('storage/' . $hotel->logo_path) : null,
            ],
            'me' => [
                'role'         => $membership->role,
                'is_admin'     => $isAdmin,
                'access_role'  => $this->access->accessRole($membership, $hotel),
                'is_manager'   => $isManager,
                'two_factor'   => $user->hasTwoFactor(),
                'can_see_dashboard' => $this->access->canSeeDashboard($membership),
                'needs_two_factor' => (bool) ($hotel->security_settings['require_2fa'] ?? false) && ! $user->hasTwoFactor()
                    && in_array($this->access->accessRole($membership, $hotel), ['owner', 'hr', 'finance', 'director'], true),
                'team_size'    => count($this->access->reportIds($user, $hotel)),
                'areas_headed' => \App\Models\Department::where('hotel_id', $hotel->id)->where('head_id', $user->id)->orderBy('name')->pluck('name')->all(),
                'can_post'     => $isAdmin || $isManager,
                'job_title'    => $membership->job_title,
                'hired_at'     => $membership->hired_at?->toDateString(),
                'department'   => $membership->department?->name,
                'manager'      => $this->access->person($membership->manager),
            ],
            'counts' => [
                'members'            => HotelUser::where('hotel_id', $hotel->id)->where('status', 'approved')->count(),
                'requests_to_review' => ($isAdmin || $isManager) ? $reviewQuery->count() : 0,
                'my_open_requests'   => WorkspaceRequest::where('hotel_id', $hotel->id)
                    ->where('requester_id', $user->id)->where('status', 'pending')->count(),
                'new_payslips'       => Payslip::where('hotel_id', $hotel->id)
                    ->where('user_id', $user->id)->whereNull('first_viewed_at')->count(),
                // Comunicados do mural que exigem confirmação de leitura e ainda não confirmei
                'pending_acks'       => \App\Models\WorkspacePost::where('hotel_id', $hotel->id)->where('requires_ack', true)
                    ->where('author_id', '!=', $user->id)
                    ->where(fn ($q) => $q->whereNull('department_id')->when($membership->department_id, fn ($q2) => $q2->orWhere('department_id', $membership->department_id)))
                    ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('workspace_post_acks')
                        ->whereColumn('workspace_post_acks.workspace_post_id', 'workspace_posts.id')->where('workspace_post_acks.user_id', $user->id))
                    ->count(),
            ],
        ]);
    }
}
