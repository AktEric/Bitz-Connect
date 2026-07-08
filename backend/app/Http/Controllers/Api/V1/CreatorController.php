<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\User\Services\CreatorService;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Página pública do criador e seguir/deixar de seguir.
 * Sprint: monetization-hotelconnect-pro (Página do Criador)
 */
class CreatorController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CreatorService $creatorService,
    ) {}

    /**
     * GET /creators/{user}
     * Perfil do criador (dados + especialidade + stats + is_following).
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $user->load('subscriptions')->loadCount(['videos', 'reviews'])->loadAvg('reviews', 'rating');

        // Página pública: resolve o visitante via guard sanctum (token opcional).
        $viewer = Auth::guard('sanctum')->user();

        $resource = new UserResource($user);
        $resource->additional = [
            'is_following' => $this->creatorService->isFollowing($user, $viewer),
        ];

        return $this->success('Criador carregado com sucesso.', $resource);
    }

    /**
     * POST /users/{user}/follow
     */
    public function follow(Request $request, User $user): JsonResponse
    {
        $this->creatorService->follow($user, $request->user());

        return $this->success('Você começou a seguir este criador.', [
            'is_following'    => true,
            'followers_count' => (int) $user->fresh()->followers_count,
        ]);
    }

    /**
     * DELETE /users/{user}/follow
     */
    public function unfollow(Request $request, User $user): JsonResponse
    {
        $this->creatorService->unfollow($user, $request->user());

        return $this->success('Você deixou de seguir este criador.', [
            'is_following'    => false,
            'followers_count' => (int) $user->fresh()->followers_count,
        ]);
    }
}
