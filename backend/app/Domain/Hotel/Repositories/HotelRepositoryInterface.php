<?php

namespace App\Domain\Hotel\Repositories;

use App\Domain\Hotel\DTOs\CreateHotelDTO;
use App\Domain\Hotel\DTOs\UpdateHotelDTO;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Contract for hotel data access.
 * Requirements: 3.1, 3.2, 3.3, 3.8, 4.9, 8.6, 12.5
 */
interface HotelRepositoryInterface
{
    public function findBySlug(string $slug): ?Hotel;

    public function create(CreateHotelDTO $dto, string $slug, string $uuid): Hotel;

    public function update(Hotel $hotel, UpdateHotelDTO $dto): Hotel;

    public function updateLogo(Hotel $hotel, string $path): Hotel;

    public function updateCover(Hotel $hotel, string $path): Hotel;

    /** List hotels where the given user is an admin. */
    public function findByAdmin(User $user): LengthAwarePaginator;

    /** Check if the given slug already exists. */
    public function slugExists(string $slug): bool;

    /** Searches hotels where the user is admin or approved employee. */
    public function findByMember(User $user): LengthAwarePaginator;

    /** Loads hotel with optimized counts for the public profile. */
    public function findForProfile(string $slug, ?int $authUserId = null): ?Hotel;

    /** Checks if user is following the hotel. */
    public function isFollowing(Hotel $hotel, User $user): bool;
}
