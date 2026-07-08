# Design Document — Auth & Company Links Sprint

## Overview

Esta sprint entrega sete requisitos inter-relacionados: separação de login/cadastro por tipo de conta, isolamento de dashboards, correção do fluxo de vínculo profissional via `ExperienceForm`, ajuste na listagem de colaboradores, validação de permissões e melhorias de UX.

O sistema segue a arquitetura de domínio estabelecida:
- **Backend**: Laravel + Sanctum — camadas `Domain` (Services, DTOs, Repositories) + `Http` (Controllers, Requests, Resources, Policies)
- **Frontend**: React + TypeScript — separação por `pages`, `components`, `hooks`, `services`, `contexts`

---

## Architecture

A arquitetura segue o padrão de domínio já estabelecido no projeto. O backend em Laravel organiza a lógica em camadas de Domínio (Services, DTOs, Repositories) e HTTP (Controllers, Requests, Resources, Policies). O frontend React organiza em pages, components, hooks, services e contexts.

Para esta sprint, a principal adição arquitetural é o **dual-context de autenticação** no frontend: dois contextos React independentes (`AuthContext` para Pessoa e `HotelAuthContext` para Hotel) com chaves de storage separadas, evitando qualquer contaminação entre sessões.

---

## Components and Interfaces

Ver seções 3 (Frontend — Rotas e Componentes de Auth), 4 (DashboardHotel), 6 (Colaboradores), 7 (Permissões) e 8 (Componentes de UX) para detalhes completos de cada componente e suas interfaces TypeScript.

---

## Data Models

Ver seções 2.3 (campo `cnpj` na tabela `hotels`), 9 (extensões de tipos TypeScript) e 10 (tabela de endpoints) para os modelos de dados afetados.

---

## Error Handling

Ver seção 13 para a tabela completa de tratamento de erros (HTTP status codes e mensagens). O interceptor Axios em `api.ts` centraliza o tratamento de erros 401 e 403, disparando eventos globais para toast e redirecionamento.

---

## 1. Separação de Contextos de Autenticação

### 1.1 Problema atual

`AuthContext` gerencia um único contexto de usuário (tabela `users`). A autenticação de Hotel necessita de um contexto paralelo e independente, usando tokens e storage separados.

### 1.2 Estratégia: Dual-Context

Dois contextos coexistem em memória e em `localStorage` com chaves distintas:

| Contexto | Storage key (token) | Storage key (user) | Guard backend |
|---|---|---|---|
| `AuthContext` (Pessoa) | `token` | `user` | `auth:sanctum` (users) |
| `HotelAuthContext` (Hotel) | `hotel_token` | `hotel_session` | `auth:sanctum` (users, via hotel_users admin) |

> **Nota arquitetural**: O Hotel não é um Authenticatable distinto no Sanctum — a autenticação do hotel é feita pelo usuário Pessoa que é admin do hotel. O `HotelAuthContext` armazena o `hotel_slug` ativo e o token do usuário já autenticado em `AuthContext`, garantindo que o frontend saiba qual "sessão de hotel" está ativa.

### 1.3 `HotelAuthContext` — novo contexto

Arquivo: `src/contexts/HotelAuthContext.tsx`

```typescript
interface HotelSession {
  hotelSlug: string
  hotelName: string
  hotelUuid: string
  isAdmin: boolean
}

interface HotelAuthContextValue {
  session: HotelSession | null
  isHotelAuthenticated: boolean
  enterHotel: (session: HotelSession) => void
  exitHotel: () => void
}
```

- `enterHotel` persiste em `localStorage('hotel_session')` e define o estado
- `exitHotel` limpa o storage e o estado
- O contexto **não** gerencia token Sanctum próprio — usa o token do `AuthContext`
- Provido em `App.tsx` abaixo do `AuthProvider`

### 1.4 Fluxo de Login Hotel

