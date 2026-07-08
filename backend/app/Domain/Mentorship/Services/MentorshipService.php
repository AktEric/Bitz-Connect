<?php

namespace App\Domain\Mentorship\Services;

use App\Domain\Mentorship\DTOs\CreateMentorshipDTO;
use App\Domain\Mentorship\Repositories\MentorshipRepositoryInterface;
use App\Models\Mentorship;
use App\Models\MentorshipBooking;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Regras de negócio de mentorias e solicitações. Autorização (gate sell_mentorships
 * e propriedade) é verificada no controller.
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
class MentorshipService
{
    private const VALID_STATUSES = ['accepted', 'declined', 'completed'];

    public function __construct(
        private readonly MentorshipRepositoryInterface $repository,
    ) {}

    public function catalog(int $perPage = 12, ?string $category = null, ?string $search = null): LengthAwarePaginator
    {
        return $this->repository->published($perPage, $category, $search);
    }

    public function byMentor(User $mentor, int $perPage = 12): LengthAwarePaginator
    {
        return $this->repository->byMentor($mentor, $perPage);
    }

    public function create(CreateMentorshipDTO $dto): Mentorship
    {
        return $this->repository->create($dto, (string) Str::uuid());
    }

    public function update(Mentorship $mentorship, array $data): Mentorship
    {
        $mentorship->update(array_filter([
            'title'            => $data['title'] ?? null,
            'description'      => $data['description'] ?? null,
            'category'         => $data['category'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'session_type'     => $data['session_type'] ?? null,
            'price_cents'      => $data['price_cents'] ?? null,
        ], fn ($v) => $v !== null));

        return $mentorship->fresh();
    }

    public function publish(Mentorship $mentorship): Mentorship
    {
        $mentorship->update(['status' => 'published']);

        return $mentorship->fresh();
    }

    // ── Solicitações ─────────────────────────────────────────

    /**
     * Cria uma solicitação de mentoria. Idempotente por solicitação ativa:
     * se já houver uma requested/accepted do mesmo usuário, retorna-a.
     */
    public function book(Mentorship $mentorship, User $user, ?string $message): MentorshipBooking
    {
        $existing = $mentorship->bookings()
            ->where('user_id', $user->id)
            ->whereIn('status', ['requested', 'accepted'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($mentorship, $user, $message) {
            $booking = $mentorship->bookings()->create([
                'uuid'    => (string) Str::uuid(),
                'user_id' => $user->id,
                'message' => $message,
                'status'  => 'requested',
            ]);
            $mentorship->increment('bookings_count');

            return $booking;
        });
    }

    public function listBookings(Mentorship $mentorship, int $perPage = 20): LengthAwarePaginator
    {
        return $mentorship->bookings()
            ->with('user.subscriptions')
            ->latest()
            ->paginate($perPage);
    }

    /** Atualiza o status de uma solicitação (accepted|declined|completed). */
    public function updateBookingStatus(MentorshipBooking $booking, string $status): MentorshipBooking
    {
        if (! in_array($status, self::VALID_STATUSES, true)) {
            throw new \RuntimeException('invalid_status');
        }

        $booking->update(['status' => $status]);

        return $booking->fresh();
    }

    public function myBookings(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->mentorshipBookings()
            ->with('mentorship.user.subscriptions')
            ->latest()
            ->paginate($perPage);
    }
}
