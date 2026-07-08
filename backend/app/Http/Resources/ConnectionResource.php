<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats a connection record for API responses.
 */
class ConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'status'     => $this->status,
            'requester'  => new UserResource($this->whenLoaded('requester')),
            'addressee'  => new UserResource($this->whenLoaded('addressee')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