1. Usuário acessa `/login/hotel`
2. Insere e-mail/senha de sua conta **Pessoa** e o CNPJ do hotel que administra
3. Frontend chama `POST /auth/login` (rota existente) para obter token de usuário
4. Frontend chama `GET /me/hotels` e encontra o hotel com o CNPJ informado, verificando que o usuário é admin
5. `HotelAuthContext.enterHotel(session)` é chamado
6. Redirecionamento para `/hotel/:slug/dashboard`

> Este design evita criar um guard Sanctum separado, reaproveitando o fluxo de autenticação existente.

---

## 2. Backend — Novas Rotas de Auth

### 2.1 Endpoint de login de hotel (opcional — helper)

```
POST /auth/hotel-login
```

Recebe `email`, `password` e `cnpj`. Autentica o usuário e verifica que ele é admin do hotel com aquele CNPJ. Retorna token + dados do hotel.

```php
// app/Http/Controllers/Api/V1/AuthController.php — método adicional
public function hotelLogin(HotelLoginRequest $request): JsonResponse
{
    $result = $this->authService->login(LoginDTO::fromArray($request->validated()));
    $hotel  = Hotel::where('cnpj', $request->validated('cnpj'))->firstOrFail();

    $isAdmin = $hotel->hotelUsers()
        ->where('user_id', $result['user']->id)
        ->where('role', 'admin')
        ->where('status', 'approved')
        ->exists();

    if (! $isAdmin) {
        throw ValidationException::withMessages([
            'cnpj' => ['Você não é administrador deste hotel.'],
        ]);
    }

    return $this->success('Login realizado com sucesso.', [
        'token' => $result['token'],
        'user'  => new UserResource($result['user']),
        'hotel' => new HotelResource($hotel),
    ]);
}
```

### 2.2 Registro de Hotel com 2 passos

Endpoint para o Passo 2 (criação do hotel durante o cadastro):

```
POST /auth/register-hotel
```

Recebe dados do usuário (Passo 1) + dados do hotel (Passo 2). Executa em transação:
1. Cria usuário via `AuthService::register()`
2. Cria hotel via `HotelService::createHotel()` — que já cria `hotel_users` com `role='admin'`
3. Retorna token + usuário + hotel

```php
// app/Domain/Auth/DTOs/RegisterHotelDTO.php
class RegisterHotelDTO
{
    public function __construct(
        public readonly string  $name,      // nome do usuário
        public readonly string  $email,
        public readonly string  $password,
        public readonly string  $hotel_name,
        public readonly string  $cnpj,
        public readonly string  $hotel_email,
        public readonly string  $hotel_password,
        public readonly ?string $city,
        public readonly ?string $website,
    ) {}

    public static function fromArray(array $data): self { /* ... */ }
}
```

### 2.3 Campo `cnpj` na tabela `hotels`

Nova migração para adicionar CNPJ ao hotel:

```php
Schema::table('hotels', function (Blueprint $table) {
    $table->string('cnpj', 18)->nullable()->unique()->after('name');
});
```

`CreateHotelDTO` e `UpdateHotelDTO` recebem o campo `cnpj` opcional. Validação de formato CNPJ aplicada no `RegisterHotelRequest` via regra customizada ou `Rule::regex`.

---

## 3. Frontend — Rotas e Componentes de Auth

### 3.1 Novas rotas

```
/login/pessoa          → LoginPessoaPage
/login/hotel           → LoginHotelPage
/register              → RegisterSelectPage  (escolha do tipo)
/register/pessoa       → RegisterPessoaPage  (formulário Pessoa)
/register/hotel        → RegisterHotelPage   (fluxo 2 passos)
```

Rotas legadas `/login` e antigo `/register` redirecionam para `/login/pessoa` e `/register` (select).

### 3.2 Guards atualizados

**`PrivateRoute`** (Pessoa) — redireciona para `/login/pessoa` se não autenticado.

**`HotelPrivateRoute`** — novo componente:

```typescript
// src/routes/HotelPrivateRoute.tsx
export default function HotelPrivateRoute() {
  const { isAuthenticated } = useAuth()
  const { isHotelAuthenticated } = useHotelAuth()

  if (!isAuthenticated) return <Navigate to="/login/hotel" replace />
  if (!isHotelAuthenticated) return <Navigate to="/login/hotel" replace />
  return <HotelLayout />
}
```

