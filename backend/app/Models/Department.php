<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Setor de uma empresa (Hotel).
 */
class Department extends Model
{
    protected $fillable = ['uuid', 'hotel_id', 'name', 'head_id'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /** Chefe da área. */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    /** Vínculos (hotel_users) alocados neste setor. */
    public function hotelUsers(): HasMany
    {
        return $this->hasMany(HotelUser::class);
    }
}
