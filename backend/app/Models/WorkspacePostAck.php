<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Confirmação de leitura de um comunicado ("li e estou ciente"), com data e IP. */
class WorkspacePostAck extends Model
{
    public $timestamps = false;

    protected $fillable = ['workspace_post_id', 'user_id', 'acked_at', 'ip'];

    protected function casts(): array
    {
        return ['acked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
