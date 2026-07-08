# Implementation Plan: HotelConnect MVP

## Overview

Implementação incremental do MVP em 6 sprints. Cada tarefa constrói sobre a anterior.
Nenhum código fica isolado — tudo é integrado ao final de cada etapa.

---

## Tasks

- [x] 1. Infraestrutura e configuração base
  - Criar `docker-compose.yml` com containers: nginx, php (8.4-fpm), postgres (16), redis (7), node (20), mailpit
  - Configurar `nginx.conf` com proxy para API (:8000) e frontend (:3000)
  - Inicializar projeto Laravel 12 com estrutura de domínios em `app/Domain/`
  - Inicializar projeto React 19 + Vite + TypeScript com estrutura de pastas definida no design
  - Configurar TailwindCSS + Shadcn/UI no frontend
  - Criar `ApiResponseTrait` no backend para padronizar responses
  - Criar tipos globais `ApiResponse<T>` e `PaginatedResponse<T>` no frontend
  - _Requirements: 8.1, 8.2, 8.3, 9.1, 9.2, 9.3, 9.4, 9.5_

- [x] 2. Autenticação
  - [x] 2.1 Migration e Model de usuários
    - Criar migration `users` com todos os campos definidos no design (incluindo `uuid`, `role`, soft deletes)
    - Criar `User` model com fillable, casts e relacionamentos básicos
    - _Requirements: 1.1, 2.1_

  - [x] 2.2 Backend de autenticação
    - Criar `RegisterUserDTO`, `LoginDTO`
    - Criar `AuthService` com métodos: `register`, `login`, `logout`, `forgotPassword`, `resetPassword`, `changePassword`
    - Criar `AuthController` com rotas: POST `/auth/register`, `/auth/login`, `/auth/logout`, `/auth/forgot-password`, `/auth/reset-password`, PUT `/auth/password`
    - Criar Form Requests: `RegisterRequest`, `LoginRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`, `ChangePasswordRequest`
    - Criar `UserResource` para formatar resposta pública do usuário
    - Configurar Laravel Sanctum
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8_

  - [ ]* 2.3 Testes de autenticação
    - **Property 1: Autenticação Round-Trip** — registrar usuário com dados válidos e autenticar deve retornar token
    - **Property 2: Logout invalida token** — após logout, token não deve ser aceito em endpoints protegidos
    - **Property 3: Alteração de senha invalida sessões** — após trocar senha, todos os tokens anteriores rejeitados
    - Testes de edge case: e-mail duplicado (Req 1.2), credenciais erradas (Req 1.4), link expirado (Req 1.7)
    - **Validates: Requirements 1.1, 1.3, 1.5, 1.8**

  - [x] 2.4 Frontend de autenticação
    - Criar `AuthContext` com estado do usuário autenticado
    - Criar `auth.service.ts` com chamadas à API de auth
    - Criar `useAuth` hook
    - Criar páginas: `LoginPage`, `RegisterPage`, `ForgotPasswordPage`, `ResetPasswordPage`
    - Criar `PrivateRoute` e `GuestRoute`
    - Configurar interceptor Axios para 401 → logout automático
    - _Requirements: 1.1, 1.3, 1.5_

- [x] 3. Perfil do Usuário
  - [x] 3.1 Migration e endpoints de perfil
    - Criar migration `experiences`
    - Criar `Experience` model
    - Criar `UserRepositoryInterface` + `EloquentUserRepository`
    - Criar `UpdateProfileDTO`, `UserService`
    - Criar `UserController` com rotas: GET/PUT `/me`, POST `/me/avatar`, POST `/me/banner`
    - Criar endpoints CRUD `/me/experiences`
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6_

  - [ ]* 3.2 Testes de perfil
    - **Property 4: Atualização de perfil é consistente** — dados enviados devem ser retornados na leitura
    - **Property 5: Upload de imagem válida retorna URL** — imagens válidas devem retornar URL não-nula
    - **Property 6: Experiências como conjunto** — operações de add/remove devem ser refletidas corretamente
    - Edge cases: arquivo inválido (Req 2.4), arquivo > 5MB (Req 2.4)
    - **Validates: Requirements 2.2, 2.3, 2.5, 2.6**

  - [x] 3.3 Frontend de perfil
    - Criar `user.service.ts`
    - Criar `useProfile` hook com TanStack Query
    - Criar `ProfilePage` (visualização pública) e `EditProfilePage` (edição)
    - Componentes: `AvatarUpload`, `ExperienceList`, `ExperienceForm`
    - _Requirements: 2.1, 2.2, 2.5_

- [x] 4. Hotel
  - [x] 4.1 Migrations e Model de hotel
    - Criar migrations: `hotels`, `hotel_users`
    - Criar models: `Hotel`, `HotelUser` com relacionamentos
    - Criar `HotelPolicy` (regras: só HotelAdmin pode editar)
    - _Requirements: 3.1, 3.2, 3.7_

  - [x] 4.2 Backend de hotel
    - Criar `CreateHotelDTO`, `UpdateHotelDTO`
    - Criar `HotelRepositoryInterface` + `EloquentHotelRepository`
    - Criar `HotelService` com geração automática de slug único
    - Criar `HotelController` com rotas: POST `/hotels`, GET `/hotels/{slug}`, PUT `/hotels/{slug}`, POST `/hotels/{slug}/logo`, POST `/hotels/{slug}/cover`, GET `/me/hotels`
    - Criar `HotelResource`
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8_

  - [ ]* 4.3 Testes de hotel
    - **Property 7: Criação de hotel vincula HotelAdmin** — criador deve aparecer como admin do hotel
    - **Property 8: Unicidade de slug** — dois hotéis com nome similar não devem ter slug idêntico
    - **Property 9: Não-autorizado recebe 403** — user sem permissão tentando editar hotel
    - **Validates: Requirements 3.1, 3.6, 3.7, 3.8**

  - [x] 4.4 Frontend de hotel
    - Criar `hotel.service.ts`
    - Criar `useHotel` hook
    - Criar `HotelProfilePage` e `CreateHotelPage`
    - Componentes: `HotelHeader`, `HotelCard`, `LogoUpload`
    - _Requirements: 3.1, 3.2, 3.3_

