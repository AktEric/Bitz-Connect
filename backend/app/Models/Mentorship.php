<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mentoria oferecida por um Consultor PRO.
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
class Mentorship extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'description',
        'category',
        'duration_minutes',
        'session_type',
        'price_cents',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price_cents'      => 'integer',
            'bookings_count'   => 'integer',
            'deleted_at'       => 'datetime',
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

    public function bookings(): HasMany
    {
        return $this->hasMany(MentorshipBooking::class);
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
