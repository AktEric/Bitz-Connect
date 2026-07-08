<?php

namespace App\Domain\Job\DTOs;

/**
 * Carries validated job application data between layers.
 * Requirements: 5.5
 */
class ApplyJobDTO
{
    public function __construct(
        public readonly int     $job_id,
        public readonly int     $user_id,
        public readonly ?string $cover_letter,
    ) {}

    public static function fromArray(array $data, int $jobId, int $userId): self
    {
        return new self(
            job_id:       $jobId,
            user_id:      $userId,
            cover_letter: $data['cover_letter'] ?? null,
        );
    }
}
