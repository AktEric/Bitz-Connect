<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata uma solicitação de mentoria.
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
class MentorshipBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'         => $this->uuid,
            'message'      => $this->message,
            'status'       => $this->status,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'created_at'   => $this->created_at?->toISOString(),
            'user'         => $this->whenLoaded('user', fn () => [
                'uuid'       => $this->user->uuid,
                'name'       => $this->user->name,
                'avatar_url' => $this->user->avatar_path ? asset('storage/' . $this->user->avatar_path) : null,
                'plan'       => $this->user->relationLoaded('subscriptions') ? $this->user->activePlan()->value : null,
                'is_pro'     => $this->user->relationLoaded('subscriptions') ? $this->user->isPro() : null,
            ]),
            'mentorship'   => $this->whenLoaded('mentorship', fn () => new MentorshipResource($this->mentorship)),
        ];
    }
}
