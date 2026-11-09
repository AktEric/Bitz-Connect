<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Reação (emoji) de uma pessoa a uma mensagem de conversa direta ou de grupo. */
class ChatMessageReaction extends Model
{
    protected $fillable = ['message_kind', 'message_id', 'user_id', 'emoji'];
}
