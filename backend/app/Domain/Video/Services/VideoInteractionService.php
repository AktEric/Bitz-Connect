<?php

namespace App\Domain\Video\Services;

use App\Models\User;
use App\Models\Video;
use App\Models\VideoComment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Curtidas, comentários e compartilhamentos de vídeo.
 * Mantém os contadores desnormalizados do Video sincronizados.
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
class VideoInteractionService
{
    /**
     * Alterna a curtida do usuário no vídeo (idempotente).
     * Retorna o estado final: true = curtido, false = descurtido.
     */
    public function toggleLike(Video $video, User $user): bool
    {
        return DB::transaction(function () use ($video, $user) {
            $existing = $video->likes()->where('user_id', $user->id)->first();

            if ($existing) {
                $existing->delete();
                $video->decrement('likes_count');

                return false;
            }

            $video->likes()->create(['user_id' => $user->id]);
            $video->increment('likes_count');

            return true;
        });
    }

    public function addComment(Video $video, User $user, string $content): VideoComment
    {
        return DB::transaction(function () use ($video, $user, $content) {
            $comment = $video->comments()->create([
                'uuid'    => (string) Str::uuid(),
                'user_id' => $user->id,
                'content' => $content,
            ]);
            $video->increment('comments_count');

            return $comment->load('user.subscriptions');
        });
    }

    public function deleteComment(VideoComment $comment): void
    {
        DB::transaction(function () use ($comment) {
            $comment->delete();
            $comment->video()->decrement('comments_count');
        });
    }

    public function listComments(Video $video, int $perPage = 20): LengthAwarePaginator
    {
        return $video->comments()
            ->with('user.subscriptions')
            ->latest()
            ->paginate($perPage);
    }

    public function registerShare(Video $video): void
    {
        $video->increment('shares_count');
    }
}
