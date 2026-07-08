<?php

namespace App\Domain\Hotel\DTOs;

/**
 * Carries data for a hotel claim request.
 * Requirements: 7.2
 */
class ClaimHotelDTO
{
    public function __construct(
        public readonly ?string $message,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(message: $data['message'] ?? null);
    }
}
