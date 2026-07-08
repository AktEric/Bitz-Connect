<?php

namespace App\Domain\Video\DTOs;

/**
 * Carrega os dados validados de criação de vídeo entre as camadas.
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
class CreateVideoDTO
{
    public function __construct(
        public readonly int     $user_id,
        public readonly string  $title,
        public readonly ?string $description,
        public readonly string  $video_path,
        public readonly ?string $thumbnail_path,
        public readonly ?int    $duration_seconds,
        public readonly ?string $category,
        /** @var array<int, string>|null */
        public readonly ?array  $tags,
    ) {}

    public static function fromArray(array $data, int $userId, string $videoPath, ?string $thumbnailPath): self
    {
        return new self(
            user_id:          $userId,
            title:            $data['title'],
            description:      $data['description'] ?? null,
            video_path:       $videoPath,
            thumbnail_path:   $thumbnailPath,
            duration_seconds: isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
            category:         $data['category'] ?? null,
            tags:             $data['tags'] ?? null,
        );
    }
}
