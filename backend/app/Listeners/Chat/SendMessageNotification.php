<?php

namespace App\Listeners\Chat;

use App\Events\Chat\MessageSent;
use App\Services\NotificationService;

/**
 * Listens for MessageSent and delegates notification creation to NotificationService.
 *
 * Note: NotificationService::newMessage() will be updated in Task 3 to accept
 * $type and $content. Until then, the method is called with whatever signature
 * it exposes. The listener already passes all four parameters so that Task 3
 * can complete the implementation without touching this file.
 *
 * Requirements: 1.3, 1.5, 1.6
 */
class SendMessageNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->message->loadMissing(['sender', 'receiver']);

        $this->notifications->newMessage(
            recipient: $message->receiver,
            sender:    $message->sender,
            type:      $message->type,
            content:   $message->content,
        );
    }
}
