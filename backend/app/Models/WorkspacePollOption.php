<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspacePollOption extends Model
{
    public $timestamps = false;

    protected $fillable = ['uuid', 'workspace_post_id', 'label', 'position'];
}
