<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Feed\DTOs\CreateCommentDTO;
use App\Domain\Feed\DTOs\CreatePostDTO;
use App\Domain\Feed\Services\FeedService;
use App\Events\Feed\CommentReplied;
use App\Events\Feed\PostCommented;
use App\Events\Feed\PostLiked;
use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\CreateCommentRequest;
use App\Http\Requests\Feed\CreatePostRequest;
use App\Http\Requests\Feed\UpdatePostRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\PostResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles all feed-related endpoints.
 * Requirements: 4.1 – 4.9
 */
class FeedController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly FeedService $feedService,
    ) {}

    // ──────────────────────────────────────────────────────────
    // Feed
    // ──────────────────────────────────────────────────────────

    /**
     * GET /feed
     * Return cursor-paginated feed ordered by created_at DESC.
     * Requirements: 4.2
     */
    public function getFeed(Request $request): JsonResponse
    {
        $paginator = $this->feedService->getFeed($request->query('cursor'));

        $items = PostResource::collection($paginator->getCollection());

        return response()->json([
            'success' => true,
            'message' => 'Feed carregado com sucesso.',
            'data'    => $items,
            'meta'    => [
                'per_page'      => $paginator->perPage(),
                'next_cursor'   => $paginator->nextCursor()?->encode(),
                'prev_cursor'   => $paginator->previousCursor()?->encode(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // Posts
    // ──────────────────────────────────────────────────────────

    /**
     * POST /posts
     * Create a new post.
     * Requirements: 4.1
     */
    public function createPost(CreatePostRequest $request): JsonResponse
    {
        $hotelId = $request->validated('hotel_id');

        if ($hotelId !== null && ! $request->user()->isHotelAdminOf((int) $hotelId)) {
            return $this->forbidden('Você não é administrador deste hotel.');
        }

        // Save image if provided
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('posts', 'public');
        }

        $validated             = $request->validated();
        $validated['image_path'] = $imagePath;

        $dto  = CreatePostDTO::fromArray($validated, $request->user()->id);
        $post = $this->feedService->createPost($dto);

        return $this->created('Publicação criada com sucesso.', new PostResource($post));
    }

    /**
     * GET /posts/{uuid}
     * Show a single post.
     */
    public function getPost(Post $post): JsonResponse
    {
        $post->loadMissing(['user.subscriptions', 'hotel', 'likes'])->loadCount(['likes', 'comments']);

        return $this->success('Publicação carregada com sucesso.', new PostResource($post));
    }

    /**
     * PUT /posts/{uuid}
     * Update post content (author only).
     * Requirements: 4.3, 4.9
     */
    public function updatePost(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $post = $this->feedService->updatePost($post, $request->validated('content'));

        return $this->success('Publicação atualizada com sucesso.', new PostResource($post));
    }

    /**
     * DELETE /posts/{uuid}
     * Soft-delete a post and cascade to its comments (author only).
     * Requirements: 4.4, 4.9
     */
    public function deletePost(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $this->feedService->deletePost($post);

        return $this->success('Publicação excluída com sucesso.');
    }

    // ──────────────────────────────────────────────────────────
    // Likes
    // ──────────────────────────────────────────────────────────

    /**
     * POST /posts/{uuid}/like
     * Like a post.
     * Requirements: 4.5
     */
    public function likePost(Post $post, Request $request): JsonResponse
    {
        try {
            $post = $this->feedService->likePost($post, $request->user()->id);
        } catch (QueryException $e) {
            // Duplicate unique key — already liked
            return $this->error('Você já curtiu esta publicação.', [], 409);
        }

        // Fire event for notification
        PostLiked::dispatch($post, $request->user());

        return $this->success('Publicação curtida com sucesso.', new PostResource($post));
    }

    /**
     * DELETE /posts/{uuid}/like
     * Unlike a post.
     * Requirements: 4.6
     */
    public function unlikePost(Post $post, Request $request): JsonResponse
    {
        $post = $this->feedService->unlikePost($post, $request->user()->id);

        return $this->success('Curtida removida com sucesso.', new PostResource($post));
    }

    // ──────────────────────────────────────────────────────────
    // Comments
    // ──────────────────────────────────────────────────────────

    /**
     * GET /posts/{uuid}/comments
     * List all comments for a post.
     * Requirements: 4.7
     */
    public function getComments(Post $post): JsonResponse
    {
        // Only top-level comments (parent_id IS NULL), with their replies eager loaded
        $comments = $post->comments()
            ->with(['user.subscriptions', 'replies.user.subscriptions'])
            ->whereNull('parent_id')
            ->latest()
            ->get();

        return $this->success(
            'Comentários carregados com sucesso.',
            CommentResource::collection($comments),
        );
    }

    /**
     * POST /posts/{uuid}/comments
     * Add a comment to a post.
     * Requirements: 4.7
     */
    public function addComment(CreateCommentRequest $request, Post $post): JsonResponse
    {
        $validated = $request->validated();

        // Resolve parent_uuid → parent_id
        $parentComment = null;
        if (!empty($validated['parent_uuid'])) {
            $parentComment = \App\Models\Comment::where('uuid', $validated['parent_uuid'])->first();
            $validated['parent_id'] = $parentComment?->id;
        } else {
            $validated['parent_id'] = null;
        }

        $dto     = CreateCommentDTO::fromArray($validated, $request->user()->id, $post->id);
        $comment = $this->feedService->addComment($post, $dto);

        // Fire events for notifications
        if ($parentComment) {
            // It's a reply — notify the parent comment author
            CommentReplied::dispatch($post, $parentComment, $comment, $request->user());
        } else {
            // Top-level comment — notify the post author
            PostCommented::dispatch($post, $comment, $request->user());
        }

        return $this->created('Comentário criado com sucesso.', new CommentResource($comment));
    }

    /**
     * POST /posts/{uuid}/image
     * Upload image for a post (author only).
     */
    public function uploadPostImage(\App\Http\Requests\Feed\UploadPostImageRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        // Delete old image if any
        if ($post->image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($post->image_path);
        }

        $path = $request->file('image')->store('posts', 'public');
        $post->update(['image_path' => $path]);

        return $this->success('Imagem enviada com sucesso.', new PostResource($post->load(['user.subscriptions', 'hotel'])));
    }

    /**
     * DELETE /posts/{uuid}/comments/{comment}
     * Delete a comment (author only).
     * Requirements: 4.8, 4.9
     */
    public function deleteComment(Post $post, Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $this->feedService->deleteComment($comment);

        return $this->success('Comentário excluído com sucesso.');
    }

    /**
     * GET /users/{user}/posts
     * Public paginated posts from a specific user.
     */
    public function userPosts(Request $request, \App\Models\User $user): JsonResponse
    {
        $posts = \App\Models\Post::where('user_id', $user->id)
            ->with(['user.subscriptions', 'hotel', 'likes'])
            ->withCount(['likes', 'comments'])
            ->latest()
            ->paginate(12);

        $posts->setCollection(
            $posts->getCollection()->map(fn ($p) => new PostResource($p))
        );

        return $this->paginated('Publicações carregadas.', $posts);
    }
}
