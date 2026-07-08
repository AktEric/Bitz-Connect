<?php

namespace App\Listeners\Feed;

use App\Events\Feed\PostLiked;
use App\Services\NotificationService;

class SendPostLikedNotification
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function handle(PostLiked $event): void
    {
        $post = $event->post->loadMissing('user');

        $this->notificationService->postLiked(
            $post->user,
            $event->liker,
            $post->uuid,
        );
    }
}
