<?php

namespace App\Domain\Review\Services;

use App\Domain\Review\DTOs\CreateReviewDTO;
use App\Models\Course;
use App\Models\Mentorship;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Avaliações polimórficas de criadores, cursos e mentorias.
 *
 * Elegibilidade (evita avaliações de quem não teve contato real com o alvo):
 *  - Curso:    usuário matriculado (course_enrollments).
 *  - Mentoria: usuário com solicitação concluída (status = completed).
 *  - Criador:  usuário segue o criador (user_followers).
 *
 * Sprint: monetization-hotelconnect-pro (Avaliações)
 */
class ReviewService
{
    /** Se o usuário pode avaliar este alvo (vínculo real + não é o próprio dono). */
    public function canReview(Model $reviewable, User $user): bool
    {
        if ($reviewable instanceof Course) {
            if ($reviewable->user_id === $user->id) {
                return false;
            }

            return $reviewable->enrollments()->where('user_id', $user->id)->exists();
        }

        if ($reviewable instanceof Mentorship) {
            if ($reviewable->user_id === $user->id) {
                return false;
            }

            return $reviewable->bookings()
                ->where('user_id', $user->id)
                ->where('status', 'completed')
                ->exists();
        }

        if ($reviewable instanceof User) {
            if ($reviewable->id === $user->id) {
                return false;
            }

            return $reviewable->creatorFollowers()->where('follower_id', $user->id)->exists();
        }

        return false;
    }

    /** Cria ou atualiza a avaliação do usuário para o alvo (idempotente por par). */
    public function upsert(Model $reviewable, User $user, CreateReviewDTO $dto): Review
    {
        $review = $reviewable->reviews()->updateOrCreate(
            ['user_id' => $user->id],
            ['uuid' => (string) Str::uuid(), 'rating' => $dto->rating, 'comment' => $dto->comment],
        );

        return $review->load('user.subscriptions');
    }

    public function listFor(Model $reviewable, int $perPage = 20)
    {
        return $reviewable->reviews()
            ->with('user.subscriptions')
            ->latest()
            ->paginate($perPage);
    }

    /** @return array{average: float, count: int} */
    public function summaryFor(Model $reviewable): array
    {
        $count = $reviewable->reviews()->count();
        $average = $count > 0 ? round((float) $reviewable->reviews()->avg('rating'), 1) : 0.0;

        return ['average' => $average, 'count' => $count];
    }

    public function myReview(Model $reviewable, ?User $user): ?Review
    {
        if (! $user) {
            return null;
        }

        return $reviewable->reviews()->where('user_id', $user->id)->first();
    }
}
