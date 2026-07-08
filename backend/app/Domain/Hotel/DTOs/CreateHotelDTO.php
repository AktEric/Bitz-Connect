<?php

namespace App\Domain\Hotel\DTOs;

/**
 * Carries validated hotel creation data between layers.
 * Requirements: 3.1, 3.2, 3.5, 2.6
 */
class CreateHotelDTO
{
    public function __construct(
        public readonly string  $name,
        public readonly string  $description,
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
            name:          $data['name'],
            description:   $data['description'],
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
