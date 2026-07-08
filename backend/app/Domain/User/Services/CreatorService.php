<?php

namespace App\Domain\User\Services;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Seguir/deixar de seguir criadores. Espelha o FollowerService de hotéis.
 * Sprint: monetization-hotelconnect-pro (Página do Criador)
 */
class CreatorService
{
    /**
     * Segue um criador: insere em user_followers e incrementa o contador.
     * Lança 409 se já seguir. Impede seguir a si mesmo.
     */
    public function follow(User $creator, User $follower): void
    {
        if ($creator->id === $follower->id) {
            throw new ConflictHttpException('Você não pode seguir a si mesmo.');
        }
        if ($creator->creatorFollowers()->where('follower_id', $follower->id)->exists()) {
            throw new ConflictHttpException('Você já segue este criador.');
        }
        $creator->creatorFollowers()->create(['follower_id' => $follower->id]);
        $creator->increment('followers_count');
    }

    /** Deixa de seguir: remove de user_followers e decrementa o contador. */
    public function unfollow(User $creator, User $follower): void
    {
        $deleted = $creator->creatorFollowers()->where('follower_id', $follower->id)->delete();
        if ($deleted > 0) {
            $creator->decrement('followers_count');
        }
    }

    public function isFollowing(User $creator, ?User $follower): bool
    {
        if (! $follower) {
            return false;
        }

        return $creator->creatorFollowers()->where('follower_id', $follower->id)->exists();
    }
}