**`GuestRoute`** — atualizado para ter variante por tipo:

```typescript
// GuestRoute com prop `type`
type GuestRouteProps = { type: 'pessoa' | 'hotel' }
export default function GuestRoute({ type }: GuestRouteProps) {
  const { isAuthenticated } = useAuth()
  const { isHotelAuthenticated } = useHotelAuth()

  if (type === 'pessoa' && isAuthenticated)
    return <Navigate to="/feed" replace />
  if (type === 'hotel' && isHotelAuthenticated)
    return <Navigate to={`/hotel/${session?.hotelSlug}/dashboard`} replace />
  return <Outlet />
}
```

### 3.3 Estrutura de rotas atualizada

```typescript
// src/routes/index.tsx — alterações relevantes
<Routes>
  {/* Guest Pessoa */}
  <Route element={<GuestRoute type="pessoa" />}>
    <Route path="/login/pessoa"  element={<LoginPessoaPage />} />
    <Route path="/register"      element={<RegisterSelectPage />} />
    <Route path="/register/pessoa" element={<RegisterPessoaPage />} />
  </Route>

  {/* Guest Hotel */}
  <Route element={<GuestRoute type="hotel" />}>
    <Route path="/login/hotel"    element={<LoginHotelPage />} />
    <Route path="/register/hotel" element={<RegisterHotelPage />} />
  </Route>

  {/* Rotas Pessoa protegidas */}
  <Route element={<PrivateRoute />}>
    <Route path="/feed" element={<FeedPage />} />
    {/* ... demais rotas de pessoa ... */}
  </Route>

  {/* Rotas Hotel protegidas */}
  <Route element={<HotelPrivateRoute />}>
    <Route path="/hotel/:slug/dashboard"    element={<HotelDashboardPage />} />
    <Route path="/hotel/:slug/jobs"         element={<HotelJobsPage />} />
    <Route path="/hotel/:slug/employees"    element={<HotelEmployeesPage />} />
    <Route path="/hotel/:slug/posts"        element={<HotelPostsPage />} />
    <Route path="/hotel/:slug/settings"     element={<HotelSettingsPage />} />
    <Route path="/hotel/:slug/admins"       element={<HotelAdminsPage />} />
  </Route>

  {/* Legado — preserva links antigos */}
  <Route path="/login" element={<Navigate to="/login/pessoa" replace />} />
  <Route path="*"      element={<Navigate to="/feed" replace />} />
</Routes>
```

### 3.4 Páginas de Login separadas

**`LoginPessoaPage`** — idêntica à `LoginPage` atual. Visual: fundo azul/indigo.

**`LoginHotelPage`** — adiciona campo CNPJ ao formulário. Visual: fundo amber/dourado para distinção imediata.

```typescript
// LoginHotelPage — schema Zod adicional
const schema = z.object({
  email: z.string().email('E-mail inválido'),
  password: z.string().min(1, 'Senha obrigatória'),
  cnpj: z.string().min(14, 'CNPJ inválido').max(18),
})
```

### 3.5 RegisterHotelPage — Stepper 2 passos

```typescript
type Step = 1 | 2

// Passo 1: dados do usuário Pessoa
interface Step1Data {
  name: string
  email: string
  password: string
  password_confirmation: string
}

// Passo 2: dados do hotel
interface Step2Data {
  hotel_name: string
  cnpj: string
  hotel_email: string
  hotel_password: string
  city?: string
  website?: string
}
```

Componente mantém estado local `step` e `step1Data`. Ao submeter o Passo 2, envia tudo para `POST /auth/register-hotel`. Em caso de sucesso, chama `AuthContext.login` e `HotelAuthContext.enterHotel`.

---

## 4. DashboardHotel — Layout Isolado

### 4.1 `HotelLayout`

Arquivo: `src/components/hotel/HotelLayout.tsx`

Sidebar exclusiva com menus do Requisito 3.2:
- Editar Perfil do Hotel
- Gerenciar Vagas
- Gerenciar Colaboradores
- Publicações do Hotel
- Configurações
- Administradores

