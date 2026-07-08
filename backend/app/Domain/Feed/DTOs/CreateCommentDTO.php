<?php

namespace App\Domain\Feed\DTOs;

/**
 * Data transfer object for creating a comment.
 * Requirements: 4.7
 */
class CreateCommentDTO
{
    public function __construct(
        public readonly int    $user_id,
        public readonly int    $post_id,
        public readonly string $content,
        public readonly ?int   $parent_id = null,
    ) {}

    public static function fromArray(array $data, int $userId, int $postId): self
    {
        return new self(
            user_id:   $userId,
            post_id:   $postId,
            content:   $data['content'],
            parent_id: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
        );
    }
}
