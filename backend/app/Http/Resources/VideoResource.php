<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata dados públicos de um vídeo.
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,
            'title'            => $this->title,
            'description'      => $this->description,
            'video_url'        => $this->video_path ? asset('storage/' . $this->video_path) : null,
            'thumbnail_url'    => $this->thumbnail_path ? asset('storage/' . $this->thumbnail_path) : null,
            'duration_seconds' => $this->duration_seconds,
            'category'         => $this->category,
            'tags'             => $this->tags ?? [],
            'views_count'      => (int) $this->views_count,
            'likes_count'      => (int) $this->likes_count,
            'comments_count'   => (int) $this->comments_count,
            'shares_count'     => (int) $this->shares_count,
            // Presente quando o controller resolve o exists por usuário (sem N+1).
            'liked_by_me'      => $this->when(isset($this->liked_by_me), fn () => (bool) $this->liked_by_me),
            'created_at'       => $this->created_at?->toISOString(),
            'creator'          => $this->whenLoaded('user', fn () => [
                'uuid'       => $this->user->uuid,
                'name'       => $this->user->name,
                'avatar_url' => $this->user->avatar_path ? asset('storage/' . $this->user->avatar_path) : null,
                'plan'       => $this->user->relationLoaded('subscriptions') ? $this->user->activePlan()->value : null,
                'is_pro'     => $this->user->relationLoaded('subscriptions') ? $this->user->isPro() : null,
            ]),
        ];
    }
}
