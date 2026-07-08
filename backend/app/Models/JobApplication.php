<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Requirements: 5.5, 5.7, 5.8, 5.9
 */
class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'job_id',
        'user_id',
        'cover_letter',
        'status',
    ];

    /**
     * Route model binding uses uuid.
     */
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
