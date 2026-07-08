<?php

namespace App\Domain\Feed\DTOs;

/**
 * Data transfer object for creating a post.
 * Requirements: 4.1
 */
class CreatePostDTO
{
    public function __construct(
        public readonly int     $user_id,
        public readonly string  $content,
        public readonly ?int    $hotel_id   = null,
        public readonly ?string $image_path = null,
        public readonly string  $type       = 'post',
        public readonly ?array  $metadata   = null,
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            user_id:    $userId,
            content:    $data['content'],
            hotel_id:   $data['hotel_id']   ?? null,
            image_path: $data['image_path'] ?? null,
            type:       $data['type']       ?? 'post',
            metadata:   $data['metadata']   ?? null,
        );
    }
}
