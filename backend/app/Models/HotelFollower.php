<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model for hotel follower relationships.
 * Requirements: 7.2, 8.1
 */
class HotelFollower extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'hotel_id',
        'user_id',
    ];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
