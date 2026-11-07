<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatGroupMember extends Model
{
    protected $fillable = [
        'chat_group_id',
        'user_id',
        'role',
        'notifications_muted',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'notifications_muted' => 'boolean',
            'last_read_at'        => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ChatGroup::class, 'chat_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
