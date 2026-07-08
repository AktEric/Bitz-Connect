<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata dados de um curso.
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid'              => $this->uuid,
            'title'             => $this->title,
            'description'       => $this->description,
            'thumbnail_url'     => $this->thumbnail_path ? asset('storage/' . $this->thumbnail_path) : null,
            'category'          => $this->category,
            'level'             => $this->level,
            'price_cents'       => (int) $this->price_cents,
            'status'            => $this->status,
            'lessons_count'     => (int) $this->lessons_count,
            'enrollments_count' => (int) $this->enrollments_count,
            'rating_avg'        => $this->reviews_avg_rating !== null ? round((float) $this->reviews_avg_rating, 1) : 0.0,
            'reviews_count'     => (int) ($this->reviews_count ?? 0),
            'created_at'        => $this->created_at?->toISOString(),
            // Presente quando o controller resolve o vínculo do visitante.
            'is_enrolled'       => $this->when(isset($this->is_enrolled), fn () => (bool) $this->is_enrolled),
            'is_owner'          => $this->when(isset($this->is_owner), fn () => (bool) $this->is_owner),
            'lessons'           => CourseLessonResource::collection($this->whenLoaded('lessons')),
            'creator'           => $this->whenLoaded('user', fn () => [
                'uuid'       => $this->user->uuid,
                'name'       => $this->user->name,
                'avatar_url' => $this->user->avatar_path ? asset('storage/' . $this->user->avatar_path) : null,
                'plan'       => $this->user->relationLoaded('subscriptions') ? $this->user->activePlan()->value : null,
                'is_pro'     => $this->user->relationLoaded('subscriptions') ? $this->user->isPro() : null,
            ]),
        ];
    }
}
