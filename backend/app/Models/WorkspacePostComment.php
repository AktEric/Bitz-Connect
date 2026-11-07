<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspacePostComment extends Model
{
    protected $fillable = ['uuid','workspace_post_id','author_id','body'];

    protected $table = 'workspace_post_comments';
    public function post(): BelongsTo
    {
        return $this->belongsTo(WorkspacePost::class, 'workspace_post_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