**Nunca** renderiza menus do `AppLayout` (DashboardPessoa).

### 4.2 Cross-access guards no frontend

`HotelPrivateRoute` verifica `isHotelAuthenticated`. Se um Pessoa sem sessão de hotel tentar `/hotel/:slug/dashboard`, será redirecionado para `/login/hotel`.

`PrivateRoute` — permanece inalterado para rotas de Pessoa. Não inclui nenhum menu de hotel.

---

## 5. Correção do Fluxo de Vínculo — ExperienceForm → EmployeeService

### 5.1 Problema atual

`ExperienceForm` envia `hotel_id` no payload da experiência, mas o `UserController::addExperience` / `updateExperience` não chama `EmployeeService::linkExperience`. O vínculo em `hotel_users` não é criado.

### 5.2 Correção no backend

```php
// app/Http/Controllers/Api/V1/UserController.php
// Em addExperience() e updateExperience()
public function addExperience(AddExperienceRequest $request): JsonResponse
{
    $data = $request->validated();
    $experience = $this->userService->addExperience($request->user(), $data);

    // Cria vínculo pending se hotel_id foi fornecido
    if (! empty($data['hotel_id'])) {
        $hotel = Hotel::findOrFail($data['hotel_id']);
        $this->employeeService->linkExperience($experience, $hotel);
    }

    return $this->created('Experiência adicionada.', new ExperienceResource($experience));
}
```

O `UserController` recebe `EmployeeService` via injeção de dependência. O mesmo padrão se aplica a `updateExperience`.

### 5.3 `EmployeeService::linkExperience` — comportamento atual (preservado)

O método já usa `firstOrCreate` para garantir idempotência: se já existe um vínculo `pending` ou `approved`, não cria duplicata.

```php
public function linkExperience(Experience $experience, Hotel $hotel): void
{
    $experience->update(['hotel_id' => $hotel->id]);

    HotelUser::firstOrCreate(
        ['hotel_id' => $hotel->id, 'user_id' => $experience->user_id],
        ['role' => 'employee', 'status' => 'pending'],
    );
    // TODO: dispatch EmployeeLinkRequested event → notifica admins (Req 4.2)
}
```

### 5.4 AddExperienceRequest — extensão

```php
// Adicionar ao rules():
'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
```

### 5.5 ExperienceResource — extensão

```php
// Retornar hotel_id e verified para o frontend
'hotel_id' => $this->hotel_id,
'verified' => $this->verified,
'hotel'    => $this->whenLoaded('hotel', fn () => new HotelResource($this->hotel)),
```

### 5.6 Frontend — hook `useLinkExperience`

O `ExperienceForm` já envia `hotel_id` no payload. O `useProfile` hook que chama `userService.addExperience` ou `updateExperience` recebe o resultado com o campo `verified` já populado. Nenhuma mudança no `ExperienceForm` é necessária exceto garantir que `CreateExperienceData` inclua `hotel_id`.

```typescript
// src/types/user.types.ts — extensão de CreateExperienceData
export interface CreateExperienceData {
  title: string
  company: string
  hotel_id?: number | null   // ADICIONAR
  started_at: string
  ended_at?: string
  description?: string
}
```

---

## 6. Listagem de Colaboradores Aprovados

### 6.1 Endpoint existente — verificação

`GET /hotels/:slug/employees` já filtra `role='employee'` e `status='approved'` via `EmployeeService::listApproved`. O endpoint está correto.

### 6.2 Ajuste de dados — experiência vinculada

O `HotelController::employees` já carrega a experiência vinculada ao hotel e retorna `title`, `started_at`, `verified`. Nenhuma alteração de lógica necessária.

### 6.3 Frontend — `EmployeeCard` com EmptyState

