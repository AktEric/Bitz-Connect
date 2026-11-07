<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceRequestComment extends Model
{
    protected $fillable = ['workspace_request_id','author_id','body'];

    protected $table = 'workspace_request_comments';
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

}
