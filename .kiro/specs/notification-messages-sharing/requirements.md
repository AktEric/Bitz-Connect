# Requirements Document

## Introduction

Este documento cobre o sprint "Correções do Sistema de Notificações e Compartilhamento" do HotelConnect — aplicação de rede profissional para o setor hoteleiro construída em Laravel (backend) e React/TypeScript (frontend).

O sprint abrange três frentes interligadas:

1. **Bugfix**: Corrigir o pipeline de eventos/listeners/notificações de chat que está quebrado (sem evento `MessageSent`, sem listener, `ChatController` não dispara eventos, `NotificationService.newMessage()` sem upsert).
2. **Feature de Mensagens**: Enriquecer o modelo `Message` com campo `type`, criar notificações de chat com upsert por remetente, adicionar badge de mensagens não lidas no menu e unificar o polling do frontend.
3. **Feature de Compartilhamento de Vaga**: Permitir que uma vaga seja compartilhada no chat como card rico e automaticamente publicada no feed do usuário com o mesmo card.

A arquitetura deve ser preparada para migração futura para WebSockets sem reescrita.

---

## Glossary

- **ChatController**: Controlador Laravel em `App\Http\Controllers\Api\V1\ChatController` responsável pelas rotas de chat.
- **Message**: Model Eloquent que representa uma mensagem direta entre dois usuários.
- **MessageType**: Enum de strings que classifica o conteúdo de uma mensagem (`text`, `job`, `post`, `profile`, `hotel`, `img-stub`, `file-stub`).
- **MessageSent**: Evento Laravel disparado pelo `ChatController` após persistir uma mensagem.
- **NotificationService**: Serviço central em `App\Services\NotificationService` responsável por toda criação de notificações.
- **MessageNotificationListener**: Listener Laravel que ouve `MessageSent` e aciona o `NotificationService`.
- **EventServiceProvider**: Provedor em `App\Providers\EventServiceProvider` que registra mapeamentos evento → listener.
- **UnifiedPollingHook**: Hook React (`usePolling`) que centraliza todos os intervalos de polling do frontend.
- **MessageBadge**: Componente React que exibe o contador de mensagens não lidas no Topbar.
- **JobShareCard**: Componente React que renderiza o card rico de vaga dentro de uma conversa de chat e no feed.
- **RichMessagePayload**: Estrutura JSON armazenada no campo `metadata` de `Message` quando `type !== 'text'`.
- **FeedService**: Serviço de domínio em `App\Domain\Feed\Services\FeedService` responsável pela criação de posts no feed.
- **Topbar**: Componente React dentro de `AppLayout` que contém ações rápidas no cabeçalho superior.
- **Sidebar**: Componente React dentro de `AppLayout` com navegação lateral.
- **useChat**: Hook React em `src/hooks/useChat.ts` que gerencia estado e queries de chat.
- **useNotifications**: Hook React em `src/hooks/useNotifications.ts` que gerencia notificações.

---

## Requirements

### Requisito 1 — Evento MessageSent e Pipeline de Notificações (Bugfix + Feature)

**User Story:** Como desenvolvedor, quero que o envio de uma mensagem dispare o pipeline completo de eventos do Laravel, para que as notificações de chat funcionem de forma desacoplada e extensível.

#### Critérios de Aceitação

1. WHEN o `ChatController` persiste uma nova mensagem via `Message::create()`, THE `ChatController` SHALL disparar o evento `MessageSent` antes de retornar a resposta HTTP, passando a instância de `Message` como payload do evento.

2. THE `MessageSent` event SHALL conter a instância de `Message` com as relações `sender` e `receiver` já carregadas via eager loading, de modo que `$event->message->sender` e `$event->message->receiver` retornem instâncias de `User` sem queries adicionais.

3. WHEN o evento `MessageSent` é disparado, THE `MessageNotificationListener` SHALL ser invocado de forma síncrona dentro do mesmo ciclo de requisição HTTP, sem despacho para fila.