```typescript
// src/components/hotel/EmployeesTab.tsx
export function EmployeesTab({ slug }: { slug: string }) {
  const { data: employees = [], isLoading } = useHotelEmployees(slug)

  if (isLoading) return <EmployeesSkeleton />

  if (employees.length === 0) {
    return (
      <EmptyState
        title="Nenhum colaborador verificado"
        description="Os colaboradores aparecem aqui após aprovação do vínculo."
        icon={<Users className="h-8 w-8" />}
      />
    )
  }

  return (
    <div className="space-y-3">
      {employees.map((emp) => (
        <EmployeeCard key={emp.uuid} employee={emp} />
      ))}
    </div>
  )
}
```

---

## 7. Validação de Permissões — Laravel Policies + Guards Frontend

### 7.1 Backend — HotelPolicy (já existente — extensão)

A `HotelPolicy` existente cobre todas as ações admin. Para cobrir o Requisito 6.1 (Pessoa não pode acessar rotas de hotel), a verificação `manageEmployees`, `manageJobs`, `update`, etc. já retorna `false` para usuários que não são admin do hotel, resultando em HTTP 403.

Nenhuma nova policy é necessária. A cobertura é completa via o método `isAdmin()` já implementado.

### 7.2 Novos Guards de Permissão no Frontend

```typescript
// src/hooks/useHotelPermission.ts
export function useIsHotelAdmin(hotelSlug: string) {
  const { user } = useAuth()
  const { data: hotel } = useHotelAdmins(hotelSlug)
  return hotel?.some((admin) => admin.uuid === user?.uuid) ?? false
}
```

Componentes como `PendingEmployeesPanel` e `HotelSettingsPage` checam `useIsHotelAdmin` antes de renderizar ações destrutivas.

### 7.3 Tratamento de erros 403 no axios

```typescript
// src/services/api.ts — interceptor existente
api.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 403) {
      // Dispara evento global para toast de acesso negado
      window.dispatchEvent(new CustomEvent('auth:forbidden', {
        detail: { message: error.response.data?.message ?? 'Acesso negado.' }
      }))
    }
    if (error.response?.status === 401) {
      // Limpa sessão e redireciona para login correspondente
    }
    return Promise.reject(error)
  }
)
```

O `App.tsx` escuta `auth:forbidden` e chama `toast.error`.

---

## 8. Componentes de UX — ConfirmDialog, EmptyState, Skeleton

### 8.1 `ConfirmDialog`

Arquivo: `src/components/ui/ConfirmDialog.tsx`

```typescript
interface ConfirmDialogProps {
  open: boolean
  title: string
  description: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'danger' | 'warning' | 'default'
  onConfirm: () => void
  onCancel: () => void
  loading?: boolean
}
```

Renderizado como modal acessível (role="dialog", aria-modal, foco gerenciado). Botão "Confirmar" com cor vermelha para `variant='danger'`. Cancelar fecha sem executar operação.

### 8.2 `EmptyState`

Arquivo: `src/components/ui/EmptyState.tsx`

```typescript
interface EmptyStateProps {
  title: string
  description?: string
  icon?: React.ReactNode
  action?: { label: string; onClick: () => void }
}
```

Centralizado, ícone + título + descrição + botão opcional de ação.

### 8.3 `Skeleton` — extensão

O componente `Skeleton` já existe em `src/components/ui/Skeleton.tsx`. Adicionar variantes semânticas:

```typescript
// Variantes existentes estendidas:
export function EmployeeCardSkeleton() { /* ... */ }
export function HotelDashboardSkeleton() { /* ... */ }
```

### 8.4 Padrão de uso de Toast

Todas as mutations React Query devem seguir o padrão:

```typescript
useMutation({
  mutationFn: ...,
  onSuccess: () => {
    toast.success('Operação realizada com sucesso.')
    // invalidate queries
  },
  onError: (err) => {
    const msg = extractApiError(err) // helper que lê err.response.data.message
    toast.error(msg)
  },
})
```

Utilitário `extractApiError` em `src/utils/api-error.ts`:

```typescript
export function extractApiError(err: unknown): string {
  return (
    (err as { response?: { data?: { message?: string } } })
      ?.response?.data?.message ?? 'Ocorreu um erro inesperado.'
  )
}
```

---

## 9. Modelos e Tipos TypeScript — Extensões Necessárias

