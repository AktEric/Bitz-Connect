<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot de seguidores de criador.
 * Sprint: monetization-hotelconnect-pro (Página do Criador)
 */
class UserFollower extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'creator_id',
        'follower_id',
    ];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }
}
