<?php

namespace App\Domain\User\DTOs;

/**
 * Carries validated profile update data between layers.
 * Requirements: 2.2
 */
class UpdateProfileDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $bio,
        public readonly ?string $specialty,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $website,
        public readonly ?array  $social_links,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:         $data['name']         ?? null,
            bio:          $data['bio']           ?? null,
            specialty:    $data['specialty']     ?? null,
            city:         $data['city']          ?? null,
            state:        $data['state']         ?? null,
            website:      $data['website']       ?? null,
            social_links: $data['social_links']  ?? null,
        );
    }
}
