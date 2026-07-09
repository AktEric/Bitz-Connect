<?php

namespace App\Domain\Hotel\Repositories;

use App\Domain\Hotel\DTOs\CreateHotelDTO;
use App\Domain\Hotel\DTOs\UpdateHotelDTO;
use App\Models\Hotel;
use App\Models\HotelFollower;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Eloquent implementation of HotelRepositoryInterface.
 * Requirements: 3.1, 3.2, 3.3, 3.8, 4.9, 8.6, 12.5
 */
class EloquentHotelRepository implements HotelRepositoryInterface
{
    public function findBySlug(string $slug): ?Hotel
    {
        return Hotel::where('slug', $slug)->first();
    }

    public function create(CreateHotelDTO $dto, string $slug, string $uuid): Hotel
    {
        return Hotel::create([
            'uuid'          => $uuid,
            'name'          => $dto->name,
            'slug'          => $slug,
            'description'   => $dto->description,
            'city'          => $dto->city,
            'state'         => $dto->state,
            'country'       => $dto->country,
            'website'       => $dto->website,
            'phone'         => $dto->phone,
            'contact_email' => $dto->contact_email,
            'segment'       => $dto->segment,
        ]);
    }

    public function update(Hotel $hotel, UpdateHotelDTO $dto): Hotel
    {
        $fields = array_filter([
            'name'          => $dto->name,
            'description'   => $dto->description,
            'city'          => $dto->city,
            'state'         => $dto->state,
            'country'       => $dto->country,
            'website'       => $dto->website,
            'phone'         => $dto->phone,
            'contact_email' => $dto->contact_email,
            'segment'       => $dto->segment,
        ], fn ($v) => $v !== null);

        $hotel->fill($fields)->save();

        return $hotel->fresh();
    }

    public function updateLogo(Hotel $hotel, string $path): Hotel
    {
        $hotel->update(['logo_path' => $path]);

        return $hotel->fresh();
    }

    public function updateCover(Hotel $hotel, string $path): Hotel
    {
        $hotel->update(['cover_path' => $path]);

        return $hotel->fresh();
    }

    public function findByAdmin(User $user): LengthAwarePaginator
    {
        return Hotel::whereHas('hotelUsers', fn ($q) =>
            $q->where('user_id', $user->id)->where('role', 'admin')
        )->with('subscriptions')->paginate(15);
    }

    public function slugExists(string $slug): bool
    {
        return Hotel::where('slug', $slug)->exists();
    }

    public function findByMember(User $user): LengthAwarePaginator
    {
        return Hotel::whereHas('hotelUsers', fn ($q) =>
            $q->where('user_id', $user->id)
              ->where('status', 'approved')
              ->whereIn('role', ['admin', 'employee'])
        )->paginate(15);
    }

    public function findForProfile(string $slug, ?int $authUserId = null): ?Hotel
    {
        $hotel = Hotel::where('slug', $slug)
            ->with('admins')
            ->withCount([
                // Mesmo critério de App\Domain\Hotel\Services\EmployeeService::listApproved():
                // aprovados (admin ou employee) com experiência em andamento neste hotel.
                'hotelUsers as employees_count' => fn ($q) =>
                    $q->where('status', 'approved')
                        ->whereHas('user.experiences', fn ($eq) =>
                            $eq->whereColumn('experiences.hotel_id', 'hotel_users.hotel_id')->whereNull('ended_at')
                        ),
                'jobs as jobs_count' => fn ($q) =>
                    $q->where('status', 'active'),
            ])
            ->first();

        if (!$hotel) {
            return null;
        }

        $hotel->setAttribute(
            'is_following',
            $authUserId
                ? HotelFollower::where('hotel_id', $hotel->id)
                               ->where('user_id', $authUserId)
                               ->exists()
                : false
        );

        return $hotel;
    }

    public function isFollowing(Hotel $hotel, User $user): bool
    {
        return $hotel->hotelFollowers()->where('user_id', $user->id)->exists();
    }
}
