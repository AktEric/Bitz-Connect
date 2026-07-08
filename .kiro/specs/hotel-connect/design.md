# Design Document — HotelConnect MVP

## Overview

HotelConnect é uma rede social profissional para o setor hoteleiro. A arquitetura é construída sobre
uma API REST em Laravel 12 consumida por um SPA em React 19 + TypeScript.

A filosofia arquitetural central é **separação de responsabilidades com orientação a domínio**:
cada camada tem um papel bem definido, nenhuma lógica de negócio vive nos Controllers, e toda
evolução futura (IA, CRM, automações) pode ser adicionada sem reescrever o que já existe.

---

## Architecture

### Visão Geral

```
┌─────────────────────────────────────────────────────────────┐
│                        React SPA (Vite)                     │
│  pages / components / hooks / services / contexts / types   │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTPS / JSON REST
┌────────────────────────▼────────────────────────────────────┐
│                      NGINX (proxy)                          │
└──────────┬─────────────────────────────────┬────────────────┘
           │ :8000                           │ :3000
┌──────────▼──────────┐           ┌──────────▼──────────┐
│   Laravel 12 API    │           │   Node / Vite Dev    │
│   PHP 8.4           │           │   (apenas dev)       │
└──────────┬──────────┘           └─────────────────────-┘
           │
    ┌──────┴──────┐
    ▼             ▼
PostgreSQL      Redis
(dados)         (cache / sessões / filas)
```

### Princípios Arquiteturais

- **SOLID**: cada classe tem uma única responsabilidade, depende de abstrações (interfaces).
- **Clean Architecture**: Controllers apenas orquestram; Services contêm regras de negócio.
- **Repository Pattern**: acesso a dados abstraído via interfaces — facilita troca de fonte de dados.
- **DTO (Data Transfer Objects)**: dados transitam entre camadas como objetos tipados, nunca arrays crus.
- **Actions**: operações atômicas e reutilizáveis (ex: `CreatePostAction`, `PublishJobAction`).
- **Policies**: toda autorização centralizada em Laravel Policies, nunca no Controller.

---

## Components and Interfaces

### Backend — Estrutura de Pastas

```
app/
├── Domain/
│   ├── Auth/
│   │   ├── Actions/
│   │   │   ├── RegisterUserAction.php
│   │   │   ├── LoginUserAction.php
│   │   │   └── ResetPasswordAction.php
│   │   ├── DTOs/
│   │   │   ├── RegisterUserDTO.php
│   │   │   └── LoginDTO.php
│   │   └── Services/
│   │       └── AuthService.php
│   │
│   ├── User/
│   │   ├── Actions/
│   │   │   ├── UpdateProfileAction.php
│   │   │   └── UploadAvatarAction.php
│   │   ├── DTOs/
│   │   │   └── UpdateProfileDTO.php
│   │   ├── Repositories/
│   │   │   ├── UserRepositoryInterface.php
│   │   │   └── EloquentUserRepository.php
│   │   └── Services/
│   │       └── UserService.php
│   │
│   ├── Hotel/
│   │   ├── Actions/
│   │   │   ├── CreateHotelAction.php
│   │   │   └── UpdateHotelAction.php
│   │   ├── DTOs/
│   │   │   ├── CreateHotelDTO.php
│   │   │   └── UpdateHotelDTO.php
│   │   ├── Repositories/
│   │   │   ├── HotelRepositoryInterface.php
│   │   │   └── EloquentHotelRepository.php
│   │   └── Services/
│   │       └── HotelService.php
│   │
│   ├── Feed/
│   │   ├── Actions/
│   │   │   ├── CreatePostAction.php
│   │   │   ├── LikePostAction.php
│   │   │   └── CreateCommentAction.php
│   │   ├── DTOs/
│   │   │   ├── CreatePostDTO.php
│   │   │   └── CreateCommentDTO.php
│   │   ├── Repositories/
│   │   │   ├── PostRepositoryInterface.php
│   │   │   └── EloquentPostRepository.php
│   │   └── Services/
│   │       └── FeedService.php
│   │
│   ├── Job/
│   │   ├── Actions/
│   │   │   ├── PublishJobAction.php
│   │   │   ├── ApplyToJobAction.php
│   │   │   └── UpdateJobStatusAction.php
│   │   ├── DTOs/
│   │   │   ├── CreateJobDTO.php
│   │   │   └── ApplyJobDTO.php
│   │   ├── Repositories/
│   │   │   ├── JobRepositoryInterface.php
│   │   │   └── EloquentJobRepository.php
│   │   └── Services/
│   │       └── JobService.php
│   │
│   └── Search/
│       ├── Services/
│       │   └── SearchService.php
│       └── DTOs/
│           └── SearchQueryDTO.php
│
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/
│   │   │   ├── AuthController.php
│   │   │   ├── UserController.php
│   │   │   ├── HotelController.php
│   │   │   ├── FeedController.php
│   │   │   ├── JobController.php
│   │   │   └── SearchController.php
│   ├── Requests/
│   │   ├── Auth/
│   │   ├── User/
│   │   ├── Hotel/
│   │   ├── Feed/
│   │   └── Job/
│   ├── Resources/
│   │   ├── UserResource.php
│   │   ├── HotelResource.php
│   │   ├── PostResource.php
│   │   ├── CommentResource.php
│   │   └── JobResource.php
│   └── Middleware/
│       └── ForceJsonResponse.php
│
├── Models/
│   ├── User.php
│   ├── Hotel.php
│   ├── HotelUser.php
│   ├── Post.php
│   ├── Comment.php
│   ├── Like.php
│   ├── Job.php
│   ├── JobApplication.php
│   └── Experience.php
│
├── Policies/
│   ├── HotelPolicy.php
│   ├── PostPolicy.php
│   ├── CommentPolicy.php
│   └── JobPolicy.php
│
└── Providers/
    ├── AppServiceProvider.php
    └── RepositoryServiceProvider.php   ← bind interfaces → implementações
```

