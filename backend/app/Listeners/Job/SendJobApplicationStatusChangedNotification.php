<?php

namespace App\Listeners\Job;

use App\Events\Job\JobApplicationStatusChanged;
use App\Services\NotificationService;

class SendJobApplicationStatusChangedNotification
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(JobApplicationStatusChanged $event): void
    {
        $application = $event->application->loadMissing('user');
        $job         = $event->job->loadMissing('hotel');
        $applicant   = $application->user;

        match ($event->newStatus) {
            'hired'    => $this->notificationService->jobApplicationAccepted($applicant, null, $job->title, $job->uuid),
            'rejected' => $this->notificationService->jobApplicationRejected($applicant, null, $job->title, $job->uuid),
            default    => $this->notificationService->jobApplicationUpdated($applicant, $job->title, $job->uuid, $event->newStatus),
        };
    }
}
