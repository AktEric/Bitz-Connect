<?php

namespace App\Listeners\Chat;

use App\Events\Chat\MessageSent;
use App\Services\NotificationService;

/**
 * Listens for MessageSent and delegates notification creation to NotificationService.
 * Three cases: pessoa → pessoa, hotel → pessoa (caixa compartilhada respondendo),
 * pessoa → hotel (notifica todos os admins aprovados do hotel).
 *
 * Requirements: 1.3, 1.5, 1.6
 */
class SendMessageNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->message->loadMissing(['sender', 'receiver', 'senderHotel', 'receiverHotel']);

        if ($message->receiver_hotel_id) {
            $hotel = $message->receiverHotel;
            foreach ($hotel->admins as $admin) {
                $this->notifications->newMessageToHotel(
                    admin:   $admin,
                    sender:  $message->sender,
                    hotel:   $hotel,
                    content: $message->content,
                );
            }
            return;
        }

        if ($message->sender_hotel_id) {
            $this->notifications->newMessageFromHotel(
                recipient: $message->receiver,
                hotel:     $message->senderHotel,
                content:   $message->content,
            );
            return;
        }

        $this->notifications->newMessage(
            recipient: $message->receiver,
            sender:    $message->sender,
            type:      $message->type,
            content:   $message->content,
        );
    }
}
