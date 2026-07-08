<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitação de mentoria feita por um usuário.
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
class MentorshipBooking extends Model
{
    protected $fillable = [
        'uuid',
        'mentorship_id',
        'user_id',
        'message',
        'scheduled_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function mentorship(): BelongsTo
    {
        return $this->belongsTo(Mentorship::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
