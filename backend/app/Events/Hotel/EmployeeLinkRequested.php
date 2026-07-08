<?php

namespace App\Events\Hotel;

use App\Models\HotelUser;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeLinkRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly HotelUser $hotelUser,
    ) {}
}
