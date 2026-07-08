<?php

namespace App\Listeners\Hotel;

use App\Events\Hotel\EmployeeLinkRequested;
use App\Services\NotificationService;

class SendEmployeeLinkRequestedNotification
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(EmployeeLinkRequested $event): void
    {
        $hotelUser = $event->hotelUser->loadMissing(['hotel', 'user']);
        $hotel     = $hotelUser->hotel;
        $requester = $hotelUser->user;

        foreach ($hotel->admins as $admin) {
            $this->notificationService->hotelLinkRequested($admin, $requester, $hotel->name, $hotel->slug);
        }
    }
}
