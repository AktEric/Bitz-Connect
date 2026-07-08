<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Aula de curso. O `video_url`/`content` só são expostos para quem tem acesso
 * (matriculado ou dono) — controlado pelo controller via a flag $this->locked.
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
class CourseLessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locked = $this->locked ?? false;

        return [
            'uuid'             => $this->uuid,
            'title'            => $this->title,
            'description'      => $this->description,
            'position'         => $this->position,
            'duration_seconds' => $this->duration_seconds,
            'locked'           => $locked,
            'content'          => $locked ? null : $this->content,
            'video_url'        => $locked ? null : $this->video_url,
        ];
    }
}
