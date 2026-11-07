<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Marcação de ponto (entrada, pausa, retorno, saída). */
class TimeEntry extends Model
{
    public const TYPES = ['in', 'break_start', 'break_end', 'out'];

    protected $fillable = ['uuid', 'hotel_id', 'user_id', 'type', 'punched_at', 'source', 'note', 'created_by', 'ip'];

    protected function casts(): array
    {
        return ['punched_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
