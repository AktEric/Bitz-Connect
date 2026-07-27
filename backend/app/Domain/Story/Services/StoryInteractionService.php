<?php

namespace App\Domain\Story\Services;

use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Curtidas e compartilhamentos de story.
 * Mantém os contadores desnormalizados do Story sincronizados.
 * Sprint: monetization-hotelconnect-pro (Stories)
 */
class StoryInteractionService
{
    /**
     * Alterna a curtida do usuário no story (idempotente).
     * Retorna o estado final: true = curtido, false = descurtido.
     */
    public function toggleLike(Story $story, User $user): bool
    {
        return DB::transaction(function () use ($story, $user) {
            $existing = $story->likes()->where('user_id', $user->id)->first();

            if ($existing) {
                $existing->delete();
                $story->decrement('likes_count');

                return false;
            }

            $story->likes()->create(['user_id' => $user->id]);
            $story->increment('likes_count');

            return true;
        });
    }

    public function registerShare(Story $story): void
    {
        $story->increment('shares_count');
    }
}
