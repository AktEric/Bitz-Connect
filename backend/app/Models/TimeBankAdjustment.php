<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lançamento manual no banco de horas (crédito ou débito) feito pelo RH, sempre com motivo. */
class TimeBankAdjustment extends Model
{
    protected $fillable = ['uuid', 'hotel_id', 'user_id', 'minutes', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['minutes' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
