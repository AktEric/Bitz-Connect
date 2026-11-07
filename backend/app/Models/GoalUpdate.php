<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoalUpdate extends Model
{
    protected $fillable = ['goal_id','user_id','value','note'];

    protected $table = 'goal_updates';
    protected function casts(): array
    {
        return ['value' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
