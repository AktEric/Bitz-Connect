<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata dados públicos de um story (vaga ou mentoria em destaque).
 * Sprint: monetization-hotelconnect-pro (Stories)
 */
class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'          => $this->uuid,
            'story_type'    => $this->story_type,
            'caption'       => $this->caption,
            'image_url'     => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'views_count'   => (int) $this->views_count,
            'likes_count'   => (int) $this->likes_count,
            'shares_count'  => (int) $this->shares_count,
            'liked_by_me'   => $this->when(isset($this->liked_by_me), fn () => (bool) $this->liked_by_me),
            'created_at'    => $this->created_at?->toISOString(),
            'job'           => $this->job ? new JobResource($this->job) : null,
            'mentorship'    => $this->mentorship ? new MentorshipResource($this->mentorship) : null,
        ];
    }
}
