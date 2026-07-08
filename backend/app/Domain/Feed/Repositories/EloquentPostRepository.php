<?php

namespace App\Domain\Feed\Repositories;

use App\Domain\Feed\DTOs\CreateCommentDTO;
use App\Domain\Feed\DTOs\CreatePostDTO;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Str;

/**
 * Eloquent implementation of PostRepositoryInterface.
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8
 */
class EloquentPostRepository implements PostRepositoryInterface
{
    /**
     * Return cursor-paginated feed ordered newest-first.
     * Requirements: 4.2
     */
    public function getFeed(?string $cursor, int $perPage = 15): CursorPaginator
    {
        return Post::with(['user.subscriptions', 'hotel', 'likes'])
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->cursorPaginate(
                perPage: $perPage,
                cursor: $cursor,
            );
    }

    /**
     * Find a post by UUID or throw ModelNotFoundException.
     */
    public function findByUuid(string $uuid): Post
    {
        return Post::where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Create and return a new post.
     * Requirements: 4.1
     */
    public function create(CreatePostDTO $dto): Post
    {
        $post = Post::create([
            'uuid'       => (string) Str::uuid(),
            'user_id'    => $dto->user_id,
            'hotel_id'   => $dto->hotel_id,
            'content'    => $dto->content,
            'image_path' => $dto->image_path,
            'type'       => $dto->type,
            'metadata'   => $dto->metadata,
            'edited'     => false,
        ]);

        return $post->load(['user.subscriptions', 'hotel', 'likes'])
            ->loadCount(['likes', 'comments']);
    }

    /**
     * Update post fields and return the fresh model.
     * Requirements: 4.3
     */
    public function update(Post $post, array $data): Post
    {
        $post->fill($data)->save();

        return $post->fresh(['user', 'hotel', 'likes'])
            ->loadCount(['likes', 'comments']);
    }

    /**
     * Soft-delete the post; related comments are cascade-soft-deleted via model observer or DB cascade.
     * Requirements: 4.4
     */
    public function delete(Post $post): void
    {
        // Soft-delete all comments on this post first (cascade soft delete)
        $post->comments()->each(fn (Comment $c) => $c->delete());

        $post->delete();
    }

    /**
     * Insert a like row and return refreshed post with counts.
     * Requirements: 4.5
     */
    public function addLike(Post $post, int $userId): Post
    {
        $post->likes()->create(['user_id' => $userId]);

        return $post->fresh(['user', 'hotel', 'likes'])
            ->loadCount(['likes', 'comments']);
    }

    /**
     * Remove a like row and return refreshed post with counts.
     * Requirements: 4.6
     */
    public function removeLike(Post $post, int $userId): Post
    {
        $post->likes()->where('user_id', $userId)->delete();

        return $post->fresh(['user', 'hotel', 'likes'])
            ->loadCount(['likes', 'comments']);
    }

    /**
     * Attach a new comment to the post.
     * Requirements: 4.7
     */
    public function addComment(Post $post, CreateCommentDTO $dto): Comment
    {
        $comment = $post->comments()->create([
            'uuid'      => (string) Str::uuid(),
            'user_id'   => $dto->user_id,
            'parent_id' => $dto->parent_id,
            'content'   => $dto->content,
        ]);

        return $comment->load(['user.subscriptions', 'replies.user.subscriptions']);
    }

    /**
     * Soft-delete a comment.
     * Requirements: 4.8
     */
    public function deleteComment(Comment $comment): void
    {
        $comment->delete();
    }
}
