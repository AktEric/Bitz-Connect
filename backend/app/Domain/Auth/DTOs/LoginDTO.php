<?php

namespace App\Domain\Auth\DTOs;

class LoginDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $code = null,
        public readonly ?string $recoveryCode = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
            code: $data['code'] ?? null,
            recoveryCode: $data['recovery_code'] ?? null,
        );
    }
}
