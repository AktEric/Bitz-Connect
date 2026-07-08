<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formats public comment data.
 * Requirements: 8.1, 8.4
 */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'content'    => $this->content,
            'parent_id'  => $this->parent_id,
            'created_at' => $this->created_at?->toISOString(),
            'user'       => $this->whenLoaded('user', fn () => [
                'uuid'       => $this->user->uuid,
                'name'       => $this->user->name,
                'avatar_url' => $this->user->avatar_path
                    ? asset('storage/' . $this->user->avatar_path)
                    : null,
                'plan'       => $this->user->relationLoaded('subscriptions') ? $this->user->activePlan()->value : null,
                'is_pro'     => $this->user->relationLoaded('subscriptions') ? $this->user->isPro() : null,
            ]),
            'replies' => $this->whenLoaded('replies', fn () =>
                CommentResource::collection($this->replies)
            ),
        ];
    }
}
