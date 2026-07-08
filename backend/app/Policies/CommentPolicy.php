<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

/**
 * Authorization rules for Comment resources.
 * Only the comment author (or PlatformAdmin) can delete a comment.
 * Requirements: 4.9, 7.3
 */
class CommentPolicy
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
     * Only the comment author can delete a comment.
     * Requirements: 4.8, 4.9
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }
}
