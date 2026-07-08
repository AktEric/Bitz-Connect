<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Comentário de vídeo.
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
class VideoComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'video_id',
        'user_id',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
