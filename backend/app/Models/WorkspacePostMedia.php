<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto de uma publicação do mural (arquivo em disco privado). */
class WorkspacePostMedia extends Model
{
    protected $table = 'workspace_post_media';

    protected $fillable = ['uuid', 'workspace_post_id', 'path', 'mime', 'size', 'position'];

    /** Publicação (não retorna se ela foi removida). */
    public function post(): BelongsTo
    {
        return $this->belongsTo(WorkspacePost::class, 'workspace_post_id');
    }
}
