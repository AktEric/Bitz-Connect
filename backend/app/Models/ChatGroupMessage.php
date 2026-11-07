<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatGroupMessage extends Model
{
    protected $fillable = [
        'uuid',
        'chat_group_id',
        'sender_id',
        'content',
        'type',
        'metadata',
        'is_announcement',
    ];

    protected function casts(): array
    {
        return [
            'metadata'        => 'array',
            'is_announcement' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ChatGroup::class, 'chat_group_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
