<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata um comentário de vídeo.
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
class VideoCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'content'    => $this->content,
            'created_at' => $this->created_at?->toISOString(),
            'author'     => $this->whenLoaded('user', fn () => [
                'uuid'       => $this->user->uuid,
                'name'       => $this->user->name,
                'avatar_url' => $this->user->avatar_path ? asset('storage/' . $this->user->avatar_path) : null,
                'plan'       => $this->user->relationLoaded('subscriptions') ? $this->user->activePlan()->value : null,
                'is_pro'     => $this->user->relationLoaded('subscriptions') ? $this->user->isPro() : null,
            ]),
        ];
    }
}
