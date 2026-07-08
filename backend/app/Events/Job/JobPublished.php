<?php

namespace App\Events\Job;

use App\Models\Job;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Job $job,
    ) {}
}
