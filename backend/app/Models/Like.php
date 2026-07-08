<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Requirements: 4.5, 4.6
 */
class Like extends Model
{
    protected $fillable = [
        'user_id',
        'post_id',
    ];

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
