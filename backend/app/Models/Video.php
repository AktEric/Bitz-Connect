<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vídeo profissional publicado por um Consultor PRO.
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
class Video extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'description',
        'video_path',
        'thumbnail_path',
        'duration_seconds',
        'category',
        'tags',
        'visibility',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tags'             => 'array',
            'duration_seconds' => 'integer',
            'views_count'      => 'integer',
            'likes_count'      => 'integer',
            'comments_count'   => 'integer',
            'shares_count'     => 'integer',
            'deleted_at'       => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(VideoLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(VideoComment::class);
    }

    // ──────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────

    /** Vídeos visíveis no feed público. */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where('visibility', 'public');
    }
}
