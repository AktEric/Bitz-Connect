<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats job application data.
 * Requirements: 5.8, 5.9, 8.1, 8.4
 */
class JobApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'         => $this->uuid,
            'status'       => $this->status,
            'cover_letter' => $this->cover_letter,
            'created_at'   => $this->created_at?->toISOString(),
            'job'          => $this->whenLoaded('job', fn () => new JobResource($this->job)),
            'user'         => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }
}
