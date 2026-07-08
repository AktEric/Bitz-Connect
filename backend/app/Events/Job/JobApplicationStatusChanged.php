<?php

namespace App\Events\Job;

use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobApplicationStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly JobApplication $application,
        public readonly Job            $job,
        public readonly string         $oldStatus,
        public readonly string         $newStatus,
    ) {}
}
