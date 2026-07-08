<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats public post data.
 * Requirements: 8.1, 8.4
 */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'           => $this->uuid,
            'content'        => $this->content,
            'type'           => $this->type ?? 'post',
            'metadata'       => $this->metadata,
            'image_url'      => $this->image_path
                ? asset('storage/' . $this->image_path)
                : null,
            'edited'         => (bool) $this->edited,
            'likes_count'    => (int) ($this->likes_count ?? $this->likes->count()),
            'comments_count' => (int) ($this->comments_count ?? $this->comments()->count()),
            'liked_by_me'    => $this->whenLoaded(
                'likes',
                fn () => $this->likes->contains('user_id', auth()->id()),
                false,
            ),
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
            'user'           => $this->whenLoaded('user', fn () => [
                'uuid'       => $this->user->uuid,
                'name'       => $this->user->name,
                'avatar_url' => $this->user->avatar_path
                    ? asset('storage/' . $this->user->avatar_path)
                    : null,
                'plan'       => $this->user->relationLoaded('subscriptions') ? $this->user->activePlan()->value : null,
                'is_pro'     => $this->user->relationLoaded('subscriptions') ? $this->user->isPro() : null,
            ]),
            'hotel' => $this->whenLoaded('hotel', fn () => $this->hotel
                ? [
                    'uuid'     => $this->hotel->uuid,
                    'name'     => $this->hotel->name,
                    'slug'     => $this->hotel->slug,
                    'logo_url' => $this->hotel->logo_path
                        ? asset('storage/' . $this->hotel->logo_path)
                        : null,
                ]
                : null,
            null),
        ];
    }
}
