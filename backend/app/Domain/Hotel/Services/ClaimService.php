<?php

namespace App\Domain\Hotel\Services;

use App\Models\Hotel;
use App\Models\HotelClaim;
use App\Models\HotelUser;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Handles the hotel claim workflow.
 * Requirements: 7.2, 7.3, 7.4, 7.5, 7.6, 12.1
 */
class ClaimService
{
    /**
     * Creates a pending claim request.
     * Requirements: 7.2, 7.6
     */
    public function claim(Hotel $hotel, User $user, ?string $message = null): HotelClaim
    {
        $exists = HotelClaim::where('hotel_id', $hotel->id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            throw new ConflictHttpException('Você já possui uma solicitação pendente para este hotel.');
        }

        $claim = HotelClaim::create([
            'hotel_id' => $hotel->id,
            'user_id'  => $user->id,
            'status'   => 'pending',
            'message'  => $message,
        ]);

        // TODO: notify platform_admins (Requirement 7.3)

        return $claim;
    }

    /**
     * Approves a claim and creates an approved admin hotel_users record.
     * Requirement: 7.4
     */
    public function approve(HotelClaim $claim, User $reviewer): void
    {
        $claim->update([
            'status'      => 'approved',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);

        HotelUser::create([
            'hotel_id' => $claim->hotel_id,
            'user_id'  => $claim->user_id,
            'role'     => 'admin',
            'status'   => 'approved',
        ]);

        // TODO: notify the claimant (Requirement 7.5)
    }

    /**
     * Rejects a claim.
     * Requirement: 7.5
     */
    public function reject(HotelClaim $claim, User $reviewer): void
    {
        $claim->update([
            'status'      => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);

        // TODO: notify the claimant
    }
}
