<?php

namespace App\Domain\Feed\Repositories;

use App\Domain\Feed\DTOs\CreateCommentDTO;
use App\Domain\Feed\DTOs\CreatePostDTO;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Pagination\CursorPaginator;

/**
 * Contract for post/feed data access.
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8
 */
interface PostRepositoryInterface
{
    /**
     * Paginate the feed ordered by created_at DESC using cursor-based pagination.
     * Requirements: 4.2
     */
    public function getFeed(?string $cursor, int $perPage = 15): CursorPaginator;

    /**
     * Find a post by its UUID.
     */
    public function findByUuid(string $uuid): Post;

    /**
     * Persist a new post from the given DTO.
     * Requirements: 4.1
     */
    public function create(CreatePostDTO $dto): Post;

    /**
     * Update post fields.
     * Requirements: 4.3
     */
    public function update(Post $post, array $data): Post;

    /**
     * Soft-delete the post (cascades to comments).
     * Requirements: 4.4
     */
    public function delete(Post $post): void;

    /**
     * Record a like for the given user.
     * Requirements: 4.5
     */
    public function addLike(Post $post, int $userId): Post;

    /**
     * Remove a like for the given user.
     * Requirements: 4.6
     */
    public function removeLike(Post $post, int $userId): Post;

    /**
     * Add a comment to the post.
     * Requirements: 4.7
     */
    public function addComment(Post $post, CreateCommentDTO $dto): Comment;

    /**
     * Soft-delete a comment.
     * Requirements: 4.8
     */
    public function deleteComment(Comment $comment): void;
}
