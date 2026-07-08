# Implementation Plan: Auth & Company Links Sprint

## Overview

Implementação incremental dos sete requisitos da sprint, com as seguintes prioridades:

1. **Backend first**: migração de CNPJ, novos endpoints de auth hotel e correção do `UserController`
2. **Contexto e hooks frontend**: `HotelAuthContext` e `useHotelAuth` como base para todo o restante
3. **Rotas e guards**: páginas de login separadas, `GuestRoute` atualizado, `HotelPrivateRoute`
4. **Cadastro**: `RegisterSelectPage`, `RegisterPessoaPage` e `RegisterHotelPage` (stepper)
5. **Layout de hotel**: `HotelLayout` + rotas protegidas + páginas de dashboard
6. **Aba de colaboradores**: `EmployeesTab` com `EmptyState` e `Skeleton`
7. **Componentes de UX**: `ConfirmDialog`, `EmptyState`, `extractApiError`, padrão de toast
8. **Testes de propriedade** para as propriedades definidas no design

A linguagem de implementação é **PHP (Laravel)** no backend e **TypeScript (React)** no frontend, conforme o projeto existente.

---

## Tasks

- [x] 1. Backend — Migração CNPJ e novos endpoints de autenticação de hotel
  - [x] 1.1 Criar migração para adicionar coluna `cnpj` à tabela `hotels`
    - Criar arquivo em `database/migrations/` com `Schema::table('hotels', ...)` adicionando `string('cnpj', 18)->nullable()->unique()->after('name')`
    - Executar `php artisan migrate` para validar a migração
    - _Requirements: 2.6_

  - [x] 1.2 Estender `CreateHotelDTO` e `UpdateHotelDTO` com o campo `cnpj` opcional
    - Adicionar `public readonly ?string $cnpj` nos dois DTOs
    - Atualizar `fromArray` em cada DTO para mapear o campo
    - _Requirements: 2.6_

  - [x] 1.3 Criar `RegisterHotelDTO` com campos de usuário + hotel
    - Arquivo: `app/Domain/Auth/DTOs/RegisterHotelDTO.php`
    - Campos: `name`, `email`, `password`, `hotel_name`, `cnpj`, `hotel_email`, `hotel_password`, `city` (nullable), `website` (nullable)
    - _Requirements: 2.3_

  - [x] 1.4 Criar `HotelLoginRequest` e `RegisterHotelRequest`
    - `HotelLoginRequest`: valida `email` (required|email), `password` (required|string), `cnpj` (required|string|min:14|max:18)
    - `RegisterHotelRequest`: valida campos do usuário (Passo 1) e do hotel (Passo 2), incluindo `cnpj` com `Rule::regex` para formato brasileiro e `unique:hotels,cnpj` para detectar duplicidade
    - _Requirements: 2.5, 2.6_

  - [x] 1.5 Estender `AuthController` com `hotelLogin()` e `registerHotel()`
    - `hotelLogin`: autentica via `AuthService::login()`, busca hotel pelo CNPJ, verifica `hotel_users` com `role='admin'` e `status='approved'`, retorna token + `UserResource` + `HotelResource`
    - `registerHotel`: executa em DB transaction — cria usuário via `AuthService::register()`, cria hotel via `HotelService::createHotel()`, retorna token + usuário + hotel
    - _Requirements: 1.2, 1.4, 2.3, 2.4, 2.8_

  - [x] 1.6 Registrar novas rotas em `api.php`
    - `POST /auth/hotel-login` → `AuthController@hotelLogin`
    - `POST /auth/register-hotel` → `AuthController@registerHotel`
    - _Requirements: 1.2, 2.3_

  - [ ]* 1.7 Escrever testes de propriedade para CNPJ duplicado (Property 4) e formato inválido (Property 5)
    - **Property 4: CNPJ duplicado é rejeitado universalmente**
    - **Validates: Requirements 2.5**
    - **Property 5: Validação de formato CNPJ é universal**
    - **Validates: Requirements 2.6**

