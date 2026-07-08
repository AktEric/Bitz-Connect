<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata dados de uma mentoria.
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
class MentorshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,
            'title'            => $this->title,
            'description'      => $this->description,
            'category'         => $this->category,
            'duration_minutes' => (int) $this->duration_minutes,
            'session_type'     => $this->session_type,
            'price_cents'      => (int) $this->price_cents,
            'status'           => $this->status,
            'bookings_count'   => (int) $this->bookings_count,
            'rating_avg'       => $this->reviews_avg_rating !== null ? round((float) $this->reviews_avg_rating, 1) : 0.0,
            'reviews_count'    => (int) ($this->reviews_count ?? 0),
            'created_at'       => $this->created_at?->toISOString(),
            'my_booking_status' => $this->when(isset($this->my_booking_status), fn () => $this->my_booking_status),
            'is_owner'         => $this->when(isset($this->is_owner), fn () => (bool) $this->is_owner),
            'mentor'           => $this->whenLoaded('user', fn () => [
                'uuid'       => $this->user->uuid,
                'name'       => $this->user->name,
                'avatar_url' => $this->user->avatar_path ? asset('storage/' . $this->user->avatar_path) : null,
                'plan'       => $this->user->relationLoaded('subscriptions') ? $this->user->activePlan()->value : null,
                'is_pro'     => $this->user->relationLoaded('subscriptions') ? $this->user->isPro() : null,
            ]),
        ];
    }
}
