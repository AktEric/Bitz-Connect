<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkspacePost extends Model
{
    use SoftDeletes;

    protected $fillable = ['uuid','hotel_id','author_id','department_id','body','pinned'];

    protected $table = 'workspace_posts';
    protected function casts(): array
    {
        return ['pinned' => 'boolean'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WorkspacePostComment::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(WorkspacePostMedia::class)->orderBy('position');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(WorkspacePostLike::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
