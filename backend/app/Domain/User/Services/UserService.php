<?php

namespace App\Domain\User\Services;

use App\Domain\User\DTOs\UpdateProfileDTO;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for user profile management.
 * Requirements: 2.1–2.6
 */
class UserService
{
    public function __construct(private readonly UserRepositoryInterface $userRepository) {}

    /**
     * Update authenticated user profile fields.
     * Requirements: 2.2
     */
    public function updateProfile(User $user, UpdateProfileDTO $dto): User
    {
        return $this->userRepository->update($user, $dto);
    }

    /**
     * Store avatar and return updated user.
     * Requirements: 2.3, 2.4
     */
    public function updateAvatar(User $user, UploadedFile $file): User
    {
        $this->deleteOldFile($user->avatar_path);
        $path = \App\Support\ImageSanitizer::store($file, 'avatars', 'public');

        return $this->userRepository->updateAvatar($user, $path);
    }

    /**
     * Store banner and return updated user.
     * Requirements: 2.3, 2.4
     */
    public function updateBanner(User $user, UploadedFile $file): User
    {
        $this->deleteOldFile($user->banner_path);
        $path = \App\Support\ImageSanitizer::store($file, 'banners', 'public');

        return $this->userRepository->updateBanner($user, $path);
    }

    /**
     * Add a professional experience to the user's profile.
     * Requirements: 2.5
     */
    public function addExperience(User $user, array $data): Experience
    {
        return $user->experiences()->create([
            'uuid'        => (string) Str::uuid(),
            'title'       => $data['title'],
            'company'     => $data['company'],
            'started_at'  => $data['started_at'],
            'ended_at'    => $data['ended_at'] ?? null,
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Update an existing experience.
     * Requirements: 2.5
     */
    public function updateExperience(User $user, int $id, array $data): Experience
    {
        $experience = $user->experiences()->findOrFail($id);

        $experience->update(array_filter([
            'title'       => $data['title']       ?? null,
            'company'     => $data['company']      ?? null,
            'started_at'  => $data['started_at']   ?? null,
            'ended_at'    => $data['ended_at']     ?? null,
            'description' => $data['description']  ?? null,
        ], fn ($v) => $v !== null));

        return $experience->fresh();
    }

    /**
     * Remove an experience from the user's profile.
     * Requirements: 2.6
     */
    public function deleteExperience(User $user, int $id): void
    {
        $experience = $user->experiences()->findOrFail($id);
        $experience->delete();
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
