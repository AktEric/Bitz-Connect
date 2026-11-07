<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceJob extends Model
{
    protected $fillable = ['uuid', 'hotel_id', 'department_id', 'created_by', 'title', 'description', 'requirements', 'location', 'contract_type', 'contact', 'status', 'public', 'share_token', 'closes_on'];

    protected function casts(): array
    {
        return ['public' => 'boolean', 'closes_on' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(WorkspaceJobApplication::class);
    }

    /** Aberta e dentro do prazo. */
    public function isOpen(): bool
    {
        return $this->status === 'open' && (! $this->closes_on || $this->closes_on->endOfDay()->isFuture());
    }
}
