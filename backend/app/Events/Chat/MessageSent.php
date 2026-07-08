<?php

namespace App\Events\Chat;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by ChatController after a new Message is persisted.
 * The $message instance must have sender and receiver already loaded.
 *
 * Requirements: 1.1, 1.2
 */
class MessageSent
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Message $message) {}
}
