<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConnectionResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Connection;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Handles connection (social network links) between users.
 */
class ConnectionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly NotificationService $notificationService) {}

    /**
     * POST /connections/{user}
     * Send a connection invite. The authenticated user is the requester.
     */
    public function send(Request $request, User $user): JsonResponse
    {
        $auth = $request->user();

        // Cannot invite yourself
        if ($auth->id === $user->id) {
            return $this->validationError('Você não pode se conectar com você mesmo.');
        }

        // Check for an existing connection in either direction
        $existing = Connection::where(function ($q) use ($auth, $user) {
            $q->where('requester_id', $auth->id)->where('addressee_id', $user->id);
        })->orWhere(function ($q) use ($auth, $user) {
            $q->where('requester_id', $user->id)->where('addressee_id', $auth->id);
        })->first();

        if ($existing) {
            return $this->validationError(
                'Já existe uma conexão ou convite entre vocês.',
                ['status' => [$existing->status]],
            );
        }

        $connection = Connection::create([
            'uuid'         => (string) Str::uuid(),
            'requester_id' => $auth->id,
            'addressee_id' => $user->id,
            'status'       => 'pending',
        ]);

        $this->notificationService->connectionInvite($user, $auth);

        return $this->created(
            'Convite de conexão enviado.',
            new ConnectionResource($connection->load(['requester', 'addressee'])),
        );
    }

    /**
     * DELETE /connections/{user}
     * Cancel a pending invite (requester only) or remove an accepted connection (both sides).
     */
    public function remove(Request $request, User $user): JsonResponse
    {
        $auth = $request->user();

        $connection = Connection::where(function ($q) use ($auth, $user) {
            $q->where('requester_id', $auth->id)->where('addressee_id', $user->id);
        })->orWhere(function ($q) use ($auth, $user) {
            $q->where('requester_id', $user->id)->where('addressee_id', $auth->id);
        })->first();

        if (! $connection) {
            return $this->notFound('Conexão não encontrada.');
        }

        // Pending: only the requester can cancel
        if ($connection->status === 'pending' && $connection->requester_id !== $auth->id) {
            return $this->forbidden('Apenas quem enviou o convite pode cancelá-lo.');
        }

        // Notify the other party when cancelling a pending invite
        if ($connection->status === 'pending') {
            $this->notificationService->connectionCancelled($user, $auth);
        }

        $connection->delete();

        return $this->success('Conexão removida com sucesso.');
    }

    /**
     * PATCH /connections/{user}/accept
     * Accept a pending invite. Only the addressee can accept.
     */
    public function accept(Request $request, User $user): JsonResponse
    {
        $auth = $request->user();

        $connection = Connection::where('requester_id', $user->id)
            ->where('addressee_id', $auth->id)
            ->where('status', 'pending')
            ->first();

        if (! $connection) {
            return $this->notFound('Convite de conexão não encontrado.');
        }

        $connection->update(['status' => 'accepted']);

        $this->notificationService->connectionAccepted($user, $auth);

        return $this->success(
            'Convite aceito com sucesso.',
            new ConnectionResource($connection->load(['requester', 'addressee'])),
        );
    }

    /**
     * PATCH /connections/{user}/reject
     * Reject a pending invite. Only the addressee can reject.
     */
    public function reject(Request $request, User $user): JsonResponse
    {
        $auth = $request->user();

        $connection = Connection::where('requester_id', $user->id)
            ->where('addressee_id', $auth->id)
            ->where('status', 'pending')
            ->first();

        if (! $connection) {
            return $this->notFound('Convite de conexão não encontrado.');
        }

        $connection->update(['status' => 'rejected']);

        $this->notificationService->connectionRejected($user, $auth);

        return $this->success('Convite rejeitado.');
    }

    /**
     * GET /connections
     * List accepted connections for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $auth = $request->user();

        $connections = Connection::with(['requester', 'addressee'])
            ->where('status', 'accepted')
            ->where(function ($q) use ($auth) {
                $q->where('requester_id', $auth->id)
                  ->orWhere('addressee_id', $auth->id);
            })
            ->latest()
            ->get();

        return $this->success(
            'Conexões carregadas com sucesso.',
            ConnectionResource::collection($connections),
        );
    }

    /**
     * GET /connections/pending
     * List pending invites received by the authenticated user.
     */
    public function pending(Request $request): JsonResponse
    {
        $auth = $request->user();

        $connections = Connection::with(['requester', 'addressee'])
            ->where('addressee_id', $auth->id)
            ->where('status', 'pending')
            ->latest()
            ->get();

        return $this->success(
            'Convites pendentes carregados.',
            ConnectionResource::collection($connections),
        );
    }
}
