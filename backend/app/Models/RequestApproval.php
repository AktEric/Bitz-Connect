<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma etapa de aprovação de uma solicitação. */
class RequestApproval extends Model
{
    protected $table = 'workspace_request_approvals';

    protected $fillable = ['workspace_request_id', 'position', 'role', 'status', 'decided_by', 'decided_at', 'note'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'position' => 'integer'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(WorkspaceRequest::class, 'workspace_request_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
