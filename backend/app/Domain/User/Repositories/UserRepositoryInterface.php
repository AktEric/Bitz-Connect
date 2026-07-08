<?php

namespace App\Domain\User\Repositories;

use App\Domain\User\DTOs\UpdateProfileDTO;
use App\Models\User;

/**
 * Contract for user data access.
 * Requirements: 2.1, 2.2
 */
interface UserRepositoryInterface
{
    public function findByUuid(string $uuid): ?User;

    public function update(User $user, UpdateProfileDTO $dto): User;

    public function updateAvatar(User $user, string $path): User;

    public function updateBanner(User $user, string $path): User;
}
