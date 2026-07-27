<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Story\DTOs\CreateStoryDTO;
use App\Domain\Story\Services\StoryInteractionService;
use App\Domain\Story\Services\StoryService;
use App\Domain\Subscription\Services\EntitlementService;
use App\Enums\Feature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Story\StoryRequest;
use App\Http\Resources\StoryResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Job;
use App\Models\Mentorship;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stories de destaque (vaga ou mentoria). Criação exclusiva de contas PRO
 * (entitlement publish_stories) — hotel dono da vaga ou consultor dono da
 * mentoria. MVP sem expiração.
 * Sprint: monetization-hotelconnect-pro (Stories)
 */
class StoryController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly StoryService $storyService,
        private readonly StoryInteractionService $interactionService,
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * GET /stories
     * Feed de stories ativos, agrupado por dono (hotel ou consultor).
     */
    public function index(): JsonResponse
    {
        $stories = $this->storyService->feed();

        $groups = [];
        foreach ($stories as $story) {
            $owner = $this->ownerFor($story);
            if (! $owner) {
                continue; // story órfão (vaga/mentoria removida) — não deveria acontecer (cascadeOnDelete)
            }

            $key = $owner['type'] . ':' . $owner['uuid'];
            if (! isset($groups[$key])) {
                $groups[$key] = ['owner' => $owner, 'stories' => []];
            }
            $groups[$key]['stories'][] = new StoryResource($story);
        }

        return $this->success('Stories carregados com sucesso.', array_values($groups));
    }

    /**
     * POST /stories
     * Publica um story a partir de uma vaga ou mentoria já existente.
     */
    public function store(StoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if ($data['story_type'] === 'job') {
            $job = Job::where('uuid', $data['job_uuid'])->firstOrFail();

            if (! $user->isHotelAdminOf($job->hotel_id)) {
                return $this->forbidden('Você não administra o hotel desta vaga.');
            }
            if (! $this->entitlements->allows($job->hotel, Feature::PublishStories)) {
                return $this->forbidden('A publicação de stories é exclusiva do HotelConnect PRO - Empresa.');
            }

            $imagePath = $request->hasFile('image') ? $request->file('image')->store('story-images', 'public') : null;
            $dto = CreateStoryDTO::fromArray($data, $job->id, null, $imagePath);
        } else {
            $mentorship = Mentorship::where('uuid', $data['mentorship_uuid'])->firstOrFail();

            if ($mentorship->user_id !== $user->id) {
                return $this->forbidden('Você não é o autor desta mentoria.');
            }
            if ($user->cannot('publish_stories')) {
                return $this->forbidden('A publicação de stories é exclusiva do HotelConnect PRO - Consultor.');
            }

            $imagePath = $request->hasFile('image') ? $request->file('image')->store('story-images', 'public') : null;
            $dto = CreateStoryDTO::fromArray($data, null, $mentorship->id, $imagePath);
        }

        $story = $this->storyService->create($dto);
        $story->load(['job.hotel.subscriptions', 'mentorship.user.subscriptions']);

        return $this->created('Story publicado com sucesso.', new StoryResource($story));
    }

    /**
     * DELETE /stories/{story}
     * Remove (dono apenas).
     */
    public function destroy(Request $request, Story $story): JsonResponse
    {
        if ($resp = $this->denyIfNotOwner($request, $story)) {
            return $resp;
        }

        $this->storyService->delete($story);

        return $this->success('Story removido com sucesso.');
    }

    // ──────────────────────────────────────────────────────────
    // Interações
    // ──────────────────────────────────────────────────────────

    /**
     * POST /stories/{story}/view
     * Registra uma visualização (disparado pelo viewer ao exibir o story).
     */
    public function registerView(Story $story): JsonResponse
    {
        $this->storyService->registerView($story);

        return $this->success('Visualização registrada.');
    }

    /**
     * POST /stories/{story}/like
     * Alterna a curtida (idempotente).
     */
    public function toggleLike(Request $request, Story $story): JsonResponse
    {
        $liked = $this->interactionService->toggleLike($story, $request->user());

        return $this->success($liked ? 'Story curtido.' : 'Curtida removida.', [
            'liked'       => $liked,
            'likes_count' => (int) $story->fresh()->likes_count,
        ]);
    }

    /**
     * POST /stories/{story}/share
     */
    public function share(Story $story): JsonResponse
    {
        $this->interactionService->registerShare($story);

        return $this->success('Compartilhamento registrado.', [
            'shares_count' => (int) $story->fresh()->shares_count,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    /** @return array{type: string, uuid: string, slug?: string, name: string, avatar_url: ?string, is_pro: bool}|null */
    private function ownerFor(Story $story): ?array
    {
        if ($story->story_type === 'job' && $story->job && $story->job->hotel) {
            $hotel = $story->job->hotel;

            return [
                'type'       => 'hotel',
                'uuid'       => $hotel->uuid,
                'slug'       => $hotel->slug,
                'name'       => $hotel->name,
                'avatar_url' => $hotel->logo_path ? asset('storage/' . $hotel->logo_path) : null,
                'is_pro'     => $hotel->isPro(),
            ];
        }

        if ($story->story_type === 'mentorship' && $story->mentorship && $story->mentorship->user) {
            $user = $story->mentorship->user;

            return [
                'type'       => 'user',
                'uuid'       => $user->uuid,
                'name'       => $user->name,
                'avatar_url' => $user->avatar_path ? asset('storage/' . $user->avatar_path) : null,
                'is_pro'     => $user->isPro(),
            ];
        }

        return null;
    }

    private function denyIfNotOwner(Request $request, Story $story): ?JsonResponse
    {
        $user = $request->user();

        if ($story->story_type === 'job') {
            $story->loadMissing('job');
            if (! $story->job || ! $user->isHotelAdminOf($story->job->hotel_id)) {
                return $this->forbidden('Você não administra o hotel deste story.');
            }
        } else {
            $story->loadMissing('mentorship');
            if (! $story->mentorship || $story->mentorship->user_id !== $user->id) {
                return $this->forbidden('Você não é o autor deste story.');
            }
        }

        return null;
    }
}