- [x] 2. Backend — Correção do `UserController` (injetar `EmployeeService`, chamar `linkExperience`)
  - [x] 2.1 Injetar `EmployeeService` no construtor do `UserController`
    - Adicionar `private readonly EmployeeService $employeeService` ao construtor
    - Adicionar `use App\Domain\Hotel\Services\EmployeeService` e `use App\Models\Hotel` ao cabeçalho
    - _Requirements: 4.1_

  - [x] 2.2 Estender `ExperienceRequest` com validação do campo `hotel_id`
    - Adicionar `'hotel_id' => ['nullable', 'integer', 'exists:hotels,id']` ao array `rules()`
    - _Requirements: 4.1_

  - [x] 2.3 Chamar `EmployeeService::linkExperience` em `addExperience()` e `updateExperience()`
    - Se `$data['hotel_id']` não estiver vazio, buscar hotel com `Hotel::findOrFail()` e chamar  `$this->employeeService->linkExperience($experience, $hotel)`
    - Aplicar o mesmo padrão em `updateExperience()`
    - _Requirements: 4.1, 4.8_

  - [x] 2.4 Estender `ExperienceResource` com campos `hotel_id`, `verified` e relação `hotel` eager-loaded
    - Retornar `'hotel_id' => $this->hotel_id`, `'verified' => $this->verified`, `'hotel' => $this->whenLoaded('hotel', ...)`
    - Garantir que `addExperience` e `updateExperience` carreguem a relação `hotel` antes de retornar
    - _Requirements: 4.6, 5.2_

  - [ ]* 2.5 Escrever testes de propriedade para idempotência do vínculo (Property 7) e aprovação em cascata (Property 8)
    - **Property 7: Linkagem de experiência é idempotente**
    - **Validates: Requirements 4.1, 4.8**
    - **Property 8: Aprovação do vínculo atualiza todas as experiências do usuário para aquele hotel**
    - **Validates: Requirements 4.3**

  - [ ]* 2.6 Escrever testes de propriedade para controle de acesso 403 (Property 6)
    - **Property 6: Acesso cruzado Pessoa→Hotel retorna 403**
    - **Validates: Requirements 3.5, 6.1**

- [x] 3. Checkpoint — Backend funcional
  - Certifique-se de que os endpoints `POST /auth/hotel-login`, `POST /auth/register-hotel` e `POST /me/experiences` (com `hotel_id`) retornam as respostas esperadas. Pergunte ao usuário se houver dúvidas.

- [x] 4. Frontend — `HotelAuthContext` e hook `useHotelAuth`
  - [x] 4.1 Criar `src/types/hotel-auth.types.ts` com interface `HotelSession`
    - Campos: `hotelSlug: string`, `hotelName: string`, `hotelUuid: string`, `isAdmin: boolean`
    - _Requirements: 3.7_

  - [x] 4.2 Criar `src/contexts/HotelAuthContext.tsx`
    - Interface `HotelAuthContextValue` com: `session: HotelSession | null`, `isHotelAuthenticated: boolean`, `enterHotel(session)`, `exitHotel()`
    - `enterHotel` persiste em `localStorage('hotel_session')`, `exitHotel` limpa o storage
    - Expor `HotelAuthProvider` e `useHotelAuthContext`
    - _Requirements: 1.9, 3.7_

  - [x] 4.3 Criar `src/hooks/useHotelAuth.ts`
    - Re-exportar `useHotelAuthContext` como `useHotelAuth` para consistência com o padrão `useAuth` existente
    - _Requirements: 3.7_

  - [x] 4.4 Registrar `HotelAuthProvider` em `App.tsx` abaixo do `AuthProvider`
    - Garantir que `HotelAuthProvider` envolva todos os componentes filhos
    - _Requirements: 3.7_

  - [x] 4.5 Estender `hotel.types.ts` com campo `cnpj: string | null` em `Hotel` e `CreateHotelData`
    - _Requirements: 2.6_

  - [ ]* 4.6 Escrever testes de propriedade para guards de rota Pessoa (Property 1) e Hotel (Property 2)
    - **Property 1: Rotas protegidas de Pessoa redirecionam para `/login/pessoa`**
    - **Validates: Requirements 1.6**
    - **Property 2: Rotas protegidas de Hotel redirecionam para `/login/hotel`**
    - **Validates: Requirements 1.7**

