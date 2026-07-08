<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Curso publicado por um Consultor PRO.
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
class Course extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'description',
        'thumbnail_path',
        'category',
        'level',
        'price_cents',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price_cents'       => 'integer',
            'lessons_count'     => 'integer',
            'enrollments_count' => 'integer',
            'deleted_at'        => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class)->orderBy('position');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function reviews(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
