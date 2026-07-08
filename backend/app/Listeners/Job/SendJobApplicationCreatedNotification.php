<?php

namespace App\Listeners\Job;

use App\Events\Job\JobApplicationCreated;
use App\Models\HotelUser;
use App\Models\User;
use App\Services\NotificationService;

class SendJobApplicationCreatedNotification
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(JobApplicationCreated $event): void
    {
        $job   = $event->job->loadMissing('hotel');
        $hotel = $job->hotel;

        // Notify all hotel admins
        $adminIds = HotelUser::where('hotel_id', $hotel->id)
            ->where('role', 'admin')
            ->pluck('user_id');

        User::whereIn('id', $adminIds)->each(function (User $admin) use ($job, $event) {
            $this->notificationService->jobApplicationReceived(
                $admin,
                $event->applicant,
                $job->uuid,
                $job->title,
            );
        });
    }
}
