<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspacePollVote extends Model
{
    protected $fillable = ['workspace_post_id', 'workspace_poll_option_id', 'user_id'];
}
