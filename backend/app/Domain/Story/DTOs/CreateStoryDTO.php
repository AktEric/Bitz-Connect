<?php

namespace App\Domain\Story\DTOs;

/**
 * Carrega os dados validados de criação de story entre as camadas.
 * Sprint: monetization-hotelconnect-pro (Stories)
 */
class CreateStoryDTO
{
    public function __construct(
        public readonly string  $story_type,
        public readonly ?int    $job_id,
        public readonly ?int    $mentorship_id,
        public readonly ?string $caption,
        public readonly ?string $image_path,
    ) {}

    public static function fromArray(array $data, ?int $jobId, ?int $mentorshipId, ?string $imagePath): self
    {
        return new self(
            story_type:    $data['story_type'],
            job_id:        $jobId,
            mentorship_id: $mentorshipId,
            caption:       $data['caption'] ?? null,
            image_path:    $imagePath,
        );
    }
}
