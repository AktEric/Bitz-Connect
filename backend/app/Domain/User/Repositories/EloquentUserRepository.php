<?php

namespace App\Domain\User\Repositories;

use App\Domain\User\DTOs\UpdateProfileDTO;
use App\Models\User;

/**
 * Eloquent implementation of UserRepositoryInterface.
 * Requirements: 2.1, 2.2
 */
class EloquentUserRepository implements UserRepositoryInterface
{
    public function findByUuid(string $uuid): ?User
    {
        return User::where('uuid', $uuid)->first();
    }

    public function update(User $user, UpdateProfileDTO $dto): User
    {
        $fields = array_filter([
            'name'         => $dto->name,
            'bio'          => $dto->bio,
            'specialty'    => $dto->specialty,
            'city'         => $dto->city,
            'state'        => $dto->state,
            'website'      => $dto->website,
            'social_links' => $dto->social_links,
        ], fn ($v) => $v !== null);

        $user->fill($fields)->save();

        return $user->fresh();
    }

    public function updateAvatar(User $user, string $path): User
    {
        $user->update(['avatar_path' => $path]);

        return $user->fresh();
    }

    public function updateBanner(User $user, string $path): User
    {
        $user->update(['banner_path' => $path]);

        return $user->fresh();
    }
}
