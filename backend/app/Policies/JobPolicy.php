<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

/**
 * Authorization rules for Job resources.
 * Only HotelAdmin (or PlatformAdmin) can publish/edit jobs and view applications.
 * Requirements: 5.1, 5.3, 5.4, 5.8
 */
class JobPolicy
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
     * Any authenticated user can view a job.
     */
    public function view(User $user, Job $job): bool
    {
        return true;
    }

    /**
     * Only HotelAdmin can publish a job.
     * Requirements: 5.1
     */
    public function create(User $user): bool
    {
        // At create time we don't have a hotel_id yet; the request validation ensures
        // the user is admin of the supplied hotel_id. Broad gate — controller validates.
        return true;
    }

    /**
     * Only the HotelAdmin of the job's hotel can update it.
     * Requirements: 5.3, 5.4
     */
    public function update(User $user, Job $job): bool
    {
        return $user->isHotelAdminOf($job->hotel_id);
    }

    /**
     * Only HotelAdmin can list applications for a job.
     * Requirements: 5.8
     */
    public function viewApplications(User $user, Job $job): bool
    {
        return $user->isHotelAdminOf($job->hotel_id);
    }
}
