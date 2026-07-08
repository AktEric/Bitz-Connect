<?php

namespace App\Domain\Feed\Services;

use App\Domain\Feed\DTOs\CreateCommentDTO;
use App\Domain\Feed\DTOs\CreatePostDTO;
use App\Domain\Feed\Repositories\PostRepositoryInterface;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Str;

/**
 * Business logic for the feed domain.
 * Requirements: 4.1 – 4.8
 */
class FeedService
{
    public function __construct(
        private readonly PostRepositoryInterface $postRepository,
    ) {}

    /**
     * Return cursor-paginated feed.
     * Requirements: 4.2
     */
    public function getFeed(?string $cursor): CursorPaginator
    {
        return $this->postRepository->getFeed($cursor);
    }

    /**
     * Create a new post from the given DTO.
     * Requirements: 4.1
     */
    public function createPost(CreatePostDTO $dto): Post
    {
        return $this->postRepository->create($dto);
    }

    /**
     * Update the post content and mark it as edited.
     * Requirements: 4.3
     */
    public function updatePost(Post $post, string $content): Post
    {
        return $this->postRepository->update($post, [
            'content' => $content,
            'edited'  => true,
        ]);
    }

    /**
     * Soft-delete the post and cascade to its comments.
     * Requirements: 4.4
     */
    public function deletePost(Post $post): void
    {
        $this->postRepository->delete($post);
    }

    /**
     * Like the post; throws QueryException (caught upstream as 409) on duplicate.
     * Requirements: 4.5
     */
    public function likePost(Post $post, int $userId): Post
    {
        return $this->postRepository->addLike($post, $userId);
    }

    /**
     * Remove a like from the post.
     * Requirements: 4.6
     */
    public function unlikePost(Post $post, int $userId): Post
    {
        return $this->postRepository->removeLike($post, $userId);
    }

    /**
     * Add a comment to the post.
     * Requirements: 4.7
     */
    public function addComment(Post $post, CreateCommentDTO $dto): Comment
    {
        return $this->postRepository->addComment($post, $dto);
    }

    /**
     * Soft-delete a comment.
     * Requirements: 4.8
     */
    public function deleteComment(Comment $comment): void
    {
        $this->postRepository->deleteComment($comment);
    }

    /**
     * Create an automatic feed post when a user shares a job in chat.
     * Requirements: 7.2, 7.5
     */
    public function createJobSharePost(User $author, array $jobData): Post
    {
        return Post::create([
            'uuid'     => (string) Str::uuid(),
            'user_id'  => $author->id,
            'content'  => "Compartilhei uma vaga: {$jobData['title']} — {$jobData['hotel_name']}",
            'type'     => 'job_share',
            'metadata' => $jobData,
        ]);
    }
}
