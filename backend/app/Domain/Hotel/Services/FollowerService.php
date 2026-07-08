<?php

namespace App\Domain\Hotel\Services;

use App\Models\Hotel;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Handles hotel follow/unfollow operations.
 * Requirements: 8.2, 8.3, 8.4, 8.5, 12.1
 */
class FollowerService
{
    /**
     * Follows hotel: inserts into hotel_followers and increments counter.
     * Requirements: 8.2, 8.4
     */
    public function follow(Hotel $hotel, User $user): void
    {
        if ($hotel->hotelFollowers()->where('user_id', $user->id)->exists()) {
            throw new ConflictHttpException('Você já segue este hotel.');
        }
        $hotel->hotelFollowers()->create(['user_id' => $user->id]);
        $hotel->increment('followers_count');
    }

    /**
     * Unfollows hotel: removes from hotel_followers and decrements counter.
     * Requirement: 8.3
     */
    public function unfollow(Hotel $hotel, User $user): void
    {
        $hotel->hotelFollowers()->where('user_id', $user->id)->delete();
        $hotel->decrement('followers_count');
    }
}
