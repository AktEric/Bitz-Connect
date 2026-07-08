<?php

namespace App\Policies;

use App\Models\HotelClaim;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Authorization rules for HotelClaim resources.
 * Only platform_admins can review (approve/reject) claims.
 * Requirements: 7.3
 */
class HotelClaimPolicy
{
    use HandlesAuthorization;

    /**
     * Only platform_admins can approve/reject claims.
     * Requirements: 7.3
     */
    public function review(User $user, HotelClaim $claim): bool
    {
        return $user->role === 'platform_admin';
    }
}
