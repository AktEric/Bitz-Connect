<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentPlan extends Model
{
    protected $fillable = ['uuid','hotel_id','user_id','created_by','title','objective','period_start','period_end','status'];

    protected $table = 'development_plans';
    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DevelopmentItem::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(DevelopmentNote::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
