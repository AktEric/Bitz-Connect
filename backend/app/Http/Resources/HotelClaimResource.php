<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats hotel claim data for API responses.
 * Requirements: 7.2, 12.4
 */
class HotelClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'hotel_id'    => $this->hotel_id,
            'user_id'     => $this->user_id,
            'status'      => $this->status,
            'message'     => $this->message,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
        ];
    }
}
