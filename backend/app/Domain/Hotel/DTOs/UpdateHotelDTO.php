<?php

namespace App\Domain\Hotel\DTOs;

/**
 * Carries validated hotel update data between layers.
 * Requirements: 3.3, 3.5, 2.6
 */
class UpdateHotelDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $country,
        public readonly ?string $website,
        public readonly ?string $phone,
        public readonly ?string $contact_email,
        public readonly ?string $segment,
        public readonly ?string $cnpj,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:          $data['name']          ?? null,
            description:   $data['description']   ?? null,
            city:          $data['city']          ?? null,
            state:         $data['state']         ?? null,
            country:       $data['country']       ?? null,
            website:       $data['website']       ?? null,
            phone:         $data['phone']         ?? null,
            contact_email: $data['contact_email'] ?? null,
            segment:       $data['segment']       ?? null,
            cnpj:          $data['cnpj']          ?? null,
        );
    }
}
