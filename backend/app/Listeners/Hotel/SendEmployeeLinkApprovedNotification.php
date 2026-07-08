<?php

namespace App\Listeners\Hotel;

use App\Events\Hotel\EmployeeLinkApproved;
use App\Services\NotificationService;

class SendEmployeeLinkApprovedNotification
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(EmployeeLinkApproved $event): void
    {
        $hotelUser = $event->hotelUser->loadMissing(['hotel', 'user']);

        $this->notificationService->hotelLinkApproved(
            $hotelUser->user,
            $hotelUser->hotel->name,
            $hotelUser->hotel->slug,
        );
    }
}
