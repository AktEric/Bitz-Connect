<?php

namespace App\Domain\Review\DTOs;

/**
 * Dados validados de criação/atualização de avaliação.
 * Sprint: monetization-hotelconnect-pro (Avaliações)
 */
class CreateReviewDTO
{
    public function __construct(
        public readonly int     $rating,
        public readonly ?string $comment,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            rating:  (int) $data['rating'],
            comment: $data['comment'] ?? null,
        );
    }
}