- [x] 5. Frontend — Páginas de login separadas e guards de rota
  - [x] 5.1 Refatorar `LoginPage` existente em `LoginPessoaPage`
    - Criar `src/pages/auth/LoginPessoaPage.tsx` como cópia da `LoginPage` com visual azul/indigo
    - Manter comportamento idêntico (chama `AuthContext.login`, redireciona para `/feed`)
    - _Requirements: 1.1, 1.3, 1.5, 1.8_

  - [x] 5.2 Criar `LoginHotelPage` com campo CNPJ adicional
    - Arquivo: `src/pages/auth/LoginHotelPage.tsx`
    - Schema Zod com `email`, `password` e `cnpj` (min:14, max:18)
    - Ao submeter: chama `POST /auth/hotel-login`, depois `HotelAuthContext.enterHotel(session)`, redireciona para `/hotel/:slug/dashboard`
    - Visual: fundo amber/dourado distinto
    - _Requirements: 1.2, 1.4, 1.9_

  - [x] 5.3 Atualizar `GuestRoute` para aceitar prop `type: 'pessoa' | 'hotel'`
    - Para `type='pessoa'`: redireciona para `/feed` se `isAuthenticated`
    - Para `type='hotel'`: redireciona para `/hotel/:slug/dashboard` se `isHotelAuthenticated`
    - _Requirements: 1.8, 1.9_

  - [x] 5.4 Criar `HotelPrivateRoute` em `src/routes/HotelPrivateRoute.tsx`
    - Verifica `isAuthenticated` (redireciona `/login/hotel` se falso) e `isHotelAuthenticated` (redireciona `/login/hotel` se falso)
    - Renderiza `<HotelLayout />` quando autenticado
    - _Requirements: 1.7, 3.5_

  - [x] 5.5 Adicionar redirecionamentos legados: `/login` → `/login/pessoa`, `/register` → `/register` (select)
    - Em `src/routes/index.tsx`, adicionar `<Route path="/login" element={<Navigate to="/login/pessoa" replace />} />`
    - _Requirements: 1.1_

- [ ] 6. Frontend — Cadastro separado (`RegisterSelectPage`, `RegisterPessoaPage`, `RegisterHotelPage`)
  - [x] 6.1 Criar `RegisterSelectPage` com dois cards de opção
    - Arquivo: `src/pages/auth/RegisterSelectPage.tsx`
    - Dois cards/botões: "Criar conta de Pessoa" → `/register/pessoa` e "Criar conta de Hotel" → `/register/hotel`
    - _Requirements: 2.1_

  - [x] 6.2 Criar `RegisterPessoaPage` como refator da `RegisterPage` existente
    - Arquivo: `src/pages/auth/RegisterPessoaPage.tsx`
    - Campos: Nome, E-mail, Senha — chama `POST /auth/register`, autentica e redireciona para `/feed`
    - _Requirements: 2.2, 2.7_

  - [x] 6.3 Criar `RegisterHotelPage` com stepper de 2 passos
    - Arquivo: `src/pages/auth/RegisterHotelPage.tsx`
    - Passo 1: campos `name`, `email`, `password`, `password_confirmation`
    - Passo 2: campos `hotel_name`, `cnpj`, `hotel_email`, `hotel_password`, `city` (opcional), `website` (opcional)
    - Ao submeter Passo 2: chama `POST /auth/register-hotel`, depois `AuthContext` + `HotelAuthContext.enterHotel`, redireciona para `/hotel/:slug/dashboard`
    - _Requirements: 2.3, 2.4, 2.8_

  - [x] 6.4 Atualizar `src/routes/index.tsx` com todas as novas rotas de auth e hotel
    - Rotas guest Pessoa: `/login/pessoa`, `/register`, `/register/pessoa`
    - Rotas guest Hotel: `/login/hotel`, `/register/hotel`
    - Rotas privadas Hotel (usando `HotelPrivateRoute`): `/hotel/:slug/dashboard`, `/hotel/:slug/jobs`, `/hotel/:slug/employees`, `/hotel/:slug/posts`, `/hotel/:slug/settings`, `/hotel/:slug/admins`
    - _Requirements: 1.1, 1.2, 2.1, 3.1, 3.2_