### 9.1 `auth.types.ts` — nenhuma mudança

O tipo `User` já está completo.

### 9.2 `hotel.types.ts` — adição de CNPJ

```typescript
export interface Hotel {
  // ... campos existentes ...
  cnpj: string | null    // ADICIONAR
}

export interface CreateHotelData {
  // ... campos existentes ...
  cnpj?: string          // ADICIONAR
}
```

### 9.3 `HotelSession` — novo tipo

```typescript
// src/types/hotel-auth.types.ts
export interface HotelSession {
  hotelSlug: string
  hotelName: string
  hotelUuid: string
  isAdmin: boolean
}
```

---

## 10. Tabela de Endpoints Novos / Modificados

| Método | URI | Controlador | Middleware | Requisito |
|---|---|---|---|---|
| `POST` | `/auth/hotel-login` | `AuthController@hotelLogin` | — | 1.2, 1.4 |
| `POST` | `/auth/register-hotel` | `AuthController@registerHotel` | — | 2.3, 2.4 |

Todos os demais endpoints relevantes (employees, follow, claim, etc.) já existem na `api.php`.

---

## 11. Fluxo de Dados — Vínculo Profissional

```
[Pessoa] Preenche ExperienceForm
         ↓ hotel_id selecionado via autocomplete
[Frontend] POST /me/experiences { title, company, hotel_id, ... }
         ↓
[UserController::addExperience]
  1. userService.addExperience() → cria Experience com hotel_id
  2. employeeService.linkExperience(experience, hotel)
     → experience.update({ hotel_id })
     → HotelUser::firstOrCreate({ hotel_id, user_id }, { role:'employee', status:'pending' })
     → dispatch EmployeeLinkRequested (notifica admins)
[Response] ExperienceResource { ..., hotel_id, verified: false, hotel: {...} }
         ↓
[HotelAdmin] vê solicitação em /hotel/:slug/employees (aba Pendentes)
         ↓
[HotelAdmin] aprova → PATCH /hotels/:slug/employees/:userId/approve
  → employeeService.approve(hotelUser)
     → hotelUser.status = 'approved'
     → Experience.where(hotel_id, user_id).update({ verified: true })
     → dispatch EmployeeLinkApproved (notifica Pessoa)
[Pessoa] vê Selo de Verificação no perfil
```

---

## 12. Estrutura de Arquivos Novos

```
backend/
  app/
    Domain/Auth/
      DTOs/
        RegisterHotelDTO.php        ← NOVO
      Services/
        AuthService.php             ← ESTENDER com hotelLogin(), registerHotel()
    Http/
      Controllers/Api/V1/
        AuthController.php          ← ESTENDER com hotelLogin(), registerHotel()
      Requests/Auth/
        HotelLoginRequest.php       ← NOVO
        RegisterHotelRequest.php    ← NOVO
  database/migrations/
    xxxx_add_cnpj_to_hotels.php     ← NOVO

frontend/
  src/
    contexts/
      HotelAuthContext.tsx          ← NOVO
    routes/
      HotelPrivateRoute.tsx         ← NOVO
      GuestRoute.tsx                ← MODIFICAR (prop type)
      index.tsx                     ← MODIFICAR (novas rotas)
    pages/auth/
      LoginPessoaPage.tsx           ← NOVO (refator de LoginPage)
      LoginHotelPage.tsx            ← NOVO
      RegisterSelectPage.tsx        ← NOVO
      RegisterPessoaPage.tsx        ← NOVO (refator de RegisterPage)
      RegisterHotelPage.tsx         ← NOVO (stepper 2 passos)
    pages/hotel/
      HotelDashboardPage.tsx        ← NOVO
      HotelJobsPage.tsx             ← NOVO
      HotelEmployeesPage.tsx        ← NOVO
      HotelPostsPage.tsx            ← NOVO
      HotelSettingsPage.tsx         ← NOVO
      HotelAdminsPage.tsx           ← NOVO
    components/
      hotel/
        HotelLayout.tsx             ← NOVO
        EmployeesTab.tsx            ← NOVO/REFATORAR
      ui/
        ConfirmDialog.tsx           ← NOVO
        EmptyState.tsx              ← NOVO
    hooks/
      useHotelAuth.ts               ← NOVO
      useHotelPermission.ts         ← NOVO
    types/
      hotel-auth.types.ts           ← NOVO
    utils/
      api-error.ts                  ← NOVO
```

