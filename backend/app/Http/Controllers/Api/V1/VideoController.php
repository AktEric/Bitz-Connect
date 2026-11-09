<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Video\DTOs\CreateVideoDTO;
use App\Domain\Video\Services\VideoInteractionService;
use App\Domain\Video\Services\VideoService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Video\CreateVideoRequest;
use App\Http\Resources\VideoCommentResource;
use App\Http\Resources\VideoResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints do feed de vídeos e upload.
 * Upload é restrito a Consultores PRO (entitlement publish_videos).
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
class VideoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly VideoService $videoService,
        private readonly VideoInteractionService $interactionService,
    ) {}

    /**
     * GET /videos
     * Feed público de vídeos (paginado), com filtro opcional por categoria.
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->videoService->feed(12, $request->query('category'));

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Video $v) => new VideoResource($v))
        );

        return $this->paginated('Vídeos carregados com sucesso.', $paginator);
    }

    /**
     * GET /videos/{video}
     * Detalhe de um vídeo.
     */
    public function show(Request $request, Video $video): JsonResponse
    {
        $video->load('user.subscriptions');
        if ($userId = $request->user()?->id) {
            $video->loadExists(['likes as liked_by_me' => fn ($q) => $q->where('user_id', $userId)]);
        }

        return $this->success('Vídeo carregado com sucesso.', new VideoResource($video));
    }

    /**
     * POST /videos
     * Publica um vídeo. Exclusivo para Consultores PRO (Req §5).
     */
    public function store(CreateVideoRequest $request): JsonResponse
    {
        if ($request->user()->cannot('publish_videos')) {
            return $this->forbidden('A publicação de vídeos é exclusiva do Bitz Connect PRO - Consultor.');
        }

        $videoPath     = $request->file('video')->store('videos', 'public');
        $thumbnailPath = $request->hasFile('thumbnail')
            ? \App\Support\ImageSanitizer::store($request->file('thumbnail'), 'video-thumbnails', 'public')
            : null;

        $dto = CreateVideoDTO::fromArray(
            $request->validated(),
            $request->user()->id,
            $videoPath,
            $thumbnailPath,
        );

        $video = $this->videoService->create($dto);

        return $this->created('Vídeo publicado com sucesso.', new VideoResource($video->load('user.subscriptions')));
    }

    /**
     * POST /videos/{video}/view
     * Registra uma visualização (disparado pelo player ao dar play).
     */
    public function registerView(Video $video): JsonResponse
    {
        $this->videoService->registerView($video);

        return $this->success('Visualização registrada.');
    }

    /**
     * GET /users/{user}/videos
     * Vídeos publicados por um criador.
     */
    public function creatorVideos(User $user): JsonResponse
    {
        $paginator = $this->videoService->byCreator($user, 12);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Video $v) => new VideoResource($v))
        );

        return $this->paginated('Vídeos do criador carregados com sucesso.', $paginator);
    }

    // ──────────────────────────────────────────────────────────
    // Interações
    // ──────────────────────────────────────────────────────────

    /**
     * POST /videos/{video}/like
     * Alterna a curtida (idempotente).
     */
    public function toggleLike(Request $request, Video $video): JsonResponse
    {
        $liked = $this->interactionService->toggleLike($video, $request->user());

        return $this->success($liked ? 'Vídeo curtido.' : 'Curtida removida.', [
            'liked'       => $liked,
            'likes_count' => (int) $video->fresh()->likes_count,
        ]);
    }

    /**
     * GET /videos/{video}/comments
     * Lista comentários (paginado).
     */
    public function comments(Video $video): JsonResponse
    {
        $paginator = $this->interactionService->listComments($video);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (VideoComment $c) => new VideoCommentResource($c))
        );

        return $this->paginated('Comentários carregados com sucesso.', $paginator);
    }

    /**
     * POST /videos/{video}/comments
     * Adiciona um comentário.
     */
    public function addComment(Request $request, Video $video): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ]);

        $comment = $this->interactionService->addComment($video, $request->user(), $data['content']);

        return $this->created('Comentário adicionado.', new VideoCommentResource($comment));
    }

    /**
     * DELETE /videos/{video}/comments/{comment}
     * Remove um comentário (autor do comentário ou dono do vídeo).
     */
    public function deleteComment(Request $request, Video $video, VideoComment $comment): JsonResponse
    {
        if ($comment->video_id !== $video->id) {
            return $this->notFound('Comentário não encontrado.');
        }

        $userId = $request->user()->id;
        if ($comment->user_id !== $userId && $video->user_id !== $userId) {
            return $this->forbidden('Você não pode remover este comentário.');
        }

        $this->interactionService->deleteComment($comment);

        return $this->success('Comentário removido.');
    }

    /**
     * POST /videos/{video}/share
     * Registra um compartilhamento.
     */
    public function share(Video $video): JsonResponse
    {
        $this->interactionService->registerShare($video);

        return $this->success('Compartilhamento registrado.', [
            'shares_count' => (int) $video->fresh()->shares_count,
        ]);
    }
}
