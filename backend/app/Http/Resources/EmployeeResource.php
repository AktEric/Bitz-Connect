<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats employee (user with hotel pivot) data for public listing.
 * Requirements: 4.2, 5.2
 */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $experience = $this->experiences()
            ->where('hotel_id', $this->pivot->hotel_id ?? null)
            ->first();

        return [
            'uuid'       => $this->uuid,
            'name'       => $this->name,
            'avatar_url' => $this->avatar_url ?? null,
            'title'      => $experience?->title,
            'started_at' => $experience?->started_at,
            'verified'   => $experience?->verified ?? false,
            'hotel_user' => [
                'role'       => $this->pivot->role ?? null,
                'status'     => $this->pivot->status ?? null,
                'created_at' => $this->pivot->created_at ?? null,
            ],
        ];
    }
}