- [x] 5. Checkpoint — testes passando
  - Garantir que todos os testes implementados até aqui passam.
  - Verificar padrão de resposta da API em todos os endpoints criados.

- [x] 6. Feed
  - [x] 6.1 Migrations e Models do feed
    - Criar migrations: `posts`, `likes`, `comments`
    - Criar models: `Post`, `Like`, `Comment` com relacionamentos e soft deletes
    - Criar `PostPolicy`, `CommentPolicy`
    - _Requirements: 4.1, 4.4, 4.9_

  - [x] 6.2 Backend do feed
    - Criar `CreatePostDTO`, `CreateCommentDTO`
    - Criar `PostRepositoryInterface` + `EloquentPostRepository` com paginação cursor-based
    - Criar `FeedService`
    - Criar `FeedController` com rotas: GET `/feed`, POST `/posts`, GET/PUT/DELETE `/posts/{uuid}`, POST/DELETE `/posts/{uuid}/like`, POST `/posts/{uuid}/comments`, DELETE `/posts/{uuid}/comments/{id}`
    - Criar `PostResource`, `CommentResource`
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9_

  - [ ]* 6.3 Testes do feed
    - **Property 10: Feed ordenado por data decrescente** — posts devem retornar em ordem correta
    - **Property 11: Exclusão de post remove comentários** — cascata deve ser respeitada
    - **Property 12: Round-trip de curtida** — curtir + descurtir retorna ao estado original
    - **Validates: Requirements 4.2, 4.4, 4.5, 4.6**

  - [x] 6.4 Frontend do feed
    - Criar `feed.service.ts`
    - Criar `useFeed` hook com paginação infinita (TanStack Query `useInfiniteQuery`)
    - Criar `FeedPage`
    - Componentes: `PostCard`, `PostForm`, `CommentList`, `LikeButton`
    - _Requirements: 4.1, 4.2, 4.5, 4.7_

- [x] 7. Vagas
  - [x] 7.1 Migrations e Models de vagas
    - Criar migrations: `jobs`, `job_applications`
    - Criar models: `Job`, `JobApplication` com relacionamentos e soft deletes
    - Criar `JobPolicy`
    - _Requirements: 5.1, 5.2, 5.6, 5.7_

  - [x] 7.2 Backend de vagas
    - Criar `CreateJobDTO`, `ApplyJobDTO`
    - Criar `JobRepositoryInterface` + `EloquentJobRepository`
    - Criar `JobService`
    - Criar `JobController` com rotas: GET `/jobs`, POST `/jobs`, GET/PUT `/jobs/{uuid}`, PATCH `/jobs/{uuid}/status`, POST `/jobs/{uuid}/apply`, GET `/jobs/{uuid}/applications`, GET `/me/applications`
    - Criar `JobResource`
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.9_

  - [x] 7.3 Testes de vagas
    - **Property 13: Candidatura registra vínculo correto** — vaga aparece em /me/applications e usuário em /applications da vaga
    - Edge cases: candidatura duplicada (Req 5.7), vaga pausada/encerrada (Req 5.6)
    - **Validates: Requirements 5.5, 5.8, 5.9**

  - [x] 7.4 Frontend de vagas
    - Criar `job.service.ts`
    - Criar `useJobs` hook
    - Criar `JobsPage` e `JobDetailPage`
    - Componentes: `JobCard`, `JobFilters`, `ApplicationStatus`
    - _Requirements: 5.1, 5.2, 5.5_

- [x] 8. Pesquisa
  - [x] 8.1 Backend de pesquisa
    - Habilitar extensão `unaccent` no PostgreSQL
    - Criar indexes GIN full-text search nas migrations (hotels, jobs, users)
    - Criar `SearchQueryDTO`
    - Criar `SearchService` com métodos: `searchHotels`, `searchJobs`, `searchUsers`
    - Criar `SearchController` com rotas: GET `/search/hotels`, `/search/jobs`, `/search/users`
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [ ] 8.2 Testes de pesquisa
    - **Property 14: Relevância de busca** — resultados devem conter a query nos campos indexados
    - Edge case: query vazia retorna 422 (Req 6.4)
    - **Validates: Requirements 6.1, 6.2, 6.3, 6.5**

  - [x] 8.3 Frontend de pesquisa
    - Criar `search.service.ts`
    - Criar `useSearch` hook com debounce
    - Criar `SearchPage` com abas: Hotéis / Vagas / Usuários
    - _Requirements: 6.1, 6.2, 6.3_

- [x] 9. Checkpoint final — garantir testes e padrões
  - Garantir que todos os testes passam (PHPUnit + Pest).
  - Verificar que todas as respostas seguem o padrão `ApiResponseTrait`.
  - **Property 15: Endpoint protegido rejeita não autenticado** — testar amostra de endpoints sem token
  - **Property 16: Formato de resposta consistente** — validar estrutura de sucesso e erro em todos os endpoints
  - **Validates: Requirements 7.2, 8.1, 8.2, 8.3, 8.4**

---

## Notes

- Tarefas com `*` são opcionais (testes) e podem ser puladas para entrega mais rápida do MVP
- Cada tarefa referencia os requisitos específicos que implementa
- Os checkpoints nas tarefas 5 e 9 garantem validação incremental
- Toda propriedade de correção do design tem uma sub-tarefa de teste correspondente
