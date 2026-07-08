<?php

namespace App\Domain\Course\DTOs;

/**
 * Dados validados de criação/edição de curso.
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
class CreateCourseDTO
{
    public function __construct(
        public readonly int     $user_id,
        public readonly string  $title,
        public readonly ?string $description,
        public readonly ?string $category,
        public readonly ?string $level,
        public readonly int     $price_cents,
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            user_id:     $userId,
            title:       $data['title'],
            description: $data['description'] ?? null,
            category:    $data['category'] ?? null,
            level:       $data['level'] ?? null,
            price_cents: isset($data['price_cents']) ? (int) $data['price_cents'] : 0,
        );
    }
}