---

## 13. Tratamento de Erros — Tabela Completa

| Situação | HTTP | Mensagem |
|---|---|---|
| Credenciais inválidas (Pessoa ou Hotel) | 422 | "As credenciais fornecidas estão incorretas." |
| Usuário não é admin do hotel no hotel-login | 422 | "Você não é administrador deste hotel." |
| CNPJ duplicado no cadastro de hotel | 422 | "Este CNPJ já está vinculado a outro hotel." |
| CNPJ formato inválido | 422 | "O CNPJ informado não é válido." |
| Acesso a rota hotel sem ser admin | 403 | "Ação não autorizada." |
| Vínculo já existente (pending/approved) | 200 | Retorna status atual sem criar duplicata |
| Hotel não encontrado | 404 | "Hotel não encontrado." |
| Usuário não autenticado | 401 | "Não autenticado." |

---

## Correctness Properties

*Uma propriedade é uma característica ou comportamento que deve ser verdadeiro em todas as execuções válidas do sistema — essencialmente, uma declaração formal sobre o que o sistema deve fazer. As propriedades servem como ponte entre especificações legíveis por humanos e garantias de correção verificáveis por máquina.*

### Property 1: Rotas protegidas de Pessoa redirecionam para login correto

*Para qualquer* rota marcada como protegida de Pessoa, um acesso sem token de autenticação deve resultar em redirecionamento para `/login/pessoa` — nunca para `/login/hotel` ou para outra URL.

**Validates: Requirements 1.6**

### Property 2: Rotas protegidas de Hotel redirecionam para login correto

*Para qualquer* rota marcada como protegida de Hotel, um acesso sem sessão de hotel ativa deve resultar em redirecionamento para `/login/hotel`.

**Validates: Requirements 1.7**

### Property 3: Cadastro de hotel sempre cria vínculo admin

*Para qualquer* payload de cadastro de hotel válido, ao concluir o Passo 2 deve existir exatamente um registro em `hotel_users` associando o usuário criador ao hotel recém-criado, com `role = 'admin'` e `status = 'approved'`.

**Validates: Requirements 2.4**

### Property 4: CNPJ duplicado é rejeitado universalmente

*Para qualquer* CNPJ que já esteja cadastrado na tabela `hotels`, qualquer requisição de criação de novo hotel com esse mesmo CNPJ deve retornar HTTP 422 e não criar registro.

**Validates: Requirements 2.5**

### Property 5: Validação de formato CNPJ é universal

*Para qualquer* string que não obedeça ao formato de CNPJ válido (14 dígitos, máscara XX.XXX.XXX/XXXX-XX), a requisição de cadastro deve ser rejeitada com erro de validação, independente dos demais campos.

**Validates: Requirements 2.6**

### Property 6: Acesso cruzado Pessoa→Hotel retorna 403

*Para qualquer* endpoint de ação administrativa de hotel (update, manageJobs, manageEmployees, manageRoles) e qualquer usuário Pessoa autenticado que não seja admin aprovado daquele hotel, a resposta backend deve ser HTTP 403.

**Validates: Requirements 3.5, 6.1**

### Property 7: Linkagem de experiência é idempotente

*Para qualquer* par (usuário, hotel) onde já existe um registro `hotel_users` com `status IN ('pending', 'approved')`, chamar `EmployeeService::linkExperience` novamente não deve criar um segundo registro — o total de registros para aquele par deve permanecer 1.

**Validates: Requirements 4.1, 4.8**

### Property 8: Aprovação do vínculo atualiza todas as experiências do usuário para aquele hotel

