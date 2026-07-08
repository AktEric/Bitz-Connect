<?php

namespace App\Listeners\Hotel;

use App\Events\Hotel\EmployeeLinkRejected;
use App\Services\NotificationService;

class SendEmployeeLinkRejectedNotification
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(EmployeeLinkRejected $event): void
    {
        $hotelUser = $event->hotelUser->loadMissing(['hotel', 'user']);

        $this->notificationService->hotelLinkRejected(
            $hotelUser->user,
            $hotelUser->hotel->name,
        );
    }
}
