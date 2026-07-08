# Design Document — Notificações, Mensagens e Compartilhamento

## Overview

Sprint de correção e melhoria do sistema de notificações, chat e compartilhamento de vagas do HotelConnect.

**Problemas identificados no codebase:**
- `ChatController.send()` não dispara nenhum evento após salvar a mensagem — o pipeline de notificações de chat nunca é acionado.
- `NotificationService.newMessage()` cria sempre uma nova notificação, sem upsert.
- `Message` model não tem campo `type` nem `metadata`.
- O `Topbar` não tem badge de mensagens não lidas.
- `useConversations` faz poll a cada 4s e `useMessages` a cada 3s — intervalos hardcoded e separados.
- `ShareJobModal` envia apenas texto simples, sem card rico.

---

## Architecture

### Backend — Fluxo de evento para notificação de mensagem

```
ChatController.send()
  → Message::create()
  → event(new MessageSent($message))         ← NOVO
      → MessageNotificationListener           ← NOVO
          → NotificationService.newMessage()  ← ATUALIZADO (upsert)
```

Segue exatamente o mesmo padrão dos listeners de Feed existentes (`SendPostCommentedNotification`).

### Frontend — Hierarquia de dados de chat

```
AppLayout (Topbar)
  └─ MessageBadge                             ← NOVO
       └─ useUnreadMessages (polling 30s)     ← NOVO

ChatPage
  ├─ useConversations (polling 30s)           ← AJUSTADO de 4s
  └─ MessageWindow
       ├─ useMessages (polling 5s)            ← AJUSTADO de 3s
       └─ MessageBubble                       ← NOVO (substitui div inline)
            ├─ type='text'   → texto normal
            ├─ type='job'    → JobShareCard   ← NOVO
            ├─ type='img-stub' → ImageStub    ← NOVO (placeholder)
            └─ type='file-stub' → FileStub    ← NOVO (placeholder)
```

---

## Components and Interfaces

### Backend

#### 1. Migration — `add_type_metadata_to_messages`

```php
Schema::table('messages', function (Blueprint $table) {
    $table->string('type', 20)->default('text')->after('content');
    $table->json('metadata')->nullable()->after('type');
});
```

#### 2. Enum `MessageType`

```php
// app/Enums/MessageType.php
enum MessageType: string {
    case Text     = 'text';
    case Job      = 'job';
    case Post     = 'post';
    case Profile  = 'profile';
    case Hotel    = 'hotel';
    case ImgStub  = 'img-stub';
    case FileStub = 'file-stub';
}
```

#### 3. Model `Message` — campos adicionados

```php
protected $fillable = ['uuid', 'sender_id', 'receiver_id', 'content', 'read_at', 'type', 'metadata'];

protected function casts(): array {
    return [
        'read_at'  => 'datetime',
        'type'     => MessageType::class,
        'metadata' => 'array',
    ];
}
```

#### 4. Evento `MessageSent`

```php
// app/Events/Chat/MessageSent.php
class MessageSent {
    public function __construct(public readonly Message $message) {}
}
```

#### 5. Listener `SendMessageNotification`

```php
// app/Listeners/Chat/SendMessageNotification.php
class SendMessageNotification {
    public function __construct(private NotificationService $notifications) {}

    public function handle(MessageSent $event): void {
        $message = $event->message->loadMissing(['sender', 'receiver']);
        $this->notifications->newMessage(
            recipient: $message->receiver,
            sender:    $message->sender,
            type:      $message->type,
            content:   $message->content,
        );
    }
}
```

#### 6. `NotificationService.newMessage()` — com upsert

```php
public function newMessage(User $recipient, User $sender, MessageType $type, ?string $content): void {
    if ($recipient->id === $sender->id) return;

    $description = match($type) {
        MessageType::Job      => 'Compartilhou uma vaga com você',
        MessageType::ImgStub,
        MessageType::FileStub => 'Enviou uma mídia',
        default               => mb_strlen($content ?? '') > 100
                                    ? mb_substr($content, 0, 100) . '…'
                                    : ($content ?? ''),
    };

    // Upsert: atualiza notificação não lida existente do mesmo sender
    $existing = Notification::where('user_id', $recipient->id)
        ->where('actor_id', $sender->id)
        ->where('type', 'new_message')
        ->whereNull('read_at')  // campo existente 'read' = false
        ->first();

    if ($existing) {
        $existing->update(['description' => $description, 'updated_at' => now()]);
        return;
    }

    $this->create(
        recipient:   $recipient,
        actor:       $sender,
        type:        'new_message',
        category:    'message',
        title:       "{$sender->name} enviou uma mensagem",
        description: $description,
        link:        "/chat/{$sender->uuid}",
    );
}
```