### Frontend — Estrutura de Pastas

```
src/
├── components/
│   ├── ui/          ← Shadcn/UI components (auto-gerados)
│   ├── common/      ← Avatar, Badge, Card, EmptyState, etc.
│   ├── feed/        ← PostCard, CommentList, LikeButton, etc.
│   ├── hotel/       ← HotelCard, HotelHeader, etc.
│   └── job/         ← JobCard, JobFilters, ApplicationStatus, etc.
├── pages/
│   ├── auth/        ← Login, Register, ForgotPassword, ResetPassword
│   ├── feed/        ← FeedPage
│   ├── hotel/       ← HotelProfilePage, CreateHotelPage
│   ├── jobs/        ← JobsPage, JobDetailPage
│   ├── profile/     ← ProfilePage, EditProfilePage
│   └── search/      ← SearchPage
├── hooks/
│   ├── useAuth.ts
│   ├── useFeed.ts
│   ├── useHotel.ts
│   ├── useJobs.ts
│   └── useSearch.ts
├── services/
│   ├── api.ts       ← instância Axios configurada
│   ├── auth.service.ts
│   ├── user.service.ts
│   ├── hotel.service.ts
│   ├── feed.service.ts
│   ├── job.service.ts
│   └── search.service.ts
├── contexts/
│   └── AuthContext.tsx
├── routes/
│   ├── index.tsx
│   ├── PrivateRoute.tsx
│   └── GuestRoute.tsx
├── types/
│   ├── auth.types.ts
│   ├── user.types.ts
│   ├── hotel.types.ts
│   ├── feed.types.ts
│   ├── job.types.ts
│   └── api.types.ts  ← ApiResponse<T>, PaginatedResponse<T>
└── utils/
    ├── format.ts
    ├── validators.ts
    └── cn.ts        ← classnames helper
```

---

## Data Models

### Modelagem do Banco de Dados

