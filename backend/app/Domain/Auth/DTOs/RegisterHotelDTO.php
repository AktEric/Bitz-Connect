<?php

namespace App\Domain\Auth\DTOs;

class RegisterHotelDTO
{
    public function __construct(
        public readonly string  $name,
        public readonly string  $email,
        public readonly string  $password,
        public readonly string  $hotel_name,
        public readonly string  $cnpj,
        public readonly string  $hotel_email,
        public readonly string  $hotel_password,
        public readonly ?string $city,
        public readonly ?string $website,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            hotel_name: $data['hotel_name'],
            cnpj: $data['cnpj'],
            hotel_email: $data['hotel_email'],
            hotel_password: $data['hotel_password'],
            city: $data['city'] ?? null,
            website: $data['website'] ?? null,
        );
    }
}
