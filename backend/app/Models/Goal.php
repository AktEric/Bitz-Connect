<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    protected $fillable = ['uuid','hotel_id','owner_id','set_by','title','description','unit','target_value','current_value','period_start','period_end','status'];

    protected $table = 'goals';
    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'target_value' => 'float', 'current_value' => 'float'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(GoalUpdate::class);
    }

    /** Percentual atingido (0–100+). */
    public function progressPercent(): float
    {
        return $this->target_value > 0 ? round($this->current_value / $this->target_value * 100, 1) : 0.0;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