```mermaid
erDiagram
    users {
        bigint id PK
        uuid uuid UK
        string name
        string email UK
        string password
        string avatar_path
        string banner_path
        text bio
        string city
        string state
        string website
        json social_links
        enum role "user|platform_admin"
        timestamp email_verified_at
        timestamps
        softDeletes
    }

    experiences {
        bigint id PK
        uuid uuid UK
        bigint user_id FK
        string title
        string company
        date started_at
        date ended_at
        text description
        timestamps
    }

    hotels {
        bigint id PK
        uuid uuid UK
        string name
        string slug UK
        text description
        string logo_path
        string cover_path
        string city
        string state
        string country
        string website
        string phone
        string contact_email
        timestamps
        softDeletes
    }

    hotel_users {
        bigint id PK
        bigint hotel_id FK
        bigint user_id FK
        enum role "admin"
        timestamps
    }

    posts {
        bigint id PK
        uuid uuid UK
        bigint user_id FK
        bigint hotel_id FK "nullable"
        text content
        boolean edited
        timestamps
        softDeletes
    }

    likes {
        bigint id PK
        bigint user_id FK
        bigint post_id FK
        timestamps
    }

    comments {
        bigint id PK
        uuid uuid UK
        bigint post_id FK
        bigint user_id FK
        text content
        timestamps
        softDeletes
    }

    jobs {
        bigint id PK
        uuid uuid UK
        bigint hotel_id FK
        string title
        text description
        text requirements
        text benefits
        enum contract_type "clt|pj|freelance|internship"
        enum modality "on_site|remote|hybrid"
        string city
        string state
        string salary_range "nullable"
        enum status "active|paused|closed"
        timestamps
        softDeletes
    }

    job_applications {
        bigint id PK
        uuid uuid UK
        bigint job_id FK
        bigint user_id FK
        text cover_letter "nullable"
        enum status "pending|viewed|rejected|hired"
        timestamps
    }

    users ||--o{ experiences : "has"
    users ||--o{ hotel_users : "manages"
    hotels ||--o{ hotel_users : "has admins"
    users ||--o{ posts : "creates"
    hotels ||--o{ posts : "publishes"
    posts ||--o{ likes : "receives"
    posts ||--o{ comments : "receives"
    users ||--o{ comments : "writes"
    hotels ||--o{ jobs : "publishes"
    users ||--o{ job_applications : "submits"
    jobs ||--o{ job_applications : "receives"
```

### Índices de Banco de Dados

```sql
-- users
CREATE UNIQUE INDEX users_uuid_unique ON users(uuid);
CREATE UNIQUE INDEX users_email_unique ON users(email);

-- hotels
CREATE UNIQUE INDEX hotels_uuid_unique ON hotels(uuid);
CREATE UNIQUE INDEX hotels_slug_unique ON hotels(slug);
CREATE INDEX hotels_city_state_idx ON hotels(city, state);

-- hotel_users (evitar duplicatas de admin)
CREATE UNIQUE INDEX hotel_users_hotel_user_unique ON hotel_users(hotel_id, user_id);

-- posts
CREATE INDEX posts_user_id_created_at_idx ON posts(user_id, created_at DESC);
CREATE INDEX posts_hotel_id_created_at_idx ON posts(hotel_id, created_at DESC);

-- likes (evitar duplicatas de curtida)
CREATE UNIQUE INDEX likes_user_post_unique ON likes(user_id, post_id);

-- jobs
CREATE INDEX jobs_hotel_id_status_idx ON jobs(hotel_id, status);
CREATE INDEX jobs_status_created_at_idx ON jobs(status, created_at DESC);
CREATE INDEX jobs_city_state_idx ON jobs(city, state);

-- job_applications (evitar candidatura duplicada)
CREATE UNIQUE INDEX job_applications_job_user_unique ON job_applications(job_id, user_id);

-- full-text search (PostgreSQL)
CREATE INDEX hotels_fts_idx ON hotels USING gin(to_tsvector('portuguese', name || ' ' || description));
CREATE INDEX jobs_fts_idx ON jobs USING gin(to_tsvector('portuguese', title || ' ' || description));
CREATE INDEX users_fts_idx ON users USING gin(to_tsvector('portuguese', name));
```

---

## API Routes

Todas as rotas seguem o prefixo `/api/v1`.

### Autenticação (público)
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/auth/register` | Cadastro de usuário |
| POST | `/auth/login` | Login |
| POST | `/auth/forgot-password` | Solicitar recuperação de senha |
| POST | `/auth/reset-password` | Redefinir senha com token |

### Autenticação (autenticado)
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/auth/logout` | Logout (invalida token) |
| PUT | `/auth/password` | Alterar senha |

### Perfil do Usuário
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/me` | Dados do usuário autenticado |
| PUT | `/me` | Atualizar perfil |
| POST | `/me/avatar` | Upload de foto de perfil |
| POST | `/me/banner` | Upload de banner |
| GET | `/users/{uuid}` | Perfil público de um usuário |
| POST | `/me/experiences` | Adicionar experiência |
| PUT | `/me/experiences/{id}` | Editar experiência |
| DELETE | `/me/experiences/{id}` | Remover experiência |

### Hotéis
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/hotels` | Criar hotel |
| GET | `/hotels/{slug}` | Perfil público de um hotel |
| PUT | `/hotels/{slug}` | Editar hotel (HotelAdmin) |
| POST | `/hotels/{slug}/logo` | Upload de logo (HotelAdmin) |
| POST | `/hotels/{slug}/cover` | Upload de capa (HotelAdmin) |
| GET | `/me/hotels` | Hotéis gerenciados pelo usuário |

