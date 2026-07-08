<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Curtida de vídeo.
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
class VideoLike extends Model
{
    protected $fillable = [
        'video_id',
        'user_id',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
