<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Requirements: 6.1, 6.2
 */
class Experience extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'company',
        'hotel_id',
        'verified',
        'started_at',
        'ended_at',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'ended_at'   => 'date',
            'verified'   => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
