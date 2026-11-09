<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceRequest extends Model
{
    protected $fillable = ['uuid','hotel_id','requester_id','type','title','description','starts_on','ends_on','amount_cents','status','decided_by','decided_at','decision_note','category','assigned_to','due_at','first_response_at'];

    protected $table = 'workspace_requests';
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'decided_at' => 'datetime', 'amount_cents' => 'integer', 'due_at' => 'datetime', 'first_response_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WorkspaceRequestComment::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RequestApproval::class, 'workspace_request_id')->orderBy('position');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
