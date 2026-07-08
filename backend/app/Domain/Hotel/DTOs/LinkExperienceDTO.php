<?php

namespace App\Domain\Hotel\DTOs;

/**
 * Carries data for linking an experience to a hotel.
 * Requirements: 6.3
 */
class LinkExperienceDTO
{
    public function __construct(
        public readonly int $experienceId,
        public readonly int $hotelId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            experienceId: (int) $data['experience_id'],
            hotelId:      (int) $data['hotel_id'],
        );
    }
}
