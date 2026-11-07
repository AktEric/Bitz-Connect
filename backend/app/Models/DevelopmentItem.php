<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentItem extends Model
{
    protected $fillable = ['uuid','development_plan_id','title','type','due_on','status','notes'];

    protected $table = 'development_items';
    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DevelopmentPlan::class, 'development_plan_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