- [x] 7. Frontend — `HotelLayout` + páginas de dashboard do hotel
  - [x] 7.1 Criar `HotelLayout` em `src/components/hotel/HotelLayout.tsx`
    - Sidebar com menus exclusivos: Editar Perfil, Gerenciar Vagas, Gerenciar Colaboradores, Publicações, Configurações, Administradores
    - Nunca renderiza menus do `AppLayout`
    - Botão de logout que chama `HotelAuthContext.exitHotel()` e redireciona para `/login/hotel`
    - _Requirements: 3.2, 3.4_

  - [x] 7.2 Criar páginas de dashboard do hotel como stubs funcionais
    - `HotelDashboardPage`, `HotelJobsPage`, `HotelEmployeesPage`, `HotelPostsPage`, `HotelSettingsPage`, `HotelAdminsPage` em `src/pages/hotel/`
    - Cada página renderiza um título e o conteúdo básico (pode ser esqueleto inicial)
    - _Requirements: 3.2_

  - [x] 7.3 Criar hook `useHotelPermission` para verificar se o usuário logado é admin do hotel ativo
    - Arquivo: `src/hooks/useHotelPermission.ts`
    - Exporta `useIsHotelAdmin(hotelSlug: string): boolean`
    - _Requirements: 6.1, 6.3_

- [x] 8. Checkpoint — Frontend de autenticação e rotas funcional
  - Certifique-se de que login Pessoa (`/login/pessoa`) e Hotel (`/login/hotel`) funcionam, que `HotelPrivateRoute` bloqueia acessos não autorizados e que as páginas de cadastro renderizam corretamente. Pergunte ao usuário se houver dúvidas.

- [x] 9. Frontend — `EmployeesTab` com `EmptyState` e `Skeleton`
  - [x] 9.1 Criar/refatorar `src/components/hotel/EmployeesTab.tsx`
    - Usa `useHotelEmployees(slug)` para buscar colaboradores aprovados
    - Em `isLoading`: renderiza `EmployeeCardSkeleton`
    - Em lista vazia: renderiza `EmptyState` com título "Nenhum colaborador verificado"
    - Em lista preenchida: mapeia `EmployeeCard` para cada colaborador
    - _Requirements: 5.1, 5.2, 5.4, 5.5_

  - [x] 9.2 Estender `Skeleton.tsx` com variante `EmployeeCardSkeleton`
    - Adicionar `export function EmployeeCardSkeleton()` ao `src/components/ui/Skeleton.tsx` existente
    - _Requirements: 7.2_

  - [ ]* 9.3 Escrever testes de propriedade para filtro de colaboradores (Property 11), `EmployeeCard` (Property 12), `EmptyState` em lista vazia (Property 15) e skeleton em loading (Property 14)
    - **Property 11: Listagem pública filtra status corretamente**
    - **Validates: Requirements 5.1, 5.5**
    - **Property 12: EmployeeCard exibe todos os campos obrigatórios**
    - **Validates: Requirements 5.2**
    - **Property 14: Indicador de carregamento presente durante requisições**
    - **Validates: Requirements 7.2**
    - **Property 15: EmptyState renderizado para listas vazias**
    - **Validates: Requirements 5.4, 7.3**

