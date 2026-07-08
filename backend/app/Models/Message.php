<?php

namespace App\Models;

use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Requirements: 7.1
 */
class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sender_id',
        'receiver_id',
        'content',
        'read_at',
        'type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'read_at'  => 'datetime',
            'type'     => MessageType::class,
            'metadata' => 'array',
        ];
    }

    /**
     * Route model binding uses uuid instead of id.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ──────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
