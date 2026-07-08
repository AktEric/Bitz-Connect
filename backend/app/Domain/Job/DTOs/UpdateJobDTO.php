<?php

namespace App\Domain\Job\DTOs;

/**
 * Carries validated job update data between layers.
 * Requirements: 5.3
 */
class UpdateJobDTO
{
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $requirements,
        public readonly ?string $benefits,
        public readonly ?string $contract_type,
        public readonly ?string $modality,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $salary_range,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title:         $data['title']         ?? null,
            description:   $data['description']   ?? null,
            requirements:  $data['requirements']  ?? null,
            benefits:      $data['benefits']      ?? null,
            contract_type: $data['contract_type'] ?? null,
            modality:      $data['modality']      ?? null,
            city:          $data['city']          ?? null,
            state:         $data['state']         ?? null,
            salary_range:  $data['salary_range']  ?? null,
        );
    }
}
