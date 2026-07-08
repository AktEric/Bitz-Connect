<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a connection (social link) between two users.
 */
class Connection extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'requester_id',
        'addressee_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }
}
