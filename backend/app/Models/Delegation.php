<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Substituição temporária de um gestor/chefe de área por outra pessoa. */
class Delegation extends Model
{
    protected $fillable = ['uuid', 'hotel_id', 'from_user_id', 'to_user_id', 'starts_on', 'ends_on', 'created_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
