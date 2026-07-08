<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Authorization rules for Post resources.
 * Only the post author (or PlatformAdmin) can update or delete a post.
 * Requirements: 4.9, 7.3
 */
class PostPolicy
{
    /**
     * Platform admins bypass all policy checks.
     */
    public function before(User $user): ?bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Only the post author can update a post.
     * Requirements: 4.3, 4.9
     */
    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }

    /**
     * Only the post author can delete a post.
     * Requirements: 4.4, 4.9
     */
    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }
}