4. THE `EventServiceProvider` SHALL registrar o listener `MessageNotificationListener::class` para o evento `MessageSent::class` usando `Event::listen()` no método `boot()`.

5. WHEN o `MessageNotificationListener` é invocado, THE listener SHALL chamar o método `newMessage()` do `NotificationService`, passando o `recipient` (receiver da mensagem), o `sender`, uma `$description` de até 80 caracteres extraída do `content` da mensagem, e o `type` da mensagem.

6. IF a `Message` possui `type` diferente de `text`, THEN THE `MessageNotificationListener` SHALL passar o `type` como parâmetro explícito ao `NotificationService` para que a `$description` seja composta conforme as regras de tipo definidas no Requisito 2.

---

### Requisito 2 — Notificação de Nova Mensagem com Upsert por Remetente (Feature)

**User Story:** Como usuário, quero ver uma única notificação por remetente de chat que se atualiza com a mensagem mais recente, para não ser sobrecarregado por notificações duplicadas do mesmo contato.

#### Critérios de Aceitação

1. WHEN o `NotificationService.newMessage()` é chamado para um par `(recipient, sender)`, THE `NotificationService` SHALL verificar se já existe uma `Notification` não lida (`read_at IS NULL`) do tipo `new_message` com `actor_id = sender.id` e `user_id = recipient.id`.

2. IF uma notificação não lida do mesmo remetente já existe, THEN THE `NotificationService` SHALL atualizar o campo `description` com o novo trecho (composto conforme as regras dos critérios 5, 6 e 7), atualizar o campo `updated_at` para o momento atual, e manter o campo `title` inalterado, em vez de criar um novo registro.

3. IF nenhuma notificação não lida do mesmo remetente existe, THEN THE `NotificationService` SHALL criar uma nova `Notification` com `type = 'new_message'`, `category = 'message'`, `title = "{sender.name} enviou uma mensagem"`, `description` composta conforme as regras dos critérios 5, 6 e 7, e `link = "/chat/{sender.uuid}"`.

4. WHEN uma notificação de `new_message` é marcada como lida (`read_at` preenchido), THE `NotificationService` SHALL tratar a próxima mensagem do mesmo remetente como nova (criar nova notificação em vez de fazer upsert sobre a lida).

5. IF o `type` da mensagem é `text` e o `content` exceder 100 caracteres, THEN THE `NotificationService` SHALL truncar o `content` para 100 caracteres e acrescentar `"..."` formando a `description`.

6. IF o `type` da mensagem é `job`, THEN THE `NotificationService` SHALL compor a `description` como `"Compartilhou uma vaga com você"` em vez de usar o `content` da mensagem.

7. IF o `type` da mensagem é `img-stub` ou `file-stub`, THEN THE `NotificationService` SHALL compor a `description` como `"Enviou uma mídia"` em vez de usar o `content` da mensagem.

---

### Requisito 3 — Campo `type` no Model Message (Feature)

**User Story:** Como desenvolvedor, quero que o modelo `Message` tenha um campo `type` para suportar diferentes tipos de conteúdo no chat sem alterar a estrutura das mensagens de texto existentes.

#### Critérios de Aceitação

1. THE sistema SHALL possuir uma migration que adiciona a coluna `type` do tipo `VARCHAR(20)` com valor padrão `'text'` e constraint `NOT NULL` na tabela `messages`.

2. THE `Message` model SHALL incluir `type` na lista `$fillable` e no array `$casts` mapeado para o enum de string `MessageType`.

3. THE `MessageType` SHALL ser um enum PHP com os valores: `text`, `job`, `post`, `profile`, `hotel`, `img-stub`, `file-stub`.

4. THE sistema SHALL possuir uma migration que adiciona a coluna `metadata` do tipo `JSON` nullable na tabela `messages`, destinada a armazenar o payload rico (ex.: dados da vaga) quando `type !== 'text'`.

5. THE `Message` model SHALL incluir `metadata` na lista `$fillable` e castado como `array` no `$casts`.