### Feed
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/feed` | Feed paginado (cursor-based) |
| POST | `/posts` | Criar publicação |
| GET | `/posts/{uuid}` | Detalhe de uma publicação |
| PUT | `/posts/{uuid}` | Editar publicação (autor) |
| DELETE | `/posts/{uuid}` | Excluir publicação (autor) |
| POST | `/posts/{uuid}/like` | Curtir |
| DELETE | `/posts/{uuid}/like` | Descurtir |
| POST | `/posts/{uuid}/comments` | Comentar |
| DELETE | `/posts/{uuid}/comments/{id}` | Excluir comentário (autor) |

### Vagas
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/jobs` | Listar vagas ativas (paginado) |
| POST | `/jobs` | Publicar vaga (HotelAdmin) |
| GET | `/jobs/{uuid}` | Detalhe de uma vaga |
| PUT | `/jobs/{uuid}` | Editar vaga (HotelAdmin) |
| PATCH | `/jobs/{uuid}/status` | Alterar status da vaga (HotelAdmin) |
| POST | `/jobs/{uuid}/apply` | Candidatar-se a uma vaga |
| GET | `/jobs/{uuid}/applications` | Listar candidaturas (HotelAdmin) |
| GET | `/me/applications` | Minhas candidaturas |

### Pesquisa
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/search/hotels?q=` | Buscar hotéis |
| GET | `/search/jobs?q=` | Buscar vagas ativas |
| GET | `/search/users?q=` | Buscar usuários |

---

## Correctness Properties

*Uma propriedade é uma característica ou comportamento que deve ser verdadeiro em todas as execuções
válidas do sistema — essencialmente, uma declaração formal sobre o que o sistema deve fazer.
Propriedades servem como ponte entre especificações legíveis por humanos e garantias de correção
verificáveis por máquinas.*

### Property 1: Autenticação Round-Trip

*Para qualquer* conjunto válido de dados de cadastro (nome, e-mail único, senha), o processo de
registrar um usuário e em seguida autenticar com as mesmas credenciais deve retornar um token
de acesso válido e dados do perfil criado.

**Validates: Requirements 1.1, 1.3**

---

### Property 2: Logout invalida token

*Para qualquer* usuário autenticado, após realizar logout, qualquer requisição subsequente ao mesmo
endpoint protegido usando o token anterior deve retornar HTTP 401.

**Validates: Requirements 1.5**

---

### Property 3: Alteração de senha invalida todas as sessões

*Para qualquer* usuário com múltiplos tokens ativos, após alterar a senha com sucesso, todos os
tokens anteriores devem ser invalidados — requisições com tokens antigos devem retornar HTTP 401.

**Validates: Requirements 1.8**

---

### Property 4: Atualização de perfil é consistente

*Para qualquer* conjunto válido de campos de perfil, após uma atualização bem-sucedida, ler o perfil
deve retornar exatamente os dados enviados na atualização (round-trip de dados).

**Validates: Requirements 2.2, 3.3**

---

### Property 5: Upload de imagem válida retorna URL acessível

*Para qualquer* imagem válida (jpg, png, webp, ≤ 5MB) enviada para endpoints de avatar, banner,
logo ou capa, o sistema deve armazenar e retornar uma URL pública não-nula.

**Validates: Requirements 2.3, 3.4**

---

### Property 6: Experiências profissionais são mantidas como conjunto

*Para qualquer* sequência de operações de adição e remoção de experiências, o conjunto de
experiências retornado pelo perfil deve refletir exatamente as operações realizadas — sem duplicatas
e sem experiências fantasmas.

**Validates: Requirements 2.5, 2.6**

---

### Property 7: Criação de hotel vincula HotelAdmin

*Para qualquer* usuário autenticado que cria um hotel com dados válidos, o sistema deve retornar o
hotel criado e o usuário deve aparecer na lista de admins desse hotel (role = admin).

**Validates: Requirements 3.1, 3.8**

---

### Property 8: Unicidade de slug de hotel

*Para qualquer* conjunto de hotéis criados na plataforma, não podem existir dois hotéis com o
mesmo slug — tentativas de criar hotel com slug duplicado devem ser rejeitadas com erro.

**Validates: Requirements 3.6**

---

### Property 9: Autorização — não-autorizado recebe 403

*Para qualquer* recurso protegido por HotelAdmin (editar hotel, publicar vaga, listar candidaturas)
ou por autoria (editar/excluir post/comentário), um usuário sem a permissão correta deve receber
HTTP 403 — independentemente do recurso específico.

**Validates: Requirements 3.7, 4.9, 7.3**

---

### Property 10: Feed é ordenado por data decrescente

*Para qualquer* conjunto de posts criados em momentos diferentes, a listagem do feed deve retornar
os posts em ordem estritamente decrescente de `created_at` — posts mais recentes primeiro.

**Validates: Requirements 4.2**

---

### Property 11: Exclusão de post remove comentários (cascata)

*Para qualquer* post com N comentários, após excluir o post, consultas aos comentários associados
não devem retornar nenhum resultado (soft delete em cascata).

**Validates: Requirements 4.4**

---

### Property 12: Round-trip de curtida

*Para qualquer* par (usuário, post), curtir e em seguida descurtir deve retornar o contador de
curtidas ao valor original — o sistema é idempotente nessa operação composta.

**Validates: Requirements 4.5, 4.6**

---

### Property 13: Candidatura registra vínculo correto

*Para qualquer* usuário e vaga ativa, após se candidatar, a vaga deve aparecer na lista de
candidaturas do usuário E o usuário deve aparecer na lista de candidatos da vaga.

**Validates: Requirements 5.5, 5.8, 5.9**

---

### Property 14: Relevância de busca

*Para qualquer* query de busca não-vazia, todos os resultados retornados devem conter a query
(ou variação) no(s) campo(s) indexados — nenhum resultado irrelevante deve ser incluído.

**Validates: Requirements 6.1, 6.2, 6.3**

---

### Property 15: Endpoint protegido rejeita não autenticado

*Para qualquer* endpoint que requer autenticação, uma requisição sem token (ou com token inválido)
deve retornar HTTP 401 — independentemente do endpoint ou método HTTP.

**Validates: Requirements 7.2**

---

### Property 16: Formato de resposta da API é consistente

*Para qualquer* resposta da API (sucesso ou erro), o corpo JSON deve conter os campos obrigatórios
do padrão definido: `success` (boolean), `message` (string) e `data` ou `errors` conforme o caso.

**Validates: Requirements 8.1, 8.2, 8.3, 8.4**

---

## Error Handling

### Estratégia Global

Todos os erros são tratados pelo `Handler.php` do Laravel e formatados via um trait
`ApiResponseTrait` reutilizado por todos os Controllers:

```php
// Sucesso
return $this->success('Usuário criado com sucesso.', new UserResource($user), 201);

