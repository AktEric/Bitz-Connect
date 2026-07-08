<?php

namespace App\Domain\Search\DTOs;

/**
 * Carries a validated search query between layers.
 * Requirements: 6.1, 6.2, 6.3, 6.4
 */
class SearchQueryDTO
{
    public function __construct(
        public readonly string $query,
        public readonly int    $perPage = 15,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            query:   $data['q'],
            perPage: (int) ($data['per_page'] ?? 15),
        );
    }
}
