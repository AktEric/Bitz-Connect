<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Marcação de ponto (entrada, pausa, retorno, saída). */
class TimeEntry extends Model
{
    public const TYPES = ['in', 'break_start', 'break_end', 'out'];

    protected $fillable = ['uuid', 'hotel_id', 'user_id', 'type', 'punched_at', 'source', 'note', 'created_by', 'ip', 'lat', 'lng', 'accuracy_m', 'photo_path', 'outside_fence',
        'device_id', 'device_name', 'device_label', 'user_agent', 'network_name', 'outside_network', 'new_device', 'nsr', 'hash'];

    protected function casts(): array
    {
        return ['punched_at' => 'datetime', 'lat' => 'float', 'lng' => 'float', 'outside_fence' => 'boolean', 'outside_network' => 'boolean', 'new_device' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
