<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'        => $this->uuid,
            'type'        => $this->type,
            'category'    => $this->category,
            'title'       => $this->title,
            'description' => $this->description,
            'link'        => $this->link,
            'read'        => (bool) $this->read,
            'created_at'  => $this->created_at?->toISOString(),

            // The user who triggered this notification (actor)
            'actor' => $this->when(
                $this->relationLoaded('actor') && $this->actor,
                fn () => [
                    'uuid'       => $this->actor->uuid,
                    'name'       => $this->actor->name,
                    'avatar_url' => $this->actor->avatar_path
                        ? asset('storage/' . $this->actor->avatar_path)
                        : null,
                    'plan'       => $this->actor->relationLoaded('subscriptions') ? $this->actor->activePlan()->value : null,
                    'is_pro'     => $this->actor->relationLoaded('subscriptions') ? $this->actor->isPro() : null,
                ],
            ),
        ];
    }
}
