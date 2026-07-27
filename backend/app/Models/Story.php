<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Story de destaque (vaga ou mentoria) publicado por uma conta PRO.
 * MVP sem expiração: fica ativo até o dono removê-lo (soft delete).
 * Sprint: monetization-hotelconnect-pro (Stories)
 */
class Story extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'story_type',
        'job_id',
        'mentorship_id',
        'caption',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'views_count'  => 'integer',
            'likes_count'  => 'integer',
            'shares_count' => 'integer',
            'deleted_at'   => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function mentorship(): BelongsTo
    {
        return $this->belongsTo(Mentorship::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(StoryLike::class);
    }

    // ──────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('story_type', $type);
    }
}
