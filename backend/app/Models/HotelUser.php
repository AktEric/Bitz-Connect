<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model for hotel admin/employee relationships.
 * Requirements: 3.1, 3.7, 3.8, 6.2
 */
class HotelUser extends Model
{
    protected $fillable = [
        'hotel_id',
        'user_id',
        'role',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string', // 'pending' | 'approved' | 'rejected'
            'role'   => 'string', // 'admin' | 'employee'
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
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
