<?php

namespace App\Events\Job;

use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobApplicationCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly JobApplication $application,
        public readonly Job            $job,
        public readonly User           $applicant,
    ) {}
}
