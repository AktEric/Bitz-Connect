<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Mentorship\DTOs\CreateMentorshipDTO;
use App\Domain\Mentorship\Services\MentorshipService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mentorship\MentorshipRequest;
use App\Http\Resources\MentorshipBookingResource;
use App\Http\Resources\MentorshipResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Mentorship;
use App\Models\MentorshipBooking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mentorias (§11). Criação exclusiva de Consultores PRO (sell_mentorships);
 * solicitação gratuita no MVP.
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
class MentorshipController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly MentorshipService $mentorships,
    ) {}

    /** GET /mentorships — catálogo publicado (com busca ?q e filtro ?category). */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->mentorships->catalog(12, $request->query('category'), $request->query('q'));
        $paginator->setCollection($paginator->getCollection()->map(fn (Mentorship $m) => new MentorshipResource($m)));

        return $this->paginated('Mentorias carregadas com sucesso.', $paginator);
    }

    /** GET /mentorships/{mentorship} — detalhe. */
    public function show(Request $request, Mentorship $mentorship): JsonResponse
    {
        $mentorship->load('user.subscriptions')->loadCount('reviews')->loadAvg('reviews', 'rating');
        $user = $request->user();

        $mentorship->setAttribute('is_owner', $user && $mentorship->user_id === $user->id);
        if ($user) {
            $booking = $mentorship->bookings()->where('user_id', $user->id)->latest()->first();
            $mentorship->setAttribute('my_booking_status', $booking?->status);
        }

        return $this->success('Mentoria carregada com sucesso.', new MentorshipResource($mentorship));
    }

    /** POST /mentorships — cria (Consultor PRO). */
    public function store(MentorshipRequest $request): JsonResponse
    {
        if ($request->user()->cannot('sell_mentorships')) {
            return $this->forbidden('A oferta de mentorias é exclusiva do HotelConnect PRO - Consultor.');
        }

        $mentorship = $this->mentorships->create(CreateMentorshipDTO::fromArray($request->validated(), $request->user()->id));

        return $this->created('Mentoria criada com sucesso.', new MentorshipResource($mentorship->load('user.subscriptions')));
    }

    /** PUT /mentorships/{mentorship} — edita (dono). */
    public function update(MentorshipRequest $request, Mentorship $mentorship): JsonResponse
    {
        if ($resp = $this->denyIfNotOwner($request, $mentorship)) {
            return $resp;
        }

        $mentorship = $this->mentorships->update($mentorship, $request->validated());

        return $this->success('Mentoria atualizada com sucesso.', new MentorshipResource($mentorship->load('user.subscriptions')));
    }

    /** PATCH /mentorships/{mentorship}/publish — publica (dono). */
    public function publish(Request $request, Mentorship $mentorship): JsonResponse
    {
        if ($resp = $this->denyIfNotOwner($request, $mentorship)) {
            return $resp;
        }

        $mentorship = $this->mentorships->publish($mentorship);

        return $this->success('Mentoria publicada com sucesso.', new MentorshipResource($mentorship->load('user.subscriptions')));
    }

    // ── Solicitações ─────────────────────────────────────────

    /** POST /mentorships/{mentorship}/book — solicita mentoria. */
    public function book(Request $request, Mentorship $mentorship): JsonResponse
    {
        if ($mentorship->status !== 'published') {
            return $this->error('Esta mentoria ainda não está disponível.', [], 422);
        }
        if ($mentorship->user_id === $request->user()->id) {
            return $this->error('Você não pode solicitar sua própria mentoria.', [], 422);
        }

        $data = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);
        $booking = $this->mentorships->book($mentorship, $request->user(), $data['message'] ?? null);

        return $this->created('Solicitação enviada com sucesso.', new MentorshipBookingResource($booking->load('user.subscriptions')));
    }

    /** GET /mentorships/{mentorship}/bookings — solicitações recebidas (dono). */
    public function bookings(Request $request, Mentorship $mentorship): JsonResponse
    {
        if ($resp = $this->denyIfNotOwner($request, $mentorship)) {
            return $resp;
        }

        $paginator = $this->mentorships->listBookings($mentorship);
        $paginator->setCollection($paginator->getCollection()->map(fn (MentorshipBooking $b) => new MentorshipBookingResource($b)));

        return $this->paginated('Solicitações carregadas com sucesso.', $paginator);
    }

    /** PATCH /mentorships/{mentorship}/bookings/{booking} — aceita/recusa/conclui (dono). */
    public function updateBooking(Request $request, Mentorship $mentorship, MentorshipBooking $booking): JsonResponse
    {
        if ($resp = $this->denyIfNotOwner($request, $mentorship)) {
            return $resp;
        }
        if ($booking->mentorship_id !== $mentorship->id) {
            return $this->notFound('Solicitação não encontrada.');
        }

        $data = $request->validate(['status' => ['required', 'in:accepted,declined,completed']]);

        try {
            $booking = $this->mentorships->updateBookingStatus($booking, $data['status']);
        } catch (\RuntimeException $e) {
            return $this->error('Status inválido.', [], 422);
        }

        return $this->success('Solicitação atualizada com sucesso.', new MentorshipBookingResource($booking->load('user.subscriptions')));
    }

    /** GET /me/mentorship-bookings — minhas solicitações (como aluno). */
    public function myBookings(Request $request): JsonResponse
    {
        $paginator = $this->mentorships->myBookings($request->user());
        $paginator->setCollection($paginator->getCollection()->map(fn (MentorshipBooking $b) => new MentorshipBookingResource($b)));

        return $this->paginated('Suas solicitações carregadas com sucesso.', $paginator);
    }

    /** GET /users/{user}/mentorships — mentorias de um mentor. */
    public function mentorMentorships(User $user): JsonResponse
    {
        $paginator = $this->mentorships->byMentor($user);
        $paginator->setCollection($paginator->getCollection()->map(fn (Mentorship $m) => new MentorshipResource($m)));

        return $this->paginated('Mentorias do mentor carregadas com sucesso.', $paginator);
    }

    // ── Helpers ──────────────────────────────────────────────

    private function denyIfNotOwner(Request $request, Mentorship $mentorship): ?JsonResponse
    {
        return $mentorship->user_id === $request->user()->id
            ? null
            : $this->forbidden('Você não é o autor desta mentoria.');
    }
}