6. WHEN o `ChatController` recebe uma requisição de envio de mensagem com `type = 'text'` (padrão), THE `ChatController` SHALL aceitar e persistir a mensagem sem exigir o campo `metadata`.

7. WHEN o `ChatController` recebe uma requisição com `type = 'job'`, THE `ChatController` SHALL validar que o campo `metadata` contém pelo menos `job_uuid` como string obrigatória.

---

### Requisito 4 — Badge de Mensagens Não Lidas no Topbar (Feature)

**User Story:** Como usuário, quero ver um contador de mensagens não lidas no botão Mensagens do Topbar, para saber imediatamente quando recebi novas mensagens sem precisar abrir o chat.

#### Critérios de Aceitação

1. THE `ChatController` SHALL expor um endpoint `GET /api/v1/chat/unread-count` que retorna o total de mensagens não lidas do usuário autenticado como `{ "data": { "count": <integer> } }`.

2. WHEN o `Topbar` é renderizado e o fetch do contador ainda está pendente, THE `MessageBadge` SHALL não exibir nenhum indicador visual. WHEN o fetch conclui com sucesso, THE `MessageBadge` SHALL exibir o contador retornado pelo endpoint.

3. WHILE o contador de mensagens não lidas for maior que zero e menor ou igual a 9, THE `MessageBadge` SHALL exibir o número exato. WHILE o contador for maior ou igual a 10, THE `MessageBadge` SHALL exibir `"9+"`.

4. WHILE o contador de mensagens não lidas for zero, THE `MessageBadge` SHALL não renderizar nenhum indicador visual sobre o ícone de mensagens.

5. THE hook responsável pelo `MessageBadge` SHALL buscar o contador com intervalo de polling de 30 segundos, consistente com o intervalo usado por `useUnreadCount` das notificações gerais.

6. WHEN o usuário navega para `/chat`, THE `Topbar` SHALL chamar `queryClient.invalidateQueries` na chave da query do contador de mensagens não lidas para forçar um refetch imediato.

7. THE endpoint `GET /api/v1/chat/unread-count` SHALL exigir autenticação via Sanctum e retornar HTTP 401 para requisições sem token válido.

8. IF o fetch do contador falhar por erro de rede ou HTTP 5xx, THEN THE `MessageBadge` SHALL manter o último valor exibido (ou zero se nunca carregou) sem exibir mensagem de erro ao usuário.

---

### Requisito 5 — Polling Unificado (Refactor + Feature)

**User Story:** Como desenvolvedor, quero que o frontend use um mecanismo de polling centralizado para mensagens e conversas, para facilitar a migração futura para WebSockets e eliminar intervalos duplicados.

#### Critérios de Aceitação

1. THE `useChat` hook SHALL encapsular a lógica de `refetchInterval` em uma constante centralizada, de modo que a mudança do intervalo em um único lugar afete todos os lugares que usam polling de chat.

2. WHEN a aba do browser está em segundo plano (`document.hidden === true`), THE `useMessages` hook SHALL pausar o polling de mensagens individuais, mantendo `refetchIntervalInBackground: false`.

3. THE `useConversations` hook SHALL manter polling ativo com intervalo de 30 segundos quando a aba está em primeiro plano.

4. THE `useMessages` hook SHALL manter polling ativo com intervalo de 5 segundos quando a aba está em primeiro plano e uma conversa está aberta.

5. THE sistema SHALL expor constantes de intervalo de polling (`CHAT_MESSAGES_POLL_MS` e `CHAT_CONVERSATIONS_POLL_MS`) em um arquivo de configuração de constantes do frontend (`src/config/polling.ts`), importadas pelos hooks.

6. WHERE WebSockets forem adotados futuramente, THE `src/config/polling.ts` SHALL servir como ponto único de controle para desabilitar polling sem alterações nos hooks consumidores.

---

### Requisito 6 — Card Rico de Vaga no Chat (Feature)

**User Story:** Como usuário, quero poder enviar uma vaga como card rico em uma conversa, para que o destinatário visualize as informações da vaga diretamente no chat sem precisar sair da conversa.

