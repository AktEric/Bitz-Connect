<?php

namespace App\Listeners\Feed;

use App\Events\Feed\PostCommented;
use App\Services\NotificationService;

class SendPostCommentedNotification
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(PostCommented $event): void
    {
        $post = $event->post->loadMissing('user');

        // Trim comment excerpt to 80 chars
        $excerpt = mb_strlen($event->comment->content) > 80
            ? mb_substr($event->comment->content, 0, 80) . '…'
            : $event->comment->content;

        $this->notificationService->postCommented(
            $post->user,
            $event->commenter,
            $post->uuid,
            $excerpt,
        );
    }
}
