<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aula de um curso.
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
class CourseLesson extends Model
{
    protected $fillable = [
        'uuid',
        'course_id',
        'title',
        'description',
        'content',
        'video_url',
        'position',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'position'         => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
