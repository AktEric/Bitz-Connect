<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats a subscription record.
 */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'         => $this->uuid,
            'plan'         => $this->plan->value,
            'status'       => $this->status->value,
            'started_at'   => $this->started_at?->toISOString(),
            'expires_at'   => $this->expires_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'provider'     => $this->provider,
        ];
    }
}
