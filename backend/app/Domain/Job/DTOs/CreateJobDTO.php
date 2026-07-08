<?php

namespace App\Domain\Job\DTOs;

/**
 * Carries validated job creation data between layers.
 * Requirements: 5.1, 5.2
 */
class CreateJobDTO
{
    public function __construct(
        public readonly string  $hotel_uuid,
        public readonly string  $title,
        public readonly string  $description,
        public readonly ?string $requirements,
        public readonly ?string $benefits,
        public readonly string  $contract_type,
        public readonly string  $modality,
        public readonly string  $city,
        public readonly string  $state,
        public readonly ?string $salary_range,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            hotel_uuid:    $data['hotel_uuid'],
            title:         $data['title'],
            description:   $data['description'],
            requirements:  $data['requirements']  ?? null,
            benefits:      $data['benefits']      ?? null,
            contract_type: $data['contract_type'],
            modality:      $data['modality'],
            city:          $data['city'],
            state:         $data['state'],
            salary_range:  $data['salary_range']  ?? null,
        );
    }
}
