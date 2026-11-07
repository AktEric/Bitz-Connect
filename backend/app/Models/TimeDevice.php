<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Computador/tablet cadastrado para marcar ponto, com aprovação do RH. */
class TimeDevice extends Model
{
    protected $fillable = [
        'uuid', 'hotel_id', 'key_hash', 'name', 'status', 'label', 'last_ip',
        'registered_by', 'approved_by', 'approved_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
