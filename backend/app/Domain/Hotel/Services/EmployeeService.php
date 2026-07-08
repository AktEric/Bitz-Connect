<?php

namespace App\Domain\Hotel\Services;

use App\Events\Hotel\EmployeeLinkApproved;
use App\Events\Hotel\EmployeeLinkRejected;
use App\Events\Hotel\EmployeeLinkRequested;
use App\Models\Experience;
use App\Models\Hotel;
use App\Models\HotelUser;
use Illuminate\Support\Collection;

/**
 * Handles the full employee-hotel link lifecycle.
 * Requirements: 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 5.1, 12.1
 */
class EmployeeService
{
    /**
     * Links an experience to a hotel and creates a pending hotel_users record.
     * Idempotent: if a link already exists, no duplicate is created and no
     * new notification is fired — the existing status is simply returned.
     * Requirements: 6.3, 6.4, 4.8
     */
    public function linkExperience(Experience $experience, Hotel $hotel): HotelUser
    {
        $experience->update(['hotel_id' => $hotel->id]);

        $hotelUser = HotelUser::where('hotel_id', $hotel->id)
            ->where('user_id', $experience->user_id)
            ->first();

        if ($hotelUser) {
            return $hotelUser;
        }

        $hotelUser = HotelUser::create([
            'hotel_id' => $hotel->id,
            'user_id'  => $experience->user_id,
            'role'     => 'employee',
            'status'   => 'pending',
        ]);

        EmployeeLinkRequested::dispatch($hotelUser);

        return $hotelUser;
    }

    /**
     * Approves a link: updates hotel_users and sets experiences.verified = true.
     * Requirements: 6.6, 6.8
     */
    public function approve(HotelUser $hotelUser): void
    {
        $hotelUser->update(['status' => 'approved']);
        Experience::where('hotel_id', $hotelUser->hotel_id)
            ->where('user_id', $hotelUser->user_id)
            ->update(['verified' => true]);

        EmployeeLinkApproved::dispatch($hotelUser);
    }

    /**
     * Rejects a link.
     * Requirements: 6.7, 6.8
     */
    public function reject(HotelUser $hotelUser): void
    {
        $hotelUser->update(['status' => 'rejected']);

        EmployeeLinkRejected::dispatch($hotelUser);
    }

    /**
     * Lists approved members of a hotel (employees and admins alike) who are
     * still currently employed (their linked experience at this hotel has no
     * end date yet). Admins/donos aparecem aqui também — quem dirige o hotel
     * também é um colaborador dele.
     * Requirement: 5.1
     */
    public function listApproved(Hotel $hotel): Collection
    {
        return $hotel->hotelUsers()
            ->with('user')
            ->where('status', 'approved')
            ->whereHas('user.experiences', function ($query) use ($hotel) {
                $query->where('hotel_id', $hotel->id)->whereNull('ended_at');
            })
            ->get();
    }

    /**
     * Lists pending link requests for a hotel (admin only).
     * Requirement: 6.4
     */
    public function listPending(Hotel $hotel): Collection
    {
        return $hotel->hotelUsers()
            ->with('user')
            ->where('role', 'employee')
            ->where('status', 'pending')
            ->get();
    }
}