// Erro
return $this->error('Credenciais inválidas.', [], 401);
```

### Mapeamento de Exceções

| Exceção | HTTP Status | Mensagem |
|---------|-------------|----------|
| `ValidationException` | 422 | Campo a campo em `errors` |
| `AuthenticationException` | 401 | "Não autenticado." |
| `AuthorizationException` | 403 | "Acesso não autorizado." |
| `ModelNotFoundException` | 404 | "Recurso não encontrado." |
| `QueryException` (duplicate) | 409 | "Registro duplicado." |
| `Throwable` genérico | 500 | "Erro interno. Tente novamente." |

---

## Testing Strategy

### Abordagem Dual

O projeto utiliza dois tipos complementares de teste:

**Testes unitários / de feature (PHPUnit)**
- Verificam exemplos específicos e casos de borda
- Cobrem fluxos de integração entre Controller → Service → Repository
- Executados com `php artisan test`

**Testes baseados em propriedades (PestPHP + Pest Architecture + php-quickcheck ou PestPHP nativo)**
- Verificam propriedades universais com inputs gerados automaticamente
- Cada propriedade do design corresponde a um único teste PBT
- Mínimo de 100 iterações por propriedade
- Executados junto à suite normal

### Biblioteca PBT

Utilizaremos **PestPHP** com o plugin **`pestphp/pest-plugin-arch`** para testes de arquitetura e
**`giorgiopogliani/pest-property-testing`** para property-based testing nativo no ecossistema PHP/Pest.

### Anotação de Testes

```php
it('satisfies Property 1: auth round-trip', function () {
    // Feature: hotel-connect, Property 1: Autenticação Round-Trip
    // Validates: Requirements 1.1, 1.3
})->repeat(100);
```

### Frontend (Vitest + React Testing Library)
- Testes unitários para hooks e utils
- Testes de componente para fluxos críticos (login, criação de post)
- Property tests com **fast-check** para validação de tipos e transformações de dados

---

## Infrastructure (Docker)

```yaml
# docker-compose.yml (estrutura)
services:
  nginx:      # proxy reverso — porta 80 → :8000 (API) e :3000 (frontend)
  php:        # PHP 8.4-fpm com extensões pgsql, redis, gd, exif
  postgres:   # PostgreSQL 16 — volume persistente
  redis:      # Redis 7 — volume persistente, usado para cache e filas
  node:       # Node 20 (apenas dev) — roda Vite na porta 3000
  mailpit:    # Captura de e-mails dev — porta 8025 (UI) e 1025 (SMTP)
