<?php

namespace App\Listeners\Feed;

use App\Events\Feed\CommentReplied;
use App\Services\NotificationService;

class SendCommentRepliedNotification
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(CommentReplied $event): void
    {
        $parentComment = $event->parentComment->loadMissing('user');

        $excerpt = mb_strlen($event->reply->content) > 80
            ? mb_substr($event->reply->content, 0, 80) . '…'
            : $event->reply->content;

        $this->notificationService->commentReplied(
            $parentComment->user,
            $event->replier,
            $event->post->uuid,
            $excerpt,
        );
    }
}