**Nota:** O model `Notification` usa `read` (boolean), não `read_at`. O upsert filtra por `where('read', false)`.

#### 7. `ChatController` — alterações

```php
// send() — após Message::create():
event(new MessageSent($message->load(['sender', 'receiver'])));

// Novo método unreadCount():
public function unreadCount(Request $request): JsonResponse {
    $count = Message::where('receiver_id', $request->user()->id)
        ->whereNull('read_at')
        ->count();
    return $this->success('OK', ['count' => $count]);
}

// formatMessage() — adicionar type e metadata:
'type'     => $message->type?->value ?? 'text',
'metadata' => $message->metadata,
```

Para `type='job'`, o `metadata` já armazenado no banco (com `title`, `hotel_name`, `location`, `contract_type`, `is_active`, `job_uuid`) é retornado diretamente — sem query extra na serialização.

#### 8. Compartilhamento de vaga — `send()` com type='job'

```php
// Validação adicional quando type='job':
if ($request->input('type', 'text') === 'job') {
    $request->validate(['metadata.job_uuid' => 'required|string']);
    $job = Job::where('uuid', $request->input('metadata.job_uuid'))->firstOrFail();
    // Enriquecer metadata com dados da vaga
    $metadata = [
        'job_uuid'      => $job->uuid,
        'title'         => $job->title,
        'hotel_name'    => $job->hotel->name,
        'hotel_logo'    => $job->hotel->logo_url,
        'location'      => $job->location ?? $job->hotel->city,
        'contract_type' => $job->contract_type,
        'is_active'     => $job->is_active,
    ];
}
```

Publicação automática no feed: `FeedService::createJobSharePost(User $author, array $jobData)` — cria post com `type='job_share'` e `metadata` com dados da vaga. **Requer migration `type` e `metadata` na tabela `posts`.**

#### 9. Rotas novas

```php
Route::get('/chat/unread-count', [ChatController::class, 'unreadCount']);
```

---

### Frontend

#### 10. `src/config/polling.ts`

```typescript
export const CHAT_MESSAGES_POLL_MS      = 5_000
export const CHAT_CONVERSATIONS_POLL_MS = 30_000
export const NOTIFICATIONS_POLL_MS      = 30_000
export const UNREAD_MESSAGES_POLL_MS    = 30_000
```

#### 11. `useChat.ts` — ajuste de intervalos

```typescript
import { CHAT_MESSAGES_POLL_MS, CHAT_CONVERSATIONS_POLL_MS } from '@/config/polling'

// useConversations: refetchInterval: CHAT_CONVERSATIONS_POLL_MS
// useMessages:      refetchInterval: CHAT_MESSAGES_POLL_MS
```

#### 12. `useUnreadMessages` hook

```typescript
// src/hooks/useChat.ts — nova exportação
export const chatKeys = {
  ...
  unread: ['chat', 'unread-count'] as const,
}

export function useUnreadMessages() {
  return useQuery<{ count: number }>({
    queryKey: chatKeys.unread,
    queryFn:  () => chatService.getUnreadCount(),
    refetchInterval: UNREAD_MESSAGES_POLL_MS,
    select: (data) => data,
  })
}
```

#### 13. `chatService` — novo método

```typescript
async getUnreadCount(): Promise<{ count: number }> {
  const res = await api.get<ApiResponse<{ count: number }>>('/chat/unread-count')
  return res.data.data
}
```

#### 14. `api.types.ts` — tipos atualizados

```typescript
export type MessageType = 'text' | 'job' | 'post' | 'profile' | 'hotel' | 'img-stub' | 'file-stub'

export interface JobMetadata {
  job_uuid: string
  title: string
  hotel_name: string
  hotel_logo: string | null
  location: string | null
  contract_type: string | null
  is_active: boolean
}

export interface Message {
  uuid: string
  content: string
  is_mine: boolean
  read_at: string | null
  created_at: string
  sender: ChatUser
  type: MessageType        // ← novo (default 'text')
  metadata: JobMetadata | Record<string, unknown> | null  // ← novo
}
```

#### 15. `JobShareCard` component

```
src/components/chat/JobShareCard.tsx
```

Props: `metadata: JobMetadata | null`, `isActive?: boolean`

Layout:
```
┌─────────────────────────────────┐
│ [Logo]  Desenvolvedor Full Stack │
│         HotelConnect · Curitiba  │
│         CLT · Tempo integral     │
│                                  │
│         [  Ver vaga  ]           │
└─────────────────────────────────┘
```

