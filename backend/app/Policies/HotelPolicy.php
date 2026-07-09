<?php

namespace App\Policies;

use App\Models\Hotel;
use App\Models\User;

/**
 * Authorization rules for Hotel resources.
 * Only HotelAdmin (or PlatformAdmin) can modify a hotel.
 * Requirements: 3.7, 9.1, 9.3, 9.5, 9.6, 9.7, 12.3
 */
class HotelPolicy
{
    /**
     * Platform admins bypass all policy checks.
     */
    public function before(User $user): ?bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Any authenticated user can view a hotel.
     */
    public function view(User $user, Hotel $hotel): bool
    {
        return true;
    }

    /**
     * Hotels are provisioned externally by the platform team (login + CNPJ
     * handed over directly) — regular users request a relation to an
     * existing hotel instead of self-creating one. Only platform admins
     * (via before()) may create a hotel.
     * Requirements: 3.1
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Only approved admins can edit the profile.
     * Requirements: 9.1
     */
    public function update(User $user, Hotel $hotel): bool
    {
        return $this->isAdmin($user, $hotel);
    }

    /**
     * Only admins can upload logo/cover media.
     * Requirements: 3.4, 9.1
     */
    public function uploadMedia(User $user, Hotel $hotel): bool
    {
        return $this->isAdmin($user, $hotel);
    }

    /**
     * Only admins can create/delete jobs.
     * Requirements: 9.3
     */
    public function manageJobs(User $user, Hotel $hotel): bool
    {
        return $this->isAdmin($user, $hotel);
    }

    /**
     * Only admins can approve/reject employee links.
     * Requirements: 9.5
     */
    public function manageEmployees(User $user, Hotel $hotel): bool
    {
        return $this->isAdmin($user, $hotel);
    }

    /**
     * Only admins can promote/demote roles.
     * Requirements: 9.6, 9.7
     */
    public function manageRoles(User $user, Hotel $hotel): bool
    {
        return $this->isAdmin($user, $hotel);
    }

    /**
     * Checks if the user is an approved admin of the given hotel.
     */
    private function isAdmin(User $user, Hotel $hotel): bool
    {
        return $hotel->hotelUsers()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->where('status', 'approved')
            ->exists();
    }
}
