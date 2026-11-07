<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Grupo de chat corporativo, pertencente a uma empresa (Hotel).
 */
class ChatGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'hotel_id',
        'created_by',
        'name',
        'description',
        'only_admins_can_post',
    ];

    protected function casts(): array
    {
        return [
            'only_admins_can_post' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChatGroupMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatGroupMessage::class);
    }

    public function membershipOf(User $user): ?ChatGroupMember
    {
        return $this->members()->where('user_id', $user->id)->first();
    }
}