```

---

## Roadmap e Backlog

### Sprint 0 — Infraestrutura Base (1 semana)
- [ ] Docker Compose completo (nginx, php, postgres, redis, node, mailpit)
- [ ] Projeto Laravel 12 inicializado com estrutura de domínios
- [ ] Projeto React 19 + Vite + TypeScript configurado
- [ ] CI pipeline básico (lint + tests)
- [ ] Git Flow configurado

### Sprint 1 — Autenticação (1 semana)
- [ ] Migrations: users
- [ ] Auth: register, login, logout, forgot/reset password
- [ ] Sanctum configurado
- [ ] Frontend: páginas de auth + AuthContext + PrivateRoute

### Sprint 2 — Perfil do Usuário (1 semana)
- [ ] Migrations: experiences
- [ ] API: GET/PUT /me, upload avatar/banner, CRUD experiences
- [ ] Frontend: ProfilePage, EditProfilePage

### Sprint 3 — Hotel (1 semana)
- [ ] Migrations: hotels, hotel_users
- [ ] API: CRUD hotel, upload logo/capa, listagem de hotéis gerenciados
- [ ] Frontend: HotelProfilePage, CreateHotelPage
- [ ] Policies: HotelPolicy

### Sprint 4 — Feed (1 semana)
- [ ] Migrations: posts, likes, comments
- [ ] API: CRUD post, like/unlike, CRUD comment, feed paginado
- [ ] Frontend: FeedPage, PostCard, CommentList
- [ ] Policies: PostPolicy, CommentPolicy

### Sprint 5 — Vagas (1 semana)
- [ ] Migrations: jobs, job_applications
- [ ] API: CRUD vaga, candidatura, listagens
- [ ] Frontend: JobsPage, JobDetailPage, ApplicationStatus
- [ ] Policies: JobPolicy

### Sprint 6 — Pesquisa + Polimento (1 semana)
- [ ] Full-text search (PostgreSQL GIN indexes)
- [ ] API: /search/hotels, /search/jobs, /search/users
- [ ] Frontend: SearchPage
- [ ] Revisão geral de UX, loading states, error boundaries
- [ ] Documentação de rotas

### Backlog Futuro (pós-MVP)
- Sistema de notificações (WebSockets / Pusher)
- Mensagens diretas entre hotéis/usuários
- Dashboard analytics para hotéis
- Integração com IA para sugestão de vagas e perfis
- CRM básico para gestão de candidaturas
- Planos de assinatura (Stripe)
- App mobile (React Native)

---

## Technical Risks

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Full-text search em PT-BR com acentuação | Média | Alto | Usar `unaccent` extension do PostgreSQL desde o início |
| Upload de imagens sem CDN em MVP | Alta | Médio | Usar Laravel Storage local no MVP, abstraído atrás de `StorageService` para migrar p/ S3 sem mudar code |
| Paginação cursor-based no feed com filtros | Média | Médio | Modelar corretamente os índices compostos desde a primeira migration |
| Escalabilidade do feed sem cache | Alta | Alto | Redis cache desde Sprint 4, TTL curto (60s) |
| Token Sanctum sem refresh automático | Média | Médio | Implementar interceptor Axios p/ 401 + logout automático no frontend |
| Conflito de slugs em hotéis com nomes similares | Baixa | Baixo | Geração automática de slug com sufixo incremental |
