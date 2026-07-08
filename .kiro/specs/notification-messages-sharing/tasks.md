# Implementation Plan: Notificações, Mensagens e Compartilhamento

## Overview

Sprint de correção e melhoria do sistema de notificações, chat e compartilhamento de vagas do HotelConnect. Cobre: pipeline de eventos para notificações de chat, badge de mensagens não lidas, card rico de vagas no chat e no feed, tipos de mensagem e melhorias de UX.

## Tasks

- [x] 1. Migration — adicionar `type` e `metadata` na tabela `messages`
  - Criar migration `add_type_metadata_to_messages_table` com coluna `type` varchar(20) default `'text'` NOT NULL e coluna `metadata` json nullable
  - Criar enum PHP `app/Enums/MessageType.php` com valores `text`, `job`, `post`, `profile`, `hotel`, `img-stub`, `file-stub`
  - Atualizar `Message` model: adicionar `type` e `metadata` em `$fillable` e `$casts` (type → MessageType::class, metadata → 'array')
  - Rodar `php artisan migrate` e confirmar que mensagens existentes ficam com `type='text'`
  - **Arquivos**: `database/migrations/`, `app/Enums/MessageType.php`, `app/Models/Message.php`

- [x] 2. Evento `MessageSent` e Listener `SendMessageNotification`
  - Criar `app/Events/Chat/MessageSent.php` com propriedade pública `readonly Message $message`
  - Criar `app/Listeners/Chat/SendMessageNotification.php` que injeta `NotificationService` e chama `newMessage()` com `recipient`, `sender`, `type` e `content`
  - Registrar `MessageSent::class → SendMessageNotification::class` no `EventServiceProvider::boot()` via `Event::listen()`
  - Em `ChatController::send()`, após `$message->load(['sender', 'receiver'])`, disparar `event(new MessageSent($message))`
  - **Arquivos**: `app/Events/Chat/MessageSent.php`, `app/Listeners/Chat/SendMessageNotification.php`, `app/Providers/EventServiceProvider.php`, `app/Http/Controllers/Api/V1/ChatController.php`
  - **Depende de**: Task 1

- [x] 3. `NotificationService::newMessage()` com upsert por remetente
  - Atualizar assinatura do método para receber `MessageType $type` e `?string $content`
  - Implementar lógica de descrição por tipo: `job` → "Compartilhou uma vaga com você"; `img-stub`/`file-stub` → "Enviou uma mídia"; `text` → truncar `content` em 100 chars com "…"
  - Implementar upsert: verificar `Notification` não lida (`read = false`) com `actor_id = sender.id`, `user_id = recipient.id`, `type = 'new_message'`; se existir, atualizar `description` e `updated_at`; caso contrário, criar nova
  - Ajustar o listener `SendMessageNotification` para passar os novos parâmetros
  - **Arquivos**: `app/Services/NotificationService.php`, `app/Listeners/Chat/SendMessageNotification.php`
  - **Depende de**: Task 2

- [x] 4. Endpoint `GET /chat/unread-count` e suporte a `type` no envio
  - Adicionar método `unreadCount(Request $request): JsonResponse` no `ChatController` que conta `Message` com `receiver_id = auth()->id()` e `read_at = null`
  - Registrar rota `GET /chat/unread-count` em `routes/api.php` dentro do grupo autenticado, antes do model binding de `{user}` para evitar conflito
  - Atualizar `ChatController::send()`: aceitar campos `type` (default `'text'`) e `metadata`; validar `metadata.job_uuid` obrigatório quando `type='job'`; retornar HTTP 422 se `job_uuid` não encontrado
  - Quando `type='job'`: buscar `Job` pelo `job_uuid` e montar `metadata` enriquecido com `title`, `hotel_name`, `hotel_logo`, `location`, `contract_type`, `is_active`
  - Atualizar `formatMessage()` para incluir `type` (como string) e `metadata` na resposta serializada
  - **Arquivos**: `app/Http/Controllers/Api/V1/ChatController.php`, `routes/api.php`
  - **Depende de**: Task 1

