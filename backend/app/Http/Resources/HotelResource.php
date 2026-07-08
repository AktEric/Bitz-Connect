<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats public hotel data.
 * Requirements: 4.2, 4.9, 5.2, 7.2, 8.1, 8.4, 12.4
 */
class HotelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'            => $this->uuid,
            'name'            => $this->name,
            'slug'            => $this->slug,
            'segment'         => $this->segment,
            'description'     => $this->description,
            'logo_url'        => $this->logo_path
                ? asset('storage/' . $this->logo_path)
                : null,
            'cover_url'       => $this->cover_path
                ? asset('storage/' . $this->cover_path)
                : null,
            'city'            => $this->city,
            'state'           => $this->state,
            'country'         => $this->country,
            'website'         => $this->website,
            'phone'           => $this->phone,
            'contact_email'   => $this->contact_email,
            'followers_count' => $this->followers_count ?? 0,
            'employees_count' => $this->whenCounted('employees_count'),
            'jobs_count'      => $this->whenCounted('jobs_count'),
            'is_following'    => $this->when(isset($this->resource->is_following), $this->resource->is_following ?? false),
            'has_admin'       => $this->hotelUsers()->where('role', 'admin')->where('status', 'approved')->exists(),
            'is_admin'        => (bool) ($this->is_viewer_admin ?? false),
            // Subscription info — only when eager-loaded, so collections stay N+1-free.
            'plan'            => $this->whenLoaded('subscriptions', fn () => $this->activePlan()->value),
            'is_pro'          => $this->whenLoaded('subscriptions', fn () => $this->isPro()),
            'created_at'      => $this->created_at?->toISOString(),
            'admins'          => $this->whenLoaded('admins', fn () =>
                UserResource::collection($this->admins)
            ),
        ];
    }
}