*Para qualquer* `hotel_users` com `status = 'pending'`, chamar `EmployeeService::approve` deve definir `status = 'approved'` nesse registro E `verified = true` em todas as `experiences` onde `hotel_id = hotel_users.hotel_id` e `user_id = hotel_users.user_id`.

**Validates: Requirements 4.3**

### Property 9: Notificação de admins ao criar vínculo

*Para qualquer* hotel com N admins aprovados, criar uma solicitação de vínculo (`status = 'pending'`) deve resultar em exatamente N notificações criadas, uma para cada admin.

**Validates: Requirements 4.2**

### Property 10: Selo de Verificação só aparece para experiências verified

*Para qualquer* experiência com `verified = true`, o componente `EmployeeCard` (ou a lista de experiências do perfil) deve renderizar o indicador de Selo de Verificação. *Para qualquer* experiência com `verified = false`, esse indicador não deve estar presente.

**Validates: Requirements 4.6**

### Property 11: Listagem pública de colaboradores filtra status corretamente

*Para qualquer* hotel, a resposta de `GET /hotels/:slug/employees` deve conter exclusivamente registros onde `role = 'employee'` E `status = 'approved'`. Nenhum registro com `status = 'pending'` ou `status = 'rejected'` deve aparecer.

**Validates: Requirements 5.1, 5.5**

### Property 12: EmployeeCard exibe todos os campos obrigatórios

*Para qualquer* objeto `Employee` com dados completos, o componente `EmployeeCard` renderizado deve conter: avatar/placeholder, nome clicável, cargo (title), data de entrada (started_at) e — se `verified = true` — o Selo de Verificação.

**Validates: Requirements 5.2**

### Property 13: ConfirmDialog deve ser exibido antes de qualquer operação destrutiva

*Para qualquer* acionamento de ação destrutiva (remoção de vaga, publicação ou colaborador), o componente `ConfirmDialog` deve ser exibido com `open = true` antes da mutation ser disparada. Se o usuário cancelar, a mutation não deve ser invocada e o estado da aplicação deve permanecer idêntico ao estado anterior.

**Validates: Requirements 7.1, 7.7**

### Property 14: Indicador de carregamento presente durante requisições

*Para qualquer* componente de listagem (colaboradores, vagas, publicações) em estado `isLoading = true`, o elemento Skeleton ou Spinner correspondente deve estar presente na árvore DOM renderizada.

**Validates: Requirements 7.2**

### Property 15: EmptyState renderizado para listas vazias

*Para qualquer* componente de lista que receba um array vazio como dados, o componente `EmptyState` contextualizado deve ser renderizado em lugar da lista.

**Validates: Requirements 5.4, 7.3**

### Property 16: Toast de sucesso e erro para mutations

*Para qualquer* mutation de escrita bem-sucedida, `toast.success` deve ser chamado com mensagem não-vazia. *Para qualquer* mutation de escrita que falhe com erro da API, `toast.error` deve ser chamado com a mensagem de erro extraída da resposta.

**Validates: Requirements 7.4, 7.5**

---

## Testing Strategy

### Abordagem Dual

A estratégia combina **testes de exemplo** (casos específicos, fluxos de integração) com **testes de propriedade** (invariantes universais sobre lógica de domínio).

**Testes de propriedade** são adequados para:
- Lógica pura de domínio: `EmployeeService::linkExperience`, `approve`, `reject`
- Filtros de listagem: endpoint de colaboradores aprovados
- Validação de CNPJ: rejeição universal de formatos inválidos
- Comportamento idempotente: criação de vínculos duplicados
- Componentes React com entradas variadas: `EmployeeCard`, `EmptyState`, `ConfirmDialog`

**Testes de integração/exemplo** são adequados para:
- Fluxos de autenticação (login/cadastro → redirect)
- Verificação de menus por tipo de dashboard
- Comportamento de guards de rota
- Endpoints de notificação e eventos

**Configuração mínima**: 100 iterações por propriedade via biblioteca de property testing (PHP: `eris` ou `phpunit` com generators; TypeScript: `fast-check`).

**Tag format para testes de propriedade**: `Feature: auth-company-links-sprint, Property {N}: {título}`
