<?php

namespace App\Domain\Mentorship\DTOs;

/**
 * Dados validados de criação/edição de mentoria.
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
class CreateMentorshipDTO
{
    public function __construct(
        public readonly int     $user_id,
        public readonly string  $title,
        public readonly ?string $description,
        public readonly ?string $category,
        public readonly int     $duration_minutes,
        public readonly string  $session_type,
        public readonly int     $price_cents,
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            user_id:          $userId,
            title:            $data['title'],
            description:      $data['description'] ?? null,
            category:         $data['category'] ?? null,
            duration_minutes: isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : 60,
            session_type:     $data['session_type'] ?? 'online',
            price_cents:      isset($data['price_cents']) ? (int) $data['price_cents'] : 0,
        );
    }
}
