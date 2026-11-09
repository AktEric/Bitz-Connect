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
        'sender_hotel_id',
        'receiver_id',
        'receiver_hotel_id',
        'content',
        'read_at',
        'delivered_at',
        'type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'read_at'  => 'datetime',
            'delivered_at' => 'datetime',
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

    /** Preenchido quando a mensagem foi enviada em nome do hotel (caixa compartilhada). */
    public function senderHotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class, 'sender_hotel_id');
    }

    /** Preenchido quando a mensagem é endereçada à caixa compartilhada do hotel. */
    public function receiverHotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class, 'receiver_hotel_id');
    }

    public function isFromHotel(): bool
    {
        return $this->sender_hotel_id !== null;
    }

    public function isToHotel(): bool
    {
        return $this->receiver_hotel_id !== null;
    }
}
