<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pedido do colaborador para incluir uma marcação esquecida; aprovado por gestor ou RH. */
class TimeAdjustment extends Model
{
    protected $fillable = ['uuid', 'hotel_id', 'user_id', 'type', 'requested_at', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
