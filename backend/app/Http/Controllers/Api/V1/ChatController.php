<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\Chat\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Job;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Handles direct messaging (chat) between users.
 * Requirements: 7.1
 */
class ChatController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /chat/unread-count
     * Returns the count of unread messages for the authenticated user.
     * Requirements: 4.1, 4.7
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Message::where('receiver_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return $this->success('OK', ['count' => $count]);
    }

    /**
     * GET /chat/conversations
     * Returns the list of conversations for the authenticated user,
     * one entry per interlocutor, with the latest message and unread count.
     */
    public function conversations(Request $request): JsonResponse
    {
        $authId = $request->user()->id;

        // Fetch all messages involving the current user
        $messages = Message::with(['sender', 'receiver'])
            ->where(function ($q) use ($authId) {
                $q->where('sender_id', $authId)
                  ->orWhere('receiver_id', $authId);
            })
            ->orderByDesc('created_at')
            ->get();

        // Build a map keyed by interlocutor ID → latest message
        $conversations = [];
        foreach ($messages as $message) {
            $interlocutorId = $message->sender_id === $authId
                ? $message->receiver_id
                : $message->sender_id;

            if (!isset($conversations[$interlocutorId])) {
                $interlocutor = $message->sender_id === $authId
                    ? $message->receiver
                    : $message->sender;

                $conversations[$interlocutorId] = [
                    'user'         => $interlocutor,
                    'last_message' => $message,
                    'unread_count' => 0,
                ];
            }

            // Count unread messages sent by the interlocutor to auth user
            if ($message->receiver_id === $authId && is_null($message->read_at)) {
                $conversations[$interlocutorId]['unread_count']++;
            }
        }

        $data = array_values(array_map(function ($conv) use ($authId) {
            $user    = $conv['user'];
            $msg     = $conv['last_message'];

            return [
                'user' => [
                    'uuid'       => $user->uuid,
                    'name'       => $user->name,
                    'avatar_url' => $user->avatar_path
                        ? asset('storage/' . $user->avatar_path)
                        : null,
                ],
                'last_message' => [
                    'content'    => $msg->content,
                    'created_at' => $msg->created_at->toISOString(),
                    'is_mine'    => $msg->sender_id === $authId,
                ],
                'unread_count' => $conv['unread_count'],
            ];
        }, $conversations));

        return $this->success('Conversas carregadas com sucesso.', $data);
    }

    /**
     * GET /chat/{userUuid}/messages
     * Returns paginated messages between the auth user and another user (newest first).
     */
    public function messages(Request $request, User $user): JsonResponse
    {
        $authId = $request->user()->id;

        $paginator = Message::with(['sender', 'receiver'])
            ->where(function ($q) use ($authId, $user) {
                $q->where('sender_id', $authId)->where('receiver_id', $user->id);
            })
            ->orWhere(function ($q) use ($authId, $user) {
                $q->where('sender_id', $user->id)->where('receiver_id', $authId);
            })
            ->orderByDesc('created_at')
            ->paginate(30);

        $data = $paginator->getCollection()->map(fn (Message $msg) => $this->formatMessage($msg, $authId));

        return response()->json([
            'success' => true,
            'message' => 'Mensagens carregadas com sucesso.',
            'data'    => $data,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * POST /chat/{userUuid}/messages
     * Sends a message to another user.
     * Requirements: 3.6, 3.7, 6.1, 7.4, 8.1
     */
    public function send(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $authUser = $request->user();

        if ($authUser->id === $user->id) {
            return $this->error('Você não pode enviar mensagem para si mesmo.', [], 422);
        }

        $type = $request->input('type', 'text');
        $metadata = null;

        if ($type === 'job') {
            $request->validate(['metadata.job_uuid' => 'required|string']);

            $job = Job::with('hotel')
                ->where('uuid', $request->input('metadata.job_uuid'))
                ->first();

            if (! $job) {
                return $this->error('Vaga não encontrada.', [], 422);
            }

            $location = trim(($job->city ?? '') . ($job->state ? ', ' . $job->state : '')) ?: null;

            $metadata = [
                'job_uuid'      => $job->uuid,
                'title'         => $job->title,
                'hotel_name'    => $job->hotel->name,
                'hotel_logo'    => $job->hotel->logo_path
                    ? asset('storage/' . $job->hotel->logo_path)
                    : null,
                'location'      => $location ?? $job->hotel->city ?? null,
                'contract_type' => $job->contract_type,
                'is_active'     => $job->status === 'active',
            ];
        }

        $message = Message::create([
            'uuid'        => (string) Str::uuid(),
            'sender_id'   => $authUser->id,
            'receiver_id' => $user->id,
            'content'     => $request->input('content'),
            'type'        => $type,
            'metadata'    => $metadata,
        ]);

        $message->load(['sender', 'receiver']);

        event(new MessageSent($message));

        return $this->created(
            'Mensagem enviada com sucesso.',
            $this->formatMessage($message, $authUser->id),
        );
    }

    /**
     * PATCH /chat/{userUuid}/read
     * Marks all messages from the given user to the auth user as read.
     */
    public function markRead(Request $request, User $user): JsonResponse
    {
        $authId = $request->user()->id;

        Message::where('sender_id', $user->id)
            ->where('receiver_id', $authId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success('Mensagens marcadas como lidas.');
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private function formatMessage(Message $message, int $authId): array
    {
        return [
            'uuid'       => $message->uuid,
            'content'    => $message->content,
            'is_mine'    => $message->sender_id === $authId,
            'read_at'    => $message->read_at?->toISOString(),
            'created_at' => $message->created_at->toISOString(),
            'type'       => $message->type?->value ?? 'text',
            'metadata'   => $message->metadata,
            'sender'     => [
                'uuid'       => $message->sender->uuid,
                'name'       => $message->sender->name,
                'avatar_url' => $message->sender->avatar_path
                    ? asset('storage/' . $message->sender->avatar_path)
                    : null,
            ],
        ];
    }
}