#### Critérios de Aceitação

1. WHEN o `ChatController` recebe uma mensagem com `type = 'job'` e `metadata.job_uuid` válido, THE `ChatController` SHALL carregar os dados da vaga e incluir `title`, `hotel_name`, `location`, `contract_type` e `is_active` no campo `metadata` da resposta serializada da mensagem.

2. THE `JobShareCard` component SHALL renderizar, a partir do `metadata` de uma mensagem do tipo `job`, o título da vaga, o nome do hotel, a localização e o tipo de contrato em um card visualmente destacado dentro do balão de chat.

3. IF o campo `is_active` do `metadata` for `false`, THEN THE `JobShareCard` SHALL exibir um overlay com o texto "Vaga encerrada" sobre o card, mantendo o conteúdo visível porém com opacidade reduzida.

4. IF o campo `is_active` do `metadata` for `true`, THEN THE `JobShareCard` SHALL exibir um botão "Ver vaga" que navega para `/jobs/{metadata.job_uuid}`.

5. THE `JobShareCard` SHALL tratar o caso em que o `metadata` está ausente ou malformado exibindo um placeholder com a mensagem "Vaga indisponível" sem lançar erros de JavaScript.

6. THE `Message` type em `api.types.ts` SHALL incluir os campos opcionais `type: MessageType` e `metadata: Record<string, unknown> | null` para suportar a tipagem do card rico.

---

### Requisito 7 — Publicação Automática no Feed ao Compartilhar Vaga (Feature)

**User Story:** Como usuário, quero que ao compartilhar uma vaga no chat ela seja automaticamente publicada no meu feed como post, para que minha rede profissional também possa ver a oportunidade.

#### Critérios de Aceitação

1. WHEN o `ChatController` persiste uma mensagem com `type = 'job'`, THE `ChatController` SHALL invocar o `FeedService.createJobSharePost()` para criar um post automático no feed do remetente com referência à vaga compartilhada.

2. THE post criado automaticamente pelo `FeedService.createJobSharePost()` SHALL ter `content` composto pelo template: `"Compartilhei uma vaga: {job_title} — {hotel_name}"`, `type = 'job_share'` e `metadata` com os mesmos dados da vaga.

3. WHEN o post de compartilhamento de vaga é exibido no feed, THE `JobShareCard` component SHALL ser reutilizado para renderizar o card rico da vaga dentro do post, mantendo consistência visual entre chat e feed.

4. IF o `job_uuid` enviado pelo cliente não corresponder a nenhuma vaga existente, THEN THE `ChatController` SHALL retornar HTTP 422 com a mensagem de erro "Vaga não encontrada" e não persistir a mensagem nem criar o post no feed.

5. THE `FeedService` SHALL expor o método `createJobSharePost(User $author, array $jobData): Post` como ponto único de criação de posts automáticos de compartilhamento de vaga.

6. WHEN o `FeedService.createJobSharePost()` é chamado, THE `FeedService` SHALL disparar o evento `PostCreated` (se existir) ou equivalente, para que o pipeline de notificações de feed funcione normalmente.

---

### Requisito 8 — Tipos de Mensagem: Estrutura para img-stub e file-stub (Feature)

**User Story:** Como desenvolvedor, quero que a estrutura de mensagens suporte os tipos `img-stub` e `file-stub` como placeholders, para que o upload real de mídia possa ser implementado num sprint futuro sem quebrar a API.

#### Critérios de Aceitação

1. WHEN o `ChatController` recebe uma mensagem com `type = 'img-stub'` ou `type = 'file-stub'`, THE `ChatController` SHALL aceitar e persistir a mensagem sem validar nem processar o campo `metadata`.

2. THE `ChatController` SHALL retornar a mensagem serializada com `type` e `metadata` inalterados quando o tipo for `img-stub` ou `file-stub`.

3. THE frontend SHALL renderizar mensagens do tipo `img-stub` com um placeholder visual de imagem (ícone de imagem + texto "Imagem em breve") no lugar do `JobShareCard`.

