<?php

namespace App\Events\Feed;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostLiked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Post $post,
        public readonly User $liker,
    ) {}
}
