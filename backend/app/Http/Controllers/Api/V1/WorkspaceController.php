<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
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

    private const ACCESS_ROLES = ['owner', 'hr', 'finance', 'manager', 'employee'];

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

        $target->update(['access_role' => $data['access_role']]);

        return $this->success('Papel atualizado com sucesso.', [
            'uuid' => $uuid, 'access_role' => $this->access->accessRole($target, $hotel),
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
            ],
        ]);
    }
}