4. THE frontend SHALL renderizar mensagens do tipo `file-stub` com um placeholder visual de arquivo (ícone de arquivo + texto "Arquivo em breve") no lugar do `JobShareCard`.

5. THE sistema de tipos TypeScript SHALL incluir `MessageType` como union type: `'text' | 'job' | 'post' | 'profile' | 'hotel' | 'img-stub' | 'file-stub'` em `api.types.ts`, utilizado tanto no modelo `Message` quanto nos componentes de renderização.

---

### Requisito 9 — UX: Horário Relativo, Avatar e Highlight de Não Lidos (Feature)

**User Story:** Como usuário, quero que a interface do chat exiba horários relativos, avatares dos remetentes e destaque visual nas mensagens e conversas não lidas, para uma experiência mais moderna e informativa.

#### Critérios de Aceitação

1. THE chat SHALL exibir o horário de cada mensagem no formato relativo (ex.: "há 2 minutos", "há 1 hora") usando a biblioteca `date-fns` com locale `ptBR`, consistente com o padrão já adotado em `NotificationBell.tsx`.

2. WHEN o horário da mensagem for inferior a 24 horas em relação ao momento atual, THE chat SHALL exibir o tempo relativo (ex.: "há 5 minutos").

3. WHEN o horário da mensagem for igual ou superior a 24 horas em relação ao momento atual, THE chat SHALL exibir a data no formato `dd/MM/yyyy`.

4. THE lista de conversas SHALL exibir o avatar do interlocutor usando o componente `Avatar` existente em `src/components/ui/Avatar.tsx`, com fallback para iniciais do nome quando `avatar_url` for `null`.

5. WHILE uma conversa tiver `unread_count > 0`, THE lista de conversas SHALL aplicar um destaque visual (fundo levemente colorido e nome do interlocutor em `font-semibold`) na linha correspondente na lista de conversas.

6. WHILE uma mensagem recebida ainda não tiver `read_at` preenchido, THE área de chat aberta SHALL renderizar a mensagem com fundo ligeiramente diferenciado para indicar que ainda não foi lida pelo remetente.

7. THE componente de área de chat SHALL chamar o endpoint `PATCH /api/v1/chat/{userUuid}/read` automaticamente quando o usuário abre uma conversa, de modo a marcar as mensagens como lidas e zerar o `unread_count` para aquele interlocutor.

---

### Requisito 10 — Qualidade: Lógica Centralizada e Preparação para WebSockets (Refactor)

**User Story:** Como desenvolvedor, quero que toda a lógica de notificações de chat esteja centralizada e sem duplicação, e que a arquitetura esteja preparada para substituição do polling por WebSockets sem reescrita de componentes.

#### Critérios de Aceitação

1. THE `NotificationService` SHALL ser o único lugar no backend onde notificações do tipo `message` são criadas — nenhum controller ou outro service SHALL criar notificações de mensagem diretamente.

2. THE `ChatController` SHALL não conter lógica de notificação — toda essa responsabilidade SHALL ser delegada ao `MessageNotificationListener` via evento `MessageSent`.

3. THE hooks `useChat` e `useNotifications` no frontend SHALL ser os únicos locais onde `refetchInterval` é definido para dados de chat e notificações, respectivamente — nenhum componente de UI SHALL definir seu próprio intervalo de polling para esses dados.

4. WHERE WebSockets forem adotados futuramente, THE `useChat` hook SHALL suportar a desativação do polling via `refetchInterval: false` sem alterações nos componentes consumidores, bastando modificar as constantes em `src/config/polling.ts`.

5. THE `formatMessage()` helper do `ChatController` SHALL incluir os campos `type` e `metadata` na resposta serializada de toda mensagem, garantindo que novos tipos de mensagem sejam automaticamente transmitidos ao frontend sem alterações adicionais no serializer.

6. THE `JobShareCard` component SHALL ser importável tanto pela página de chat quanto pelos componentes de post do feed, sem duplicação de lógica de renderização do card de vaga.
