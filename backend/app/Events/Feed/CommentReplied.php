<?php

namespace App\Events\Feed;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentReplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Post    $post,
        public readonly Comment $parentComment,
        public readonly Comment $reply,
        public readonly User    $replier,
    ) {}
}
