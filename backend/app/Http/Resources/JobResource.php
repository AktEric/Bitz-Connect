<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats public job data.
 * Exposes uuid (not id); includes hotel relation when loaded.
 * Requirements: 5.2, 8.1, 8.4
 */
class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'               => $this->uuid,
            'title'              => $this->title,
            'description'        => $this->description,
            'requirements'       => $this->requirements,
            'benefits'           => $this->benefits,
            'contract_type'      => $this->contract_type,
            'modality'           => $this->modality,
            'city'               => $this->city,
            'state'              => $this->state,
            'salary_range'       => $this->salary_range,
            'status'             => $this->status,
            'applications_count' => $this->whenCounted('applications'),
            'created_at'         => $this->created_at?->toISOString(),
            'updated_at'         => $this->updated_at?->toISOString(),
            'hotel'              => $this->whenLoaded('hotel', fn () => new HotelResource($this->hotel)),
        ];
    }
}
