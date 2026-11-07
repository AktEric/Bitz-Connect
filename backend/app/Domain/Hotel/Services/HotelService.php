<?php

namespace App\Domain\Hotel\Services;

use App\Domain\Hotel\DTOs\CreateHotelDTO;
use App\Domain\Hotel\DTOs\UpdateHotelDTO;
use App\Domain\Hotel\Repositories\HotelRepositoryInterface;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Business logic for hotel management.
 * Requirements: 3.1 – 3.8
 */
class HotelService
{
    public function __construct(private readonly HotelRepositoryInterface $hotelRepository) {}

    /**
     * Create a hotel and link the creator as HotelAdmin.
     * Requirements: 3.1, 3.6
     */
    public function createHotel(User $creator, CreateHotelDTO $dto): Hotel
    {
        $slug = $this->generateUniqueSlug($dto->name);
        $uuid = (string) Str::uuid();

        $hotel = $this->hotelRepository->create($dto, $slug, $uuid);

        // Link creator as admin
        $hotel->hotelUsers()->create([
            'user_id' => $creator->id,
            'role'    => 'admin',
        ]);

        return $hotel->load('admins');
    }

    /**
     * Update hotel fields.
     * Requirements: 3.3
     */
    public function updateHotel(Hotel $hotel, UpdateHotelDTO $dto): Hotel
    {
        return $this->hotelRepository->update($hotel, $dto);
    }

    /**
     * Store hotel logo and return updated hotel.
     * Requirements: 3.4, 3.5
     */
    public function updateLogo(Hotel $hotel, UploadedFile $file): Hotel
    {
        $this->deleteOldFile($hotel->logo_path);
        $path = \App\Support\ImageSanitizer::store($file, 'hotels/logos', 'public');

        return $this->hotelRepository->updateLogo($hotel, $path);
    }

    /**
     * Store hotel cover and return updated hotel.
     * Requirements: 3.4, 3.5
     */
    public function updateCover(Hotel $hotel, UploadedFile $file): Hotel
    {
        $this->deleteOldFile($hotel->cover_path);
        $path = \App\Support\ImageSanitizer::store($file, 'hotels/covers', 'public');

        return $this->hotelRepository->updateCover($hotel, $path);
    }

    /**
     * Generate a unique slug from the hotel name.
     * Appends a numeric suffix if slug already exists.
     * Requirements: 3.6
     */
    public function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while ($this->hotelRepository->slugExists($slug)) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    // ──────────────────────────────────────────────────────────
    // Role management & admin invariant
    // ──────────────────────────────────────────────────────────

    /**
     * Checks if the hotel has at least one approved admin (Requirement 9.8).
     */
    public function hasAtLeastOneAdmin(Hotel $hotel): bool
    {
        return $hotel->hotelUsers()->where('role', 'admin')->where('status', 'approved')->exists();
    }

    /**
     * Promotes an approved employee to admin (Requirement 9.6).
     */
    public function promoteToAdmin(Hotel $hotel, User $target): void
    {
        $hotel->hotelUsers()->where('user_id', $target->id)->update(['role' => 'admin']);
    }

    /**
     * Demotes an admin to employee, enforcing the invariant (Requirements 9.7, 9.8).
     * Throws UnprocessableEntityHttpException (HTTP 422) if the hotel would have zero admins.
     */
    public function demoteToEmployee(Hotel $hotel, User $target): void
    {
        $adminCount = $hotel->hotelUsers()->where('role', 'admin')->where('status', 'approved')->count();
        if ($adminCount <= 1) {
            throw new UnprocessableEntityHttpException(
                'O hotel deve manter ao menos um administrador.'
            );
        }
        $hotel->hotelUsers()->where('user_id', $target->id)->update(['role' => 'employee']);
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private function deleteOldFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
