<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceRequest extends Model
{
    protected $fillable = ['uuid','hotel_id','requester_id','type','title','description','starts_on','ends_on','amount_cents','status','decided_by','decided_at','decision_note'];

    protected $table = 'workspace_requests';
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'decided_at' => 'datetime', 'amount_cents' => 'integer'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WorkspaceRequestComment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