- [x] 5. Migration `posts` e publicação no feed ao compartilhar vaga
  - Criar migration `add_type_metadata_to_posts_table` com coluna `type` varchar(30) default `'post'` e coluna `metadata` json nullable
  - Atualizar `Post` model: adicionar `type` e `metadata` em `$fillable` e `$casts`
  - Adicionar método `createJobSharePost(User $author, array $jobData): Post` no `FeedService` que cria post com `content` template, `type='job_share'` e `metadata` da vaga
  - Em `ChatController::send()`, quando `type='job'`, chamar `FeedService::createJobSharePost()` após salvar a mensagem (falha do feed não reverte a mensagem)
  - **Arquivos**: `database/migrations/`, `app/Models/Post.php`, `app/Domain/Feed/Services/FeedService.php`, `app/Http/Controllers/Api/V1/ChatController.php`
  - **Depende de**: Task 4

- [x] 6. Frontend — `polling.ts` e ajuste dos hooks de chat
  - Criar `frontend/src/config/polling.ts` com constantes `CHAT_MESSAGES_POLL_MS = 5_000`, `CHAT_CONVERSATIONS_POLL_MS = 30_000`, `UNREAD_MESSAGES_POLL_MS = 30_000`
  - Atualizar `useConversations` para usar `CHAT_CONVERSATIONS_POLL_MS` (era 4_000) e `useMessages` para usar `CHAT_MESSAGES_POLL_MS` (era 3_000)
  - Adicionar `useUnreadMessages()` hook em `useChat.ts` com query key `['chat', 'unread-count']` e polling de `UNREAD_MESSAGES_POLL_MS`
  - Adicionar `chatService.getUnreadCount()` em `chat.service.ts` chamando `GET /chat/unread-count`
  - Atualizar `useMarkRead.onSuccess` para invalidar também `chatKeys.unread`
  - **Arquivos**: `frontend/src/config/polling.ts`, `frontend/src/hooks/useChat.ts`, `frontend/src/services/chat.service.ts`
  - **Depende de**: Task 4

- [x] 7. Frontend — tipos em `api.types.ts`
  - Adicionar `MessageType` como union type: `'text' | 'job' | 'post' | 'profile' | 'hotel' | 'img-stub' | 'file-stub'`
  - Adicionar interface `JobMetadata` com campos `job_uuid`, `title`, `hotel_name`, `hotel_logo`, `location`, `contract_type`, `is_active`
  - Adicionar campos `type: MessageType` e `metadata: JobMetadata | Record<string, unknown> | null` na interface `Message`
  - Atualizar o `Message` otimista em `useSendMessage` para incluir `type: 'text'` e `metadata: null`
  - **Arquivos**: `frontend/src/types/api.types.ts`, `frontend/src/hooks/useChat.ts`

- [x] 8. Componente `JobShareCard`
  - Criar `frontend/src/components/chat/JobShareCard.tsx` com props `metadata: JobMetadata | null` e `isMine?: boolean`
  - Implementar layout: logo do hotel, título da vaga, nome do hotel, localização, tipo de contrato
  - `metadata === null` → renderizar "Vaga indisponível" sem erro JS
  - `is_active === false` → overlay "Vaga encerrada" com `opacity-60`, sem botão
  - `is_active === true` → botão "Ver vaga" com `useNavigate` → `/jobs/{job_uuid}`
  - Estilizar de forma consistente para uso em chat e feed
  - **Arquivos**: `frontend/src/components/chat/JobShareCard.tsx`
  - **Depende de**: Task 7

- [x] 9. Componente `MessageBubble` e placeholders de mídia
  - Criar `frontend/src/components/chat/MessageBubble.tsx` extraindo a lógica de renderização de `MessageWindow`
  - Adicionar switch por `msg.type`: `job` → `<JobShareCard>`, `img-stub` → placeholder com ícone + "Imagem em breve", `file-stub` → placeholder com ícone + "Arquivo em breve", demais → texto
  - Substituir `formatTime()` por `formatDistanceToNow` (date-fns/ptBR) para < 24h e `format(date, 'dd/MM/yyyy')` para ≥ 24h
  - Substituir o `<Avatar>` inline do `ChatPage` pelo componente `Avatar` de `@/components/ui/Avatar`
  - Atualizar `MessageWindow` em `ChatPage.tsx` para usar `<MessageBubble>`
  - **Arquivos**: `frontend/src/components/chat/MessageBubble.tsx`, `frontend/src/pages/chat/ChatPage.tsx`
  - **Depende de**: Task 7, Task 8

