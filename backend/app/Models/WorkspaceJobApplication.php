<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceJobApplication extends Model
{
    protected $fillable = ['uuid', 'workspace_job_id', 'user_id', 'message', 'status'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(WorkspaceJob::class, 'workspace_job_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
