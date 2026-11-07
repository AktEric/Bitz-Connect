<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspacePostLike extends Model
{
    protected $fillable = ['workspace_post_id','user_id'];

    protected $table = 'workspace_post_likes';

}
