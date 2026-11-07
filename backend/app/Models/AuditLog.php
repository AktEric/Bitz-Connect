<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Trilha de auditoria de ações sensíveis de RH (LGPD): quem fez o quê, sobre quem e quando.
 * Só se grava; nunca se edita nem se apaga pela aplicação.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['hotel_id', 'actor_id', 'target_id', 'action', 'meta', 'ip', 'created_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public static function record(Request $request, Hotel $hotel, string $action, ?User $target = null, array $meta = []): self
    {
        return static::create([
            'hotel_id'   => $hotel->id,
            'actor_id'   => $request->user()?->id,
            'target_id'  => $target?->id,
            'action'     => $action,
            'meta'       => $meta ?: null,
            'ip'         => $request->ip(),
            'created_at' => now(),
        ]);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }
}
