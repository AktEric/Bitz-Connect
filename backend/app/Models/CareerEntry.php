<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Um cargo/período da trajetória de uma pessoa na empresa. `ended_on` nulo = cargo atual. */
class CareerEntry extends Model
{
    protected $fillable = ['uuid', 'hotel_id', 'user_id', 'title', 'department', 'started_on', 'ended_on', 'note', 'source'];

    protected function casts(): array
    {
        return ['started_on' => 'date', 'ended_on' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
