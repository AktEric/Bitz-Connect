<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Hotel\DTOs\CreateHotelDTO;
use App\Domain\Hotel\DTOs\UpdateHotelDTO;
use App\Domain\Hotel\DTOs\ClaimHotelDTO;
use App\Domain\Hotel\Repositories\HotelRepositoryInterface;
use App\Domain\Hotel\Services\ClaimService;
use App\Domain\Hotel\Services\EmployeeService;
use App\Domain\Hotel\Services\FollowerService;
use App\Domain\Hotel\Services\HotelService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hotel\CreateHotelRequest;
use App\Http\Requests\Hotel\UpdateHotelRequest;
use App\Http\Requests\User\UploadImageRequest;
use App\Http\Resources\HotelResource;
use App\Http\Traits\ApiResponseTrait;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Hotel;
use App\Models\HotelUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Handles hotel management endpoints.
 * Requirements: 3.1 – 3.8
 */
class HotelController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly HotelService             $hotelService,
        private readonly HotelRepositoryInterface $hotelRepository,
        private readonly FollowerService          $followerService,
        private readonly EmployeeService          $employeeService,
        private readonly ClaimService             $claimService,
    ) {}

    /**
     * POST /hotels
     * Create a new hotel and link creator as HotelAdmin.
     * Requirements: 3.1, 3.2
     */
    public function store(CreateHotelRequest $request): JsonResponse
    {
        $this->authorize('create', Hotel::class);

        $hotel = $this->hotelService->createHotel(
            $request->user(),
            CreateHotelDTO::fromArray($request->validated()),
        );

        return $this->created('Hotel criado com sucesso.', new HotelResource($hotel));
    }

    /**
     * GET /hotels/{slug}
     * Public hotel profile.
     * Requirements: 3.2
     */
    public function show(Request $request, Hotel $hotel): JsonResponse
    {
        // Auth::guard('sanctum')->id() resolves the user ID from the Bearer token
        // even without the auth:sanctum middleware, so guests get null and
        // authenticated users get is_following correctly.
        $authUserId = Auth::guard('sanctum')->id();

        // Conta visualização de perfil (métrica do dashboard PRO), exceto quando
        // o próprio admin do hotel acessa.
        $viewerIsAdmin = $authUserId && $hotel->hotelUsers()
            ->where('user_id', $authUserId)->where('role', 'admin')->where('status', 'approved')->exists();
        if (! $viewerIsAdmin) {
            $hotel->increment('profile_views');
        }

        $enriched = $this->hotelRepository->findForProfile(
            $hotel->slug,
            $authUserId,
        );

        $resource = ($enriched ?? $hotel)->load('subscriptions');
        $resource->setAttribute('is_viewer_admin', $viewerIsAdmin);

        return $this->success(
            'Hotel carregado com sucesso.',
            new HotelResource($resource),
        );
    }

    /**
     * GET /hotels/directory
     * Lista pública de hotéis (aba "Hotéis" do feed §10). Hotéis PRO Empresa
     * aparecem primeiro (maior visibilidade).
     */
    public function directory(Request $request): JsonResponse
    {
        $paginator = Hotel::query()
            ->with('subscriptions')
            ->withCount(['jobs', 'employees'])
            ->orderByRaw('(
                select count(*) from subscriptions
                where subscriptions.subscribable_type = ?
                  and subscriptions.subscribable_id = hotels.id
                  and subscriptions.plan = ?
                  and subscriptions.status = ?
                  and (subscriptions.expires_at is null or subscriptions.expires_at > ?)
            ) desc', [
                Hotel::class,
                SubscriptionPlan::ProEmpresa->value,
                SubscriptionStatus::Active->value,
                now(),
            ])
            ->orderByDesc('followers_count')
            ->paginate(20);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Hotel $h) => new HotelResource($h))
        );

        return $this->paginated('Hotéis carregados com sucesso.', $paginator);
    }

    /**
     * PUT /hotels/{slug}
     * Update hotel details (HotelAdmin only).
     * Requirements: 3.3, 3.7
     */
    public function update(UpdateHotelRequest $request, Hotel $hotel): JsonResponse
    {
        $this->authorize('update', $hotel);

        $hotel = $this->hotelService->updateHotel(
            $hotel,
            UpdateHotelDTO::fromArray($request->validated()),
        );

        return $this->success('Hotel atualizado com sucesso.', new HotelResource($hotel));
    }

    /**
     * POST /hotels/{slug}/logo
     * Upload hotel logo (HotelAdmin only).
     * Requirements: 3.4, 3.5
     */
    public function uploadLogo(UploadImageRequest $request, Hotel $hotel): JsonResponse
    {
        $this->authorize('uploadMedia', $hotel);

        $hotel = $this->hotelService->updateLogo($hotel, $request->file('image'));

        return $this->success('Logo atualizado com sucesso.', new HotelResource($hotel));
    }

    /**
     * POST /hotels/{slug}/cover
     * Upload hotel cover (HotelAdmin only).
     * Requirements: 3.4, 3.5
     */
    public function uploadCover(UploadImageRequest $request, Hotel $hotel): JsonResponse
    {
        $this->authorize('uploadMedia', $hotel);

        $hotel = $this->hotelService->updateCover($hotel, $request->file('image'));

        return $this->success('Capa atualizada com sucesso.', new HotelResource($hotel));
    }

    /**
     * GET /me/hotels
     * List hotels managed by the authenticated user.
     * Requirements: 3.8
     */
    public function myHotels(Request $request): JsonResponse
    {
        $paginator = $this->hotelRepository->findByAdmin($request->user());

        // Wrap items in HotelResource before passing to paginated()
        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($hotel) => new HotelResource($hotel))
        );

        return $this->paginated('Hotéis carregados com sucesso.', $paginator);
    }

    /**
     * GET /hotels/{hotel}/jobs
     * List active jobs for a specific hotel (paginated).
     */
    public function hotelJobs(Hotel $hotel): JsonResponse
    {
        $paginator = $hotel->jobs()
            ->with('hotel')
            ->where('status', 'active')
            ->latest()
            ->paginate(15);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($job) => new \App\Http\Resources\JobResource($job))
        );

        return $this->paginated('Vagas do hotel carregadas com sucesso.', $paginator);
    }

    /**
     * GET /hotels/{hotel}/posts
     * List posts published in the name of a specific hotel (paginated).
     */
    public function hotelPosts(Hotel $hotel): JsonResponse
    {
        $paginator = $hotel->posts()
            ->with(['user', 'hotel'])
            ->latest()
            ->paginate(15);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($post) => new \App\Http\Resources\PostResource($post))
        );

        return $this->paginated('Publicações do hotel carregadas com sucesso.', $paginator);
    }

    /**
     * GET /hotels/{hotel:slug}/jobs/manage
     * Lista TODAS as vagas do hotel (qualquer status) — só para admins,
     * usado no painel de gestão de vagas.
     */
    public function manageJobs(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $request->user()->isHotelAdminOf($hotel->id)) {
            return $this->forbidden('Você não administra este hotel.');
        }

        $paginator = $hotel->jobs()
            ->with('hotel')
            ->withCount('applications')
            ->latest()
            ->paginate(20);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($job) => new \App\Http\Resources\JobResource($job))
        );

        return $this->paginated('Vagas do hotel carregadas com sucesso.', $paginator);
    }

    /**
     * GET /hotels/{hotel}/admins
     * Returns the admin users of the hotel (basic info).
     */
    public function hotelAdmins(Hotel $hotel): JsonResponse
    {
        $admins = $hotel->admins()->get();

        return $this->success(
            'Admins do hotel carregados.',
            \App\Http\Resources\UserResource::collection($admins),
        );
    }

    /**
     * POST /hotels/{hotel}/follow
     * Follow a hotel. Returns 409 if already following.
     * Requirements: 8.2, 8.3, 8.4
     */
    public function follow(Request $request, Hotel $hotel): JsonResponse
    {
        $this->followerService->follow($hotel, $request->user());
        return $this->success('Hotel seguido com sucesso.');
    }

    /**
     * DELETE /hotels/{hotel}/follow
     * Unfollow a hotel.
     * Requirement: 8.3
     */
    public function unfollow(Request $request, Hotel $hotel): JsonResponse
    {
        $this->followerService->unfollow($hotel, $request->user());
        return $this->success('Hotel deixado de seguir com sucesso.');
    }

    // ──────────────────────────────────────────────────────────
    // Employee management (Requirements: 5.1, 6.4, 6.6, 6.7, 9.5)
    // ──────────────────────────────────────────────────────────

    /**
     * GET /hotels/{hotel}/employees
     * Public list of approved employees.
     * Requirement: 5.1
     */
    public function employees(Hotel $hotel): JsonResponse
    {
        $hotelUsers = $this->employeeService->listApproved($hotel);

        return $this->success('Colaboradores carregados com sucesso.', $this->mapEmployeeResources($hotelUsers, $hotel));
    }

    /**
     * GET /hotels/{hotel}/pending-employees
     * Pending link requests (admin only).
     * Requirements: 6.4, 9.5
     */
    public function pendingEmployees(Request $request, Hotel $hotel): JsonResponse
    {
        $this->authorize('manageEmployees', $hotel);
        $pending = $this->employeeService->listPending($hotel);

        return $this->success('Solicitações pendentes carregadas.', $this->mapEmployeeResources($pending, $hotel));
    }

    /**
     * Shapes a collection of HotelUser records into the Employee resource
     * consumed by both the approved-employees list and the pending-requests list.
     */
    private function mapEmployeeResources(Collection $hotelUsers, Hotel $hotel): Collection
    {
        return $hotelUsers->map(function (HotelUser $hotelUser) use ($hotel) {
            $user = $hotelUser->user;
            // Prefere a experiência em andamento (sem data de término) neste hotel;
            // cai para a mais recente se não houver nenhuma em aberto.
            $experience = $user->experiences()->where('hotel_id', $hotel->id)
                ->orderByRaw('ended_at IS NULL DESC')
                ->latest('started_at')
                ->first();

            return [
                'uuid'       => $user->uuid,
                'user_id'    => $user->id,
                'name'       => $user->name,
                'avatar_url' => $user->avatar_url ?? null,
                'title'      => $experience?->title,
                'started_at' => $experience?->started_at,
                'verified'   => $experience?->verified ?? false,
                'hotel_user' => [
                    'role'       => $hotelUser->role,
                    'status'     => $hotelUser->status,
                    'created_at' => $hotelUser->created_at,
                ],
            ];
        });
    }

    /**
     * PATCH /hotels/{hotel}/employees/{userId}/approve
     * Approve a pending link.
     * Requirements: 6.6, 9.5
     */
    public function approveEmployee(Request $request, Hotel $hotel, int $userId): JsonResponse
    {
        $this->authorize('manageEmployees', $hotel);
        $hotelUser = $hotel->hotelUsers()->where('user_id', $userId)->firstOrFail();
        $this->employeeService->approve($hotelUser);
        return $this->success('Vínculo aprovado com sucesso.');
    }

    /**
     * PATCH /hotels/{hotel}/employees/{userId}/reject
     * Reject a pending link.
     * Requirements: 6.7, 9.5
     */
    public function rejectEmployee(Request $request, Hotel $hotel, int $userId): JsonResponse
    {
        $this->authorize('manageEmployees', $hotel);
        $hotelUser = $hotel->hotelUsers()->where('user_id', $userId)->firstOrFail();
        $this->employeeService->reject($hotelUser);
        return $this->success('Vínculo rejeitado com sucesso.');
    }

    // ──────────────────────────────────────────────────────────
    // Claim & Role management (Requirements: 7.2, 9.6, 9.7, 9.8)
    // ──────────────────────────────────────────────────────────

    /**
     * POST /hotels/{hotel}/claim
     * Submit a claim for an unmanaged hotel.
     * Requirement: 7.2
     */
    public function claim(Request $request, Hotel $hotel): JsonResponse
    {
        $claim = $this->claimService->claim($hotel, $request->user(), $request->input('message'));
        return $this->created('Reivindicação enviada com sucesso.', new \App\Http\Resources\HotelClaimResource($claim));
    }

    /**
     * PATCH /hotels/{hotel}/promote/{userId}
     * Promote an employee to admin.
     * Requirement: 9.6
     */
    public function promote(Request $request, Hotel $hotel, int $userId): JsonResponse
    {
        $this->authorize('manageRoles', $hotel);
        $target = \App\Models\User::findOrFail($userId);
        $this->hotelService->promoteToAdmin($hotel, $target);
        return $this->success('Colaborador promovido a admin com sucesso.');
    }

    /**
     * PATCH /hotels/{hotel}/demote/{userId}
     * Demote an admin to employee (enforces at-least-one-admin invariant).
     * Requirements: 9.7, 9.8
     */
    public function demote(Request $request, Hotel $hotel, int $userId): JsonResponse
    {
        $this->authorize('manageRoles', $hotel);
        $target = \App\Models\User::findOrFail($userId);
        $this->hotelService->demoteToEmployee($hotel, $target);
        return $this->success('Admin rebaixado a colaborador com sucesso.');
    }
}
