<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Requirements: 2.5, 4.6, 5.2
 */
class ExperienceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'uuid'        => $this->uuid,
            'title'       => $this->title,
            'company'     => $this->company,
            'started_at'  => $this->started_at?->toDateString(),
            'ended_at'    => $this->ended_at?->toDateString(),
            'description' => $this->description,
            'created_at'  => $this->created_at?->toISOString(),
            'hotel_id'          => $this->hotel_id,
            'verified'          => $this->verified,
            'hotel'             => $this->whenLoaded('hotel', fn () => new HotelResource($this->hotel)),
            'hotel_link_status' => $this->hotel_link_status,
        ];
    }
}
