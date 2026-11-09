<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Chat\Services\ChatAttachments;
use App\Domain\Chat\Services\ChatReactions;
use App\Events\Chat\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Hotel;
use App\Models\Job;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Handles direct messaging (chat) between users, and between a user and a
 * hotel's shared inbox (any approved admin of the hotel can see/reply — the
 * hotel appears as the sender/receiver, not the individual admin).
 * Requirements: 7.1
 */
class ChatController extends Controller
{
    use ApiResponseTrait;

    /** @var array<int, array> reações por mensagem, preenchido antes de formatar uma página */
    private array $reactionMap = [];

    // ──────────────────────────────────────────────────────────
    // Personal inbox (pessoa)
    // ──────────────────────────────────────────────────────────

    /**
     * GET /chat/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $this->touchDelivered($request->user()->id);
        $count = Message::where('receiver_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return $this->success('OK', ['count' => $count]);
    }

    /**
     * GET /chat/conversations
     * Lista as conversas do usuário — o interlocutor pode ser outra pessoa
     * ou um hotel (quando a mensagem foi enviada/recebida em nome dele).
     */
    public function conversations(Request $request): JsonResponse
    {
        $authId = $request->user()->id;
        $this->touchDelivered($authId);

        $messages = Message::with(['sender', 'receiver', 'senderHotel', 'receiverHotel'])
            ->where(function ($q) use ($authId) {
                $q->where(function ($q2) use ($authId) {
                    // Mensagens que eu mandei em nome de um hotel não são "minhas" no
                    // sentido pessoal — pertencem à caixa compartilhada do hotel.
                    $q2->where('sender_id', $authId)->whereNull('sender_hotel_id');
                })->orWhere('receiver_id', $authId);
            })
            ->orderByDesc('created_at')
            ->get();

        $conversations = [];
        foreach ($messages as $message) {
            $isMine = $message->sender_id === $authId;

            if ($isMine) {
                $party = $message->receiver_hotel_id
                    ? ['type' => 'hotel', 'model' => $message->receiverHotel]
                    : ['type' => 'user', 'model' => $message->receiver];
            } else {
                $party = $message->sender_hotel_id
                    ? ['type' => 'hotel', 'model' => $message->senderHotel]
                    : ['type' => 'user', 'model' => $message->sender];
            }

            $key = $party['type'] . ':' . $party['model']->id;

            if (!isset($conversations[$key])) {
                $conversations[$key] = [
                    'party'        => $party,
                    'last_message' => $message,
                    'unread_count' => 0,
                ];
            }

            if ($message->receiver_id === $authId && is_null($message->read_at)) {
                $conversations[$key]['unread_count']++;
            }
        }

        $data = array_values(array_map(function ($conv) use ($authId) {
            $msg = $conv['last_message'];

            return [
                'party'        => $this->formatParty($conv['party']['type'], $conv['party']['model']),
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
     */
    public function messages(Request $request, User $user): JsonResponse
    {
        $authId = $request->user()->id;
        $this->touchDelivered($authId);

        $paginator = Message::with(['sender', 'receiver', 'senderHotel', 'receiverHotel'])
            ->where(function ($q) use ($authId, $user) {
                // Mensagens que eu mandei em nome de um hotel não entram aqui —
                // pertencem à caixa compartilhada do hotel, não ao meu chat pessoal.
                $q->where('sender_id', $authId)->whereNull('sender_hotel_id')->where('receiver_id', $user->id);
            })
            ->orWhere(function ($q) use ($authId, $user) {
                $q->where('sender_id', $user->id)->where('receiver_id', $authId);
            })
            ->orderByDesc('created_at')
            ->paginate(30);

        return $this->paginatedMessages($paginator, $authId);
    }

    /**
     * POST /chat/{userUuid}/messages
     */
    public function send(Request $request, User $user, ChatAttachments $attachments): JsonResponse
    {
        $request->validate(['content' => ['required_without_all:file,metadata', 'nullable', 'string', 'max:5000']] + ChatAttachments::rules());

        $authUser = $request->user();

        if ($authUser->id === $user->id) {
            return $this->error('Você não pode enviar mensagem para si mesmo.', [], 422);
        }

        $content = (string) $request->input('content', '');
        if ($request->hasFile('file')) {
            $stored = $attachments->store($request->file('file'), $request->boolean('voice'), $request->integer('duration') ?: null, $content, 'direct');
            if (is_string($stored)) {
                return $this->error($stored, ['file' => [$stored]], 422);
            }
            [$type, $content, $metadata] = $stored;
        } elseif (($rich = $attachments->rich($request->input('type'), $request->input('metadata'), $request->user())) !== null) {
            if (is_string($rich)) {
                return $this->error($rich, [], 422);
            }
            [$type, $content, $metadata] = $rich;
        } else {
            [$type, $metadata, $error] = $this->resolveTypeAndMetadata($request, $authUser, $user);
            if ($error) {
                return $error;
            }
            if ($content === '' && $type === 'listing') {
                $content = '🛍️ Anúncio do marketplace';
            }
        }

        $message = Message::create([
            'uuid'        => (string) Str::uuid(),
            'sender_id'   => $authUser->id,
            'receiver_id' => $user->id,
            'content'     => $content,
            'type'        => $type,
            'metadata'    => $metadata,
        ]);

        $message->load(['sender', 'receiver']);

        event(new MessageSent($message));

        return $this->created('Mensagem enviada com sucesso.', $this->formatMessage($message, $authUser->id));
    }

    /**
     * PATCH /chat/{userUuid}/read
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
    // Lado pessoal falando com a caixa de um hotel
    // ──────────────────────────────────────────────────────────

    /**
     * GET /chat/hotels/{hotel}/messages
     * Thread entre o usuário autenticado e a caixa compartilhada do hotel.
     */
    public function hotelThreadMessages(Request $request, Hotel $hotel): JsonResponse
    {
        $authId = $request->user()->id;

        $paginator = Message::with(['sender', 'receiver', 'senderHotel', 'receiverHotel'])
            ->where(function ($q) use ($authId, $hotel) {
                $q->where('sender_id', $authId)->where('receiver_hotel_id', $hotel->id);
            })
            ->orWhere(function ($q) use ($authId, $hotel) {
                $q->where('sender_hotel_id', $hotel->id)->where('receiver_id', $authId);
            })
            ->orderByDesc('created_at')
            ->paginate(30);

        return $this->paginatedMessages($paginator, $authId);
    }

    /**
     * POST /chat/hotels/{hotel}/messages
     * Envia uma mensagem para a caixa compartilhada do hotel (não vai para
     * um admin específico — qualquer admin aprovado pode ver e responder).
     */
    public function sendToHotel(Request $request, Hotel $hotel): JsonResponse
    {
        $request->validate(['content' => ['required', 'string', 'max:5000']]);

        $authUser = $request->user();

        if ($authUser->isHotelAdminOf($hotel->id)) {
            return $this->error('Você administra este hotel — use a caixa de entrada do painel para responder.', [], 422);
        }

        $message = Message::create([
            'uuid'              => (string) Str::uuid(),
            'sender_id'         => $authUser->id,
            'receiver_hotel_id' => $hotel->id,
            'content'           => $request->input('content'),
            'type'              => 'text',
        ]);

        $message->load(['sender', 'receiverHotel']);

        event(new MessageSent($message));

        return $this->created('Mensagem enviada com sucesso.', $this->formatMessage($message, $authUser->id));
    }

    /**
     * PATCH /chat/hotels/{hotel}/read
     * Marca como lidas, do meu lado, as mensagens que o hotel me enviou.
     */
    public function markHotelThreadRead(Request $request, Hotel $hotel): JsonResponse
    {
        $authId = $request->user()->id;

        Message::where('sender_hotel_id', $hotel->id)
            ->where('receiver_id', $authId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success('Mensagens marcadas como lidas.');
    }

    // ──────────────────────────────────────────────────────────
    // Caixa de entrada compartilhada do hotel (painel do hotel)
    // ──────────────────────────────────────────────────────────

    /**
     * GET /hotels/{hotel:slug}/chat/unread-count
     */
    public function hotelUnreadCount(Request $request, Hotel $hotel): JsonResponse
    {
        $denied = $this->denyUnlessHotelAdmin($request, $hotel);
        if ($denied) {
            return $denied;
        }

        $count = Message::where('receiver_hotel_id', $hotel->id)->whereNull('read_at')->count();

        return $this->success('OK', ['count' => $count]);
    }

    /**
     * GET /hotels/{hotel:slug}/chat/conversations
     * Lista as conversas da caixa compartilhada — uma por candidato/pessoa.
     */
    public function hotelConversations(Request $request, Hotel $hotel): JsonResponse
    {
        $denied = $this->denyUnlessHotelAdmin($request, $hotel);
        if ($denied) {
            return $denied;
        }

        $messages = Message::with(['sender', 'receiver'])
            ->where('sender_hotel_id', $hotel->id)
            ->orWhere('receiver_hotel_id', $hotel->id)
            ->orderByDesc('created_at')
            ->get();

        $conversations = [];
        foreach ($messages as $message) {
            $sentByHotel = $message->sender_hotel_id === $hotel->id;
            $person      = $sentByHotel ? $message->receiver : $message->sender;

            if (! $person) {
                continue;
            }

            if (!isset($conversations[$person->id])) {
                $conversations[$person->id] = [
                    'party'        => $person,
                    'last_message' => $message,
                    'unread_count' => 0,
                ];
            }

            if (! $sentByHotel && is_null($message->read_at)) {
                $conversations[$person->id]['unread_count']++;
            }
        }

        $data = array_values(array_map(function ($conv) use ($hotel) {
            $msg = $conv['last_message'];

            return [
                'party'        => $this->formatParty('user', $conv['party']),
                'last_message' => [
                    'content'    => $msg->content,
                    'created_at' => $msg->created_at->toISOString(),
                    'is_mine'    => $msg->sender_hotel_id === $hotel->id,
                ],
                'unread_count' => $conv['unread_count'],
            ];
        }, $conversations));

        return $this->success('Conversas carregadas com sucesso.', $data);
    }

    /**
     * GET /hotels/{hotel:slug}/chat/{user}/messages
     */
    public function hotelAdminMessages(Request $request, Hotel $hotel, User $user): JsonResponse
    {
        $denied = $this->denyUnlessHotelAdmin($request, $hotel);
        if ($denied) {
            return $denied;
        }

        $paginator = Message::with(['sender', 'receiver', 'senderHotel', 'receiverHotel'])
            ->where(function ($q) use ($hotel, $user) {
                $q->where('sender_hotel_id', $hotel->id)->where('receiver_id', $user->id);
            })
            ->orWhere(function ($q) use ($hotel, $user) {
                $q->where('sender_id', $user->id)->where('receiver_hotel_id', $hotel->id);
            })
            ->orderByDesc('created_at')
            ->paginate(30);

        // No contexto do painel do hotel, "is_mine" é sempre relativo ao hotel, não ao admin logado.
        $data = $paginator->getCollection()->map(fn (Message $msg) => $this->formatMessage($msg, null, $hotel->id));

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
     * POST /hotels/{hotel:slug}/chat/{user}/messages
     * Qualquer admin aprovado pode responder — a mensagem aparece como
     * enviada pelo hotel, não pelo admin.
     */
    public function hotelAdminSend(Request $request, Hotel $hotel, User $user): JsonResponse
    {
        $denied = $this->denyUnlessHotelAdmin($request, $hotel);
        if ($denied) {
            return $denied;
        }

        $request->validate(['content' => ['required', 'string', 'max:5000']]);

        $message = Message::create([
            'uuid'            => (string) Str::uuid(),
            'sender_id'       => $request->user()->id,
            'sender_hotel_id' => $hotel->id,
            'receiver_id'     => $user->id,
            'content'         => $request->input('content'),
            'type'            => 'text',
        ]);

        $message->load(['sender', 'receiver', 'senderHotel']);

        event(new MessageSent($message));

        return $this->created('Mensagem enviada com sucesso.', $this->formatMessage($message, null, $hotel->id));
    }

    /**
     * PATCH /hotels/{hotel:slug}/chat/{user}/read
     * Estado de leitura compartilhado — qualquer admin que ler marca para todos.
     */
    public function hotelAdminMarkRead(Request $request, Hotel $hotel, User $user): JsonResponse
    {
        $denied = $this->denyUnlessHotelAdmin($request, $hotel);
        if ($denied) {
            return $denied;
        }

        Message::where('sender_id', $user->id)
            ->where('receiver_hotel_id', $hotel->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success('Mensagens marcadas como lidas.');
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private function denyUnlessHotelAdmin(Request $request, Hotel $hotel): ?JsonResponse
    {
        if (! $request->user()->isHotelAdminOf($hotel->id)) {
            return $this->forbidden('Você não administra este hotel.');
        }

        return null;
    }

    /**
     * Resolve o `type`/`metadata` do payload (suporta compartilhamento de vaga).
     * Retorna [type, metadata, ?JsonResponse de erro].
     */
    private function resolveTypeAndMetadata(Request $request, ?User $sender = null, ?User $receiver = null): array
    {
        $type = $request->input('type', 'text');

        if ($type === 'listing') {
            $request->validate(['metadata.listing_uuid' => 'required|string']);
            $listing = \App\Models\MarketplaceListing::with('hotel')->where('uuid', $request->input('metadata.listing_uuid'))->first();
            $member = fn (?User $u) => $u && $listing && \App\Models\HotelUser::where('hotel_id', $listing->hotel_id)->where('user_id', $u->id)->where('status', 'approved')->exists();
            if (! $listing || $listing->status === 'hidden' || ! $member($sender) || ! $member($receiver)) {
                return [$type, null, $this->error('Anúncio não encontrado.', [], 422)];
            }

            return [$type, [
                'listing_uuid' => $listing->uuid, 'title' => $listing->title, 'price_cents' => $listing->price_cents,
                'hotel_slug' => $listing->hotel->slug, 'status' => $listing->status,
            ], null];
        }

        if ($type !== 'job') {
            return [$type, null, null];
        }

        $request->validate(['metadata.job_uuid' => 'required|string']);

        $job = Job::with('hotel')->where('uuid', $request->input('metadata.job_uuid'))->first();

        if (! $job) {
            return [$type, null, $this->error('Vaga não encontrada.', [], 422)];
        }

        $location = trim(($job->city ?? '') . ($job->state ? ', ' . $job->state : '')) ?: null;

        $metadata = [
            'job_uuid'      => $job->uuid,
            'title'         => $job->title,
            'hotel_name'    => $job->hotel->name,
            'hotel_logo'    => $job->hotel->logo_path ? asset('storage/' . $job->hotel->logo_path) : null,
            'location'      => $location ?? $job->hotel->city ?? null,
            'contract_type' => $job->contract_type,
            'is_active'     => $job->status === 'active',
        ];

        return [$type, $metadata, null];
    }

    private function paginatedMessages($paginator, ?int $authId, ?int $viewpointHotelId = null): JsonResponse
    {
        $this->reactionMap = $authId ? app(ChatReactions::class)->summaries('direct', $paginator->getCollection()->pluck('id')->all(), $authId) : [];
        $data = $paginator->getCollection()->map(fn (Message $msg) => $this->formatMessage($msg, $authId, $viewpointHotelId));

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

    private function formatParty(string $type, $model): array
    {
        if ($type === 'hotel') {
            return [
                'type'       => 'hotel',
                'uuid'       => $model->uuid,
                'slug'       => $model->slug,
                'name'       => $model->name,
                'avatar_url' => $model->logo_path ? asset('storage/' . $model->logo_path) : null,
            ];
        }

        return [
            'type'       => 'user',
            'uuid'       => $model->uuid,
            'name'       => $model->name,
            'avatar_url' => $model->avatar_path ? asset('storage/' . $model->avatar_path) : null,
        ];
    }

    /**
     * @param  int|null  $authId  Id do usuário autenticado (lado pessoal). Null quando
     *                            `$viewpointHotelId` é informado (lado do painel do hotel).
     * @param  int|null  $viewpointHotelId  Id do hotel cujo painel está vendo a conversa —
     *                            "is_mine" passa a ser relativo ao hotel, não a um admin.
     */
    /** O app da pessoa buscou suas mensagens: elas passam de "enviada" para "entregue". */
    private function touchDelivered(int $userId): void
    {
        Message::where('receiver_id', $userId)->whereNull('delivered_at')->update(['delivered_at' => now()]);
    }

    /**
     * Estado da MINHA mensagem: enviada, entregue ou visualizada. "Visualizada" só aparece quando os dois lados mantêm
     * a confirmação de leitura ligada (como no WhatsApp); com ela desligada, a leitura segue valendo só para os contadores.
     */
    private function deliveryStatus(Message $message, bool $isMine, ?int $viewpointHotelId): ?string
    {
        if (! $isMine || $viewpointHotelId !== null || ! $message->receiver_id) {
            return null;
        }
        $viewerReceipts = (bool) (request()->user()?->chat_read_receipts ?? true);
        if ($message->read_at && $viewerReceipts && ($message->receiver?->chat_read_receipts ?? true)) {
            return 'read';
        }

        return $message->delivered_at || $message->read_at ? 'delivered' : 'sent';
    }

    private function formatMessage(Message $message, ?int $authId, ?int $viewpointHotelId = null): array
    {
        $isMine = $viewpointHotelId !== null
            ? $message->sender_hotel_id === $viewpointHotelId
            : $message->sender_id === $authId;

        $senderParty = $message->sender_hotel_id
            ? $this->formatParty('hotel', $message->senderHotel)
            : $this->formatParty('user', $message->sender);

        return [
            'uuid'       => $message->uuid,
            'content'    => $message->content,
            'is_mine'    => $isMine,
            'read_at'    => $isMine ? null : $message->read_at?->toISOString(),
            'status'     => $this->deliveryStatus($message, $isMine, $viewpointHotelId),
            'reactions'  => $this->reactionMap[$message->id] ?? [],
            'created_at' => $message->created_at->toISOString(),
            'type'       => $message->type?->value ?? 'text',
            'metadata'   => ChatAttachments::publicMetadata($message->metadata),
            'sender'     => $senderParty,
        ];
    }

    /** GET /chat/attachments/{uuid} — entrega o anexo só a quem participa da conversa. */
    public function attachment(Request $request, string $uuid, ChatAttachments $attachments): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $file = $attachments->findFor($request->user(), $uuid);
        if (! $file) {
            return $this->notFound('Anexo não encontrado.');
        }
        // imagens e áudios abrem na tela; o resto baixa. Nunca deixa o navegador "adivinhar" o tipo.
        $inline = in_array($file['kind'], ['image', 'audio'], true);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($file['path'], $file['name'], [
            'Content-Type'           => $file['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=3600',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], $inline ? 'inline' : 'attachment');
    }

    /** POST /chat/messages/{uuid}/react { emoji | null } — reage (ou tira a reação) a uma mensagem. */
    public function react(Request $request, string $uuid, ChatReactions $reactions): JsonResponse
    {
        $data = $request->validate(['emoji' => ['present', 'nullable', 'string', \Illuminate\Validation\Rule::in(ChatReactions::EMOJIS)]]);
        $found = $reactions->find($request->user(), $uuid);
        if (! $found) {
            return $this->notFound('Mensagem não encontrada.');
        }
        [$kind, $message] = $found;

        return $this->success('Reação atualizada.', ['reactions' => $reactions->set($request->user(), $kind, $message->id, $data['emoji'])]);
    }
}