Estados:
- `metadata === null` → "Vaga indisponível" (placeholder sem erro JS)
- `is_active === false` → overlay "Vaga encerrada" com opacidade reduzida, sem botão
- `is_active === true` → botão "Ver vaga" → `navigate('/jobs/{job_uuid}')`

Reutilizável em chat e no feed (importado de `@/components/chat/JobShareCard`).

#### 16. `MessageBubble` component

```
src/components/chat/MessageBubble.tsx
```

```typescript
function MessageBubble({ msg }: { msg: Message }) {
  return (
    <div className={`flex ${msg.is_mine ? 'justify-end' : 'justify-start'}`}>
      <div className={`max-w-[70%] rounded-2xl ...`}>
        {msg.type === 'job'       && <JobShareCard metadata={msg.metadata as JobMetadata} />}
        {msg.type === 'img-stub'  && <ImageStubPlaceholder />}
        {msg.type === 'file-stub' && <FileStubPlaceholder />}
        {(msg.type === 'text' || !msg.type) && <p>{msg.content}</p>}
        <p className="text-[10px] ...">
          {formatDistanceToNow(new Date(msg.created_at), { addSuffix: true, locale: ptBR })}
        </p>
      </div>
    </div>
  )
}
```

#### 17. Badge de mensagens no `Topbar` (AppLayout)

Substituir o `<button>` de Mensagens atual:

```typescript
function MessagesBadgeButton() {
  const navigate = useNavigate()
  const qc = useQueryClient()
  const { data } = useUnreadMessages()
  const count = data?.count ?? 0

  return (
    <button
      onClick={() => {
        qc.invalidateQueries({ queryKey: chatKeys.unread })
        navigate('/chat')
      }}
      className="relative flex items-center justify-center h-9 w-9 rounded-xl ..."
      aria-label="Mensagens"
    >
      <MessageSquare className="h-5 w-5" />
      {count > 0 && (
        <span className="absolute top-0.5 right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white animate-bounce-once">
          {count > 9 ? '9+' : count}
        </span>
      )}
    </button>
  )
}
```

A mesma lógica é aplicada ao `SideNavItem` de `/chat` na sidebar, passando `badge={count}`.

#### 18. UX — melhorias no `ChatPage`

- `formatTime()` substituído por `formatDistanceToNow` de `date-fns/ptBR` para < 24h, `format(date, 'dd/MM/yyyy')` para ≥ 24h.
- `Avatar` local (inline) substituído pelo componente `Avatar` de `@/components/ui/Avatar`.
- `ConversationItem`: destaque quando `unread_count > 0` — `bg-violet-50` + `font-semibold` no nome.
- `markRead.mutate()` chamado ao abrir conversa (já existe, confirmar que invalida `chatKeys.unread`).
- `useMarkRead.onSuccess`: adicionar `qc.invalidateQueries({ queryKey: chatKeys.unread })`.

#### 19. `ShareJobModal` — envio como card rico

```typescript
// chatService.sendJobShare(userUuid, jobUuid) — novo método
async sendJobShare(userUuid: string, jobUuid: string): Promise<Message> {
  const res = await api.post<ApiResponse<Message>>(`/chat/${userUuid}/messages`, {
    content: 'Compartilhou uma vaga com você',
    type:    'job',
    metadata: { job_uuid: jobUuid },
  })
  return res.data.data
}
```

`ShareJobModal` troca `sendMessage(text)` por `sendJobShare(uuid, job.uuid)`.

---

## Data Models

### `messages` (tabela — após migration)

| coluna | tipo | default |
|--------|------|---------|
| `type` | varchar(20) | `'text'` |
| `metadata` | json | null |

### `posts` (tabela — após migration, para compartilhamento no feed)

| coluna | tipo | default |
|--------|------|---------|
| `type` | varchar(30) | `'post'` |
| `metadata` | json | null |

---

## Error Handling

- `type='job'` com `job_uuid` inexistente → HTTP 422 "Vaga não encontrada" (via `firstOrFail` + handler global).
- `JobShareCard` com `metadata=null` → renderiza placeholder, sem throw.
- Fetch de `unread-count` com erro → `MessageBadge` mantém último valor (React Query stale).
- `FeedService.createJobSharePost` com falha → mensagem de chat **já foi salva**, o post no feed não é criado silenciosamente (log de erro, sem rollback da mensagem).

---

## Testing Strategy

- **Backend**: testes de Feature PHPUnit para `ChatController.send()` com `type='job'` verificando evento disparado, notificação criada e upsert.
- **Backend**: teste unitário de `NotificationService.newMessage()` cobrindo todos os tipos e o upsert.
- **Frontend**: sem novos testes unitários neste sprint (componentes são visuais); validação manual.
