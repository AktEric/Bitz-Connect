<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fechamento mensal do ponto: congela as horas do mês para a folha e trava edições naquele período. */
class TimeClosing extends Model
{
    protected $fillable = ['uuid', 'hotel_id', 'month', 'rows', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return ['rows' => 'array', 'closed_at' => 'datetime'];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