- [x] 10. Badge de mensagens no Topbar e Sidebar
  - Criar componente interno `MessagesBadgeButton` no `AppLayout.tsx` usando `useUnreadMessages()` para obter o contador
  - Exibir badge vermelho com contador quando `count > 0`; formatar como `'9+'` quando `count >= 10`
  - No `onClick`, invalidar `chatKeys.unread` antes de navegar para `/chat`
  - Substituir o `<button>` atual de Mensagens no `Topbar` pelo `MessagesBadgeButton`
  - Passar `badge={unreadMessages}` para o `SideNavItem` de `/chat` na `Sidebar` (o `SideNavItem` já suporta a prop `badge`)
  - **Arquivos**: `frontend/src/components/common/AppLayout.tsx`
  - **Depende de**: Task 6

- [x] 11. UX — destaques visuais na lista de conversas
  - Atualizar `ConversationItem` no `ChatPage`: quando `unread_count > 0`, aplicar `bg-violet-50` no container e `font-semibold` no nome
  - Em `MessageBubble` para mensagens recebidas com `read_at === null && !is_mine`, aplicar `bg-blue-50` em vez de `bg-gray-100`
  - Confirmar que ao abrir conversa `markRead.mutate()` invalida `chatKeys.unread` (coberto pela Task 6)
  - **Arquivos**: `frontend/src/pages/chat/ChatPage.tsx`, `frontend/src/components/chat/MessageBubble.tsx`
  - **Depende de**: Task 9

- [x] 12. `ShareJobModal` — envio como card rico
  - Adicionar `chatService.sendJobShare(userUuid: string, jobUuid: string): Promise<Message>` no `chat.service.ts` enviando `{ content: 'Compartilhou uma vaga com você', type: 'job', metadata: { job_uuid } }`
  - Atualizar `ShareJobModal.tsx`: trocar `sendMessage(buildMessage(...))` por `sendJobShare(other.uuid, job.uuid)`
  - Após envio bem-sucedido, invalidar `chatKeys.conversations` e `chatKeys.unread`
  - **Arquivos**: `frontend/src/services/chat.service.ts`, `frontend/src/components/job/ShareJobModal.tsx`
  - **Depende de**: Task 6, Task 7

- [x] 13. Integrar `JobShareCard` no feed
  - Verificar componentes de post no feed em `frontend/src/components/feed/` para identificar onde renderizar o card
  - Importar e renderizar `<JobShareCard>` quando o post tiver `type='job_share'` e `metadata` com dados da vaga
  - Garantir que o tipo `job_share` não cause erros nos componentes existentes de feed
  - **Arquivos**: `frontend/src/components/feed/` (componentes de post existentes)
  - **Depende de**: Task 8, Task 5

## Task Dependency Graph

```json
{
  "waves": [
    { "wave": 1, "tasks": ["1", "7"] },
    { "wave": 2, "tasks": ["2", "4", "8"] },
    { "wave": 3, "tasks": ["3", "5", "6", "9"] },
    { "wave": 4, "tasks": ["10", "11", "12"] },
    { "wave": 5, "tasks": ["13"] }
  ]
}
```

## Notes

- As Tasks 1–5 são exclusivamente backend (Laravel). As Tasks 6–13 são frontend (React/TypeScript). Podem ser executadas em paralelo após as dependências serem satisfeitas.
- O model `Notification` usa coluna `read` (boolean), não `read_at`. O upsert no `NotificationService` deve filtrar por `where('read', false)`.
- `ShareJobModal` já existe em `frontend/src/components/job/ShareJobModal.tsx` — apenas adaptar o método de envio.
- O `SideNavItem` em `AppLayout.tsx` já aceita a prop `badge` — apenas passar o valor correto.
- Ao adicionar `type` e `metadata` em `posts`, verificar se o `PostResource` ou serialização existente precisa ser atualizado para não quebrar o feed atual.
