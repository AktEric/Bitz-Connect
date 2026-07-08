<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Hotel\Services\EmployeeService;
use App\Domain\User\DTOs\UpdateProfileDTO;
use App\Domain\User\Services\UserService;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ExperienceRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Requests\User\UploadImageRequest;
use App\Http\Resources\ExperienceResource;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Connection;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles user profile and experience endpoints.
 * Requirements: 2.1–2.6
 */
class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly UserService $userService,
        private readonly EmployeeService $employeeService,
    ) {}

    /**
     * GET /me
     * Returns the authenticated user's profile with experiences.
     * Requirements: 2.1
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('experiences.hotel', 'subscriptions');

        return $this->success('Perfil carregado com sucesso.', new UserResource($user));
    }

    /**
     * PUT /me
     * Update authenticated user's profile.
     * Requirements: 2.2
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->userService->updateProfile(
            $request->user(),
            UpdateProfileDTO::fromArray($request->validated()),
        );

        return $this->success('Perfil atualizado com sucesso.', new UserResource($user->load('experiences.hotel')));
    }

    /**
     * POST /me/avatar
     * Upload profile picture.
     * Requirements: 2.3, 2.4
     */
    public function uploadAvatar(UploadImageRequest $request): JsonResponse
    {
        $user = $this->userService->updateAvatar(
            $request->user(),
            $request->file('image'),
        );

        return $this->success('Foto de perfil atualizada com sucesso.', new UserResource($user));
    }

    /**
     * POST /me/banner
     * Upload banner image.
     * Requirements: 2.3, 2.4
     */
    public function uploadBanner(UploadImageRequest $request): JsonResponse
    {
        $user = $this->userService->updateBanner(
            $request->user(),
            $request->file('image'),
        );

        return $this->success('Banner atualizado com sucesso.', new UserResource($user));
    }

    /**
     * GET /users/{uuid}
     * Public profile of a user.
     * Requirements: 2.1
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $user->load('experiences.hotel', 'subscriptions');
        $resource = new UserResource($user);

        // Resolve connection_status for authenticated callers
        if ($request->user()) {
            $auth = $request->user();
            $resource->additional = ['connection_status' => null];

            if ($auth->id !== $user->id) {
                $connection = Connection::where(function ($q) use ($auth, $user) {
                    $q->where('requester_id', $auth->id)->where('addressee_id', $user->id);
                })->orWhere(function ($q) use ($auth, $user) {
                    $q->where('requester_id', $user->id)->where('addressee_id', $auth->id);
                })->first();

                if ($connection) {
                    if ($connection->status === 'accepted') {
                        $resource->additional = ['connection_status' => 'accepted'];
                    } elseif ($connection->status === 'rejected') {
                        $resource->additional = ['connection_status' => 'rejected'];
                    } elseif ($connection->status === 'pending') {
                        if ($connection->requester_id === $auth->id) {
                            $resource->additional = ['connection_status' => 'pending_sent'];
                        } else {
                            $resource->additional = ['connection_status' => 'pending_received'];
                        }
                    }
                }
            }
        }

        return $this->success('Perfil carregado com sucesso.', $resource);
    }

    // ──────────────────────────────────────────────────────────
    // Experiences
    // ──────────────────────────────────────────────────────────

    /**
     * POST /me/experiences
     * Requirements: 2.5, 4.1
     */
    public function addExperience(ExperienceRequest $request): JsonResponse
    {
        $data       = $request->validated();
        $experience = $this->userService->addExperience($request->user(), $data);

        // Cria vínculo pending se hotel_uuid foi fornecido (Req 4.1, 4.8)
        if (! empty($data['hotel_uuid'])) {
            $hotel     = Hotel::where('uuid', $data['hotel_uuid'])->firstOrFail();
            $hotelUser = $this->employeeService->linkExperience($experience, $hotel);
            $experience->setAttribute('hotel_link_status', $hotelUser->status);
        }

        return $this->created('Experiência adicionada com sucesso.', new ExperienceResource($experience->load('hotel')));
    }

    /**
     * PUT /me/experiences/{id}
     * Requirements: 2.5, 4.1
     */
    public function updateExperience(ExperienceRequest $request, int $id): JsonResponse
    {
        $data       = $request->validated();
        $experience = $this->userService->updateExperience($request->user(), $id, $data);

        // Cria/atualiza vínculo pending se hotel_uuid foi fornecido (Req 4.1, 4.8)
        if (! empty($data['hotel_uuid'])) {
            $hotel     = Hotel::where('uuid', $data['hotel_uuid'])->firstOrFail();
            $hotelUser = $this->employeeService->linkExperience($experience, $hotel);
            $experience->setAttribute('hotel_link_status', $hotelUser->status);
        }

        return $this->success('Experiência atualizada com sucesso.', new ExperienceResource($experience->load('hotel')));
    }

    /**
     * DELETE /me/experiences/{id}
     * Requirements: 2.6
     */
    public function deleteExperience(Request $request, int $id): JsonResponse
    {
        $this->userService->deleteExperience($request->user(), $id);

        return $this->success('Experiência removida com sucesso.');
    }
}
