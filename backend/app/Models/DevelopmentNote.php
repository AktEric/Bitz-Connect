<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentNote extends Model
{
    protected $fillable = ['development_plan_id','author_id','body'];

    protected $table = 'development_notes';
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

}