- [x] 10. Frontend — Componentes de UX (`ConfirmDialog`, `EmptyState`, `extractApiError`, padrão de toast)
  - [x] 10.1 Criar `src/utils/api-error.ts` com utilitário `extractApiError`
    - Extrai `err.response?.data?.message` ou retorna `'Ocorreu um erro inesperado.'`
    - _Requirements: 7.5_

  - [x] 10.2 Criar `EmptyState` em `src/components/ui/EmptyState.tsx`
    - Props: `title: string`, `description?: string`, `icon?: ReactNode`, `action?: { label: string; onClick: () => void }`
    - Layout centralizado, acessível
    - _Requirements: 5.4, 7.3_

  - [x] 10.3 Criar `ConfirmDialog` em `src/components/ui/ConfirmDialog.tsx`
    - Props: `open`, `title`, `description`, `confirmLabel?`, `cancelLabel?`, `variant?` ('danger' | 'warning' | 'default'), `onConfirm`, `onCancel`, `loading?`
    - Modal acessível com `role="dialog"`, `aria-modal`, foco gerenciado
    - Botão "Confirmar" vermelho para `variant='danger'`
    - `onCancel` fecha sem executar operação
    - _Requirements: 7.1, 7.7_

  - [x] 10.4 Adicionar interceptor 403/401 em `src/services/api.ts`
    - No interceptor de resposta existente: ao receber 403, disparar `CustomEvent('auth:forbidden')` com mensagem
    - `App.tsx` escuta `auth:forbidden` e chama `toast.error`
    - _Requirements: 6.1, 6.5_

  - [x] 10.5 Aplicar padrão `onSuccess` + `onError` com `extractApiError` nas mutations que ainda não seguem o padrão
    - Mutations afetadas: criação/edição/exclusão de vagas, remoção de colaborador, aprovação/rejeição de vínculo
    - _Requirements: 7.4, 7.5_

  - [ ]* 10.6 Escrever testes de propriedade para `ConfirmDialog` (Property 13) e toast de mutations (Property 16)
    - **Property 13: ConfirmDialog exibido antes de qualquer operação destrutiva**
    - **Validates: Requirements 7.1, 7.7**
    - **Property 16: Toast de sucesso e erro para mutations**
    - **Validates: Requirements 7.4, 7.5**

  - [ ]* 10.7 Escrever testes de propriedade para cadastro de hotel — vínculo admin garantido (Property 3)
    - **Property 3: Cadastro de hotel sempre cria vínculo admin**
    - **Validates: Requirements 2.4**

- [ ] 11. Testes de propriedade — Notificação de admins ao criar vínculo (Property 9) e Selo de Verificação (Property 10)
  - [ ]* 11.1 Escrever teste de propriedade para notificação de admins (Property 9)
    - **Property 9: Para qualquer hotel com N admins aprovados, criar vínculo pending resulta em exatamente N notificações**
    - **Validates: Requirements 4.2**

  - [ ]* 11.2 Escrever teste de propriedade para Selo de Verificação (Property 10)
    - **Property 10: Selo de Verificação aparece sse `verified = true`**
    - **Validates: Requirements 4.6**

- [x] 12. Checkpoint Final — Todos os testes passando
  - Certifique-se de que todos os testes passam, que as rotas protegidas bloqueiam acessos não autorizados, e que os fluxos de login/cadastro Pessoa e Hotel funcionam de ponta a ponta. Pergunte ao usuário se houver dúvidas.

---

## Notes

- Tarefas marcadas com `*` são opcionais e podem ser puladas para uma entrega mais rápida de MVP
- Cada tarefa referencia os requisitos correspondentes para rastreabilidade
- Os testes de propriedade usam `eris` (PHP) e `fast-check` (TypeScript) conforme definido no design
- `HotelAuthContext` **não** mantém token Sanctum próprio — reutiliza o token do `AuthContext` existente
- O `ExperienceForm` já envia `hotel_id`; a correção é exclusivamente no `UserController` (backend)
- As páginas de dashboard do hotel (Task 7.2) podem ser stubs iniciais; o conteúdo completo vem em sprints subsequentes
- Checkpoints garantem validação incremental antes de avançar para a próxima camada

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3", "4.1"] },
    { "id": 1, "tasks": ["1.4", "1.5", "4.2", "10.1", "10.2", "10.3"] },
    { "id": 2, "tasks": ["1.6", "1.7", "4.3", "4.4", "4.5", "10.4"] },
    { "id": 3, "tasks": ["2.1", "2.2", "5.1", "5.3", "5.4", "6.1"] },
    { "id": 4, "tasks": ["2.3", "5.2", "6.2", "6.3"] },
    { "id": 5, "tasks": ["2.4", "4.6", "5.5", "6.4", "7.1"] },
    { "id": 6, "tasks": ["2.5", "2.6", "7.2", "7.3"] },
    { "id": 7, "tasks": ["9.1", "9.2", "10.5"] },
    { "id": 8, "tasks": ["9.3", "10.6", "10.7", "11.1", "11.2"] }
  ]
}
```
