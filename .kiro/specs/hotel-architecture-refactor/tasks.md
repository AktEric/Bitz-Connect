# Implementation Plan: Refatoramento Arquitetural — Hotel

## Overview

Este plano cobre o refatoramento completo do sistema de hotéis: separação de perfis, vínculo colaborador–hotel via experiências, sistema de seguidores, permissões administrativas granulares, fluxo de reivindicação e componentes reutilizáveis de UI. As tarefas de backend (Laravel) e frontend (React/TypeScript) são organizadas para execução paralela sempre que as dependências permitirem.

---

## Tasks

- [x] 1. Migrações de banco de dados e modelos Eloquent base
  - [x] 1.1 Criar as migrações de banco de dados
    - Criar migração `add_hotel_id_verified_to_experiences`: campo `hotel_id` (FK nullable) e `verified` (boolean) na tabela `experiences`
    - Criar migração `add_status_to_hotel_users`: alterar `role` para incluir `'employee'` e adicionar `status` enum (`pending`, `approved`, `rejected`)
    - Criar migração `create_hotel_followers_table`: tabela com `hotel_id`, `user_id`, `created_at` e unique constraint
    - Criar migração `add_followers_count_to_hotels`: coluna `followers_count` (unsigned int, default 0)
    - Criar migração `add_segment_to_hotels`: coluna `segment` (nullable string)
    - Criar migração `create_hotel_claims_table`: tabela com todos os campos especificados no design (seção 1.6)
    - Executar `php artisan migrate` e verificar que todas as migrações passam sem erros
    - _Requisitos: 3.5, 6.1, 6.2, 7.2, 8.1_

  - [x] 1.2 Atualizar os modelos Eloquent existentes
    - Adicionar `segment` e `followers_count` ao `$fillable` do modelo `Hotel`
    - Adicionar relacionamentos `followers()`, `hotelFollowers()`, `claims()` e `employees()` ao modelo `Hotel`
    - Adicionar `status` ao `$fillable` e casts ao modelo `HotelUser`; adicionar scopes `scopeApproved` e `scopePending`
    - Adicionar `hotel_id` e `verified` ao `$fillable` e casts do modelo `Experience`; adicionar relacionamento `hotel()`
    - _Requisitos: 6.1, 6.2, 8.1, 3.5_

  - [x] 1.3 Criar os novos modelos Eloquent
    - Criar `app/Models/HotelFollower.php` conforme design seção 2.4
    - Criar `app/Models/HotelClaim.php` conforme design seção 2.5
    - _Requisitos: 7.2, 8.1_

- [x] 2. DTOs, Repositórios e extensão do HotelService
  - [x] 2.1 Criar e estender DTOs de hotel
    - Criar `app/Domain/Hotel/DTOs/LinkExperienceDTO.php` conforme design seção 4.1
    - Criar `app/Domain/Hotel/DTOs/ClaimHotelDTO.php` conforme design seção 4.2
    - Estender `CreateHotelDTO.php` adicionando o campo `segment` conforme design seção 4.4
    - Estender `UpdateHotelDTO.php` adicionando o campo `segment` conforme design seção 4.3
    - _Requisitos: 3.5, 6.3, 7.2, 12.2_

  - [x] 2.2 Estender a HotelRepositoryInterface e EloquentHotelRepository
    - Adicionar métodos `findByMember`, `findForProfile` e `isFollowing` à `HotelRepositoryInterface`
    - Implementar os três métodos em `EloquentHotelRepository` conforme design seção 5.2
    - _Requisitos: 4.9, 8.6, 12.5_

  - [x] 2.3 Estender o HotelService com operações de role e invariante de admin
    - Adicionar métodos `hasAtLeastOneAdmin`, `promoteToAdmin` e `demoteToEmployee` ao `HotelService` conforme design seção 3.4
    - Lançar `UnprocessableEntityException` (HTTP 422) quando `demoteToEmployee` resultaria em hotel sem admin
    - _Requisitos: 3.3, 9.6, 9.7, 9.8_

- [x] 3. Serviços de domínio: EmployeeService, FollowerService e ClaimService
  - [x] 3.1 Implementar EmployeeService
    - Criar `app/Domain/Hotel/Services/EmployeeService.php`
    - Implementar `linkExperience`: atualiza `experience.hotel_id` e cria `hotel_users` com `role='employee'` e `status='pending'` via `firstOrCreate`
    - Implementar `approve`: atualiza `hotel_users.status='approved'` e `experiences.verified=true`; dispara evento de notificação
    - Implementar `reject`: atualiza `hotel_users.status='rejected'`; dispara evento de notificação
    - Implementar `listApproved` e `listPending` conforme design seção 3.1
    - _Requisitos: 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 12.1_

  - [x] 3.2 Implementar FollowerService
    - Criar `app/Domain/Hotel/Services/FollowerService.php`
    - Implementar `follow` com verificação de duplicidade (lança `ConflictException` 409) e `DB::transaction` para inserção + `increment`
    - Implementar `unfollow` com remoção + `decrement`
    - _Requisitos: 8.2, 8.3, 8.4, 8.5, 12.1_

  - [x] 3.3 Implementar ClaimService
    - Criar `app/Domain/Hotel/Services/ClaimService.php`
    - Implementar `claim`: verifica duplicidade pending (lança `ConflictException` 409), persiste `HotelClaim` e notifica `platform_admins`
    - Implementar `approve`: atualiza status, cria `hotel_users` admin aprovado e notifica requerente
    - Implementar `reject`: atualiza status e notifica requerente
    - _Requisitos: 7.2, 7.3, 7.4, 7.5, 7.6, 12.1_

- [x] 4. Policies Laravel e API Resources
  - [x] 4.1 Implementar HotelPolicy e HotelClaimPolicy
    - Criar `app/Policies/HotelPolicy.php` com métodos `update`, `uploadMedia`, `manageJobs`, `manageEmployees` e `manageRoles`, todos delegando ao helper privado `isAdmin`
    - Criar `app/Policies/HotelClaimPolicy.php` com método `review` verificando `role = 'platform_admin'`
    - Registrar as policies no `AuthServiceProvider`
    - _Requisitos: 9.1, 9.3, 9.5, 9.6, 9.7, 7.3, 12.3_

  - [x] 4.2 Criar e estender API Resources
    - Estender `HotelResource` adicionando os campos `segment`, `followers_count`, `employees_count`, `jobs_count`, `is_following` e `has_admin` conforme design seção 7.1
    - Criar `app/Http/Resources/EmployeeResource.php` conforme design seção 7.2
    - Criar `app/Http/Resources/HotelClaimResource.php` conforme design seção 7.3
    - _Requisitos: 4.2, 4.9, 5.2, 7.2, 12.4_

- [x] 5. Novos endpoints de API no HotelController e roteamento
  - [x] 5.1 Implementar ações de follow/unfollow no HotelController
    - Adicionar método `follow`: autorizar usuário autenticado, delegar ao `FollowerService::follow`, retornar 200 ou 409
    - Adicionar método `unfollow`: delegar ao `FollowerService::unfollow`, retornar 200
    - _Requisitos: 8.2, 8.3, 8.4, 8.5_

  - [x] 5.2 Implementar ações de funcionários no HotelController
    - Adicionar método `employees`: retorna `EmployeeResource::collection` dos aprovados via `EmployeeService::listApproved`
    - Adicionar método `pendingEmployees`: verifica policy `manageEmployees`, retorna lista via `EmployeeService::listPending`
    - Adicionar método `approveEmployee`: verifica policy, delega ao `EmployeeService::approve`
    - Adicionar método `rejectEmployee`: verifica policy, delega ao `EmployeeService::reject`
    - _Requisitos: 5.1, 6.4, 6.6, 6.7, 9.5_

  - [x] 5.3 Implementar ações de reivindicação e gestão de roles no HotelController
    - Adicionar método `claim`: delegar ao `ClaimService::claim` com `ClaimHotelDTO`
    - Adicionar método `promote`: verificar policy `manageRoles`, delegar ao `HotelService::promoteToAdmin`
    - Adicionar método `demote`: verificar policy `manageRoles`, delegar ao `HotelService::demoteToEmployee`
    - _Requisitos: 7.2, 9.6, 9.7, 9.8_

  - [x] 5.4 Registrar todas as novas rotas no routes/api.php
    - Adicionar grupo `Route::prefix('hotels/{hotel:slug}')` com rotas públicas e autenticadas conforme design seção 9
    - Garantir que o `HotelController@show` retorne os novos campos via `findForProfile` e `HotelResource` estendido
    - _Requisitos: 1.1, 4.9, 8.6, 12.6_


- [x] 6. Checkpoint backend — garantir que os testes passam
  - Executar `php artisan test` e corrigir falhas encontradas. Verificar em especial o fluxo completo de criação de hotel, vínculo e seguir/deixar de seguir. Consultar o usuário se surgirem dúvidas.

- [ ] 7. Testes de propriedade e unitários backend
  - [ ]* 7.1 Escrever teste de propriedade — Propriedade 1: Unicidade de slug
    - **Propriedade 1: Unicidade de slug gerado**
    - Gerar N slugs a partir de nomes aleatórios e verificar que nenhum contém caracteres inválidos e que são todos únicos
    - **Valida: Requisitos 3.1, 3.4**

  - [ ]* 7.2 Escrever teste de propriedade — Propriedade 2: Invariante de admin após criação
    - **Propriedade 2: Invariante de admin após criação**
    - Para qualquer hotel criado, verificar que existe exatamente 1 `hotel_users` com `role='admin'`, `status='approved'` e `user_id` do criador
    - **Valida: Requisitos 3.2, 3.3**

  - [ ]* 7.3 Escrever teste de propriedade — Propriedade 3: Invariante de ao menos um admin
    - **Propriedade 3: Invariante de ao menos um admin após operações de role**
    - Testar que `demoteToEmployee` lança exceção quando o hotel teria zero admins após a operação
    - **Valida: Requisitos 3.3, 9.7, 9.8**

  - [ ]* 7.4 Escrever teste de propriedade — Propriedade 8: Idempotência de vínculo hotel_users
    - **Propriedade 8: Idempotência de criação de vínculo hotel_users**
    - Chamar `linkExperience` N vezes para o mesmo par (user, hotel) e verificar que existe exatamente 1 registro em `hotel_users`
    - **Valida: Requisito 6.4**

  - [ ]* 7.5 Escrever teste de propriedade — Propriedade 10: Round-trip de seguir/deixar de seguir
    - **Propriedade 10: Round-trip de seguir e deixar de seguir**
    - Seguir e imediatamente deixar de seguir; verificar que `followers_count` retorna ao valor original e não existe registro em `hotel_followers`
    - **Valida: Requisitos 8.2, 8.3**

  - [ ]* 7.6 Escrever teste de propriedade — Propriedade 12: Idempotência de reivindicação pending
    - **Propriedade 12: Idempotência de reivindicação pending**
    - Tentar criar duas reivindicações pending para o mesmo par (user, hotel); a segunda deve retornar HTTP 409 e deve existir exatamente 1 registro pending
    - **Valida: Requisito 7.6**

  - [ ]* 7.7 Escrever teste de propriedade — Propriedade 13: Enforcement de permissões
    - **Propriedade 13: Enforcement de permissões administrativas**
    - Para qualquer usuário sem `role='admin'` aprovado, requisições de edição/gestão devem retornar 403 sem persistir alterações
    - **Valida: Requisitos 9.1, 9.3**

- [x] 8. Tipos TypeScript e serviços frontend
  - [x] 8.1 Estender tipos TypeScript
    - Adicionar campos `segment`, `followers_count`, `employees_count`, `jobs_count`, `is_following` e `has_admin` à interface `Hotel` em `hotel.types.ts`
    - Criar a interface `Employee` em `hotel.types.ts` conforme design seção 10.1
    - Criar a interface `HotelClaim` em `hotel.types.ts`
    - Adicionar campos `hotel_id`, `hotel?` e `verified` à interface `Experience` em `user.types.ts` conforme design seção 10.2
    - _Requisitos: 3.5, 4.2, 5.2, 6.1, 6.2, 8.6_

  - [x] 8.2 Adicionar novos métodos ao hotel.service.ts
    - Implementar `followHotel`, `unfollowHotel`, `getEmployees`, `getPendingEmployees`, `approveEmployee`, `rejectEmployee` e `claimHotel` conforme design seção 11.1
    - _Requisitos: 8.2, 8.3, 5.1, 6.4, 6.6, 6.7, 7.2_

- [x] 9. Hooks React Query para hotel
  - [x] 9.1 Implementar hooks de follow/unfollow
    - Adicionar `useFollowHotel` e `useUnfollowHotel` ao arquivo `src/hooks/useHotel.ts` conforme design seção 12.1
    - Implementar optimistic update: atualizar `is_following` e `followers_count` localmente antes da resposta; reverter em caso de erro
    - _Requisitos: 8.2, 8.3_

  - [x] 9.2 Implementar hooks de colaboradores
    - Adicionar `useHotelEmployees`, `usePendingEmployees`, `useApproveEmployee` e `useRejectEmployee` conforme design seção 12.2
    - _Requisitos: 5.1, 6.4, 6.6, 6.7_

  - [x] 9.3 Implementar hook de reivindicação
    - Criar `useClaimHotel` com `useMutation` que chama `hotelService.claimHotel` e invalida a query do perfil em caso de sucesso
    - _Requisito: 7.2_

- [x] 10. Componentes reutilizáveis de UI — links e avatares
  - [x] 10.1 Criar componentes UserLink e ProfileAvatar
    - Criar `src/components/common/UserLink.tsx` conforme design seção 13.2
    - Criar `src/components/common/ProfileAvatar.tsx` conforme design seção 13.4
    - _Requisitos: 10.1, 10.3_

  - [x] 10.2 Criar componentes CompanyLink e CompanyAvatar
    - Criar `src/components/common/CompanyLink.tsx` conforme design seção 13.3
    - Criar `src/components/common/CompanyAvatar.tsx` conforme design seção 13.5
    - _Requisitos: 10.2, 10.4_

  - [x] 10.3 Substituir renderizações de nomes/avatares no Feed, comentários e notificações
    - Localizar todos os locais onde nomes de usuários são renderizados como texto simples no feed, comentários, curtidas e notificações
    - Substituir por `UserLink` e `ProfileAvatar`
    - Localizar todos os locais onde nomes de hotéis são renderizados como texto simples
    - Substituir por `CompanyLink` e `CompanyAvatar`
    - _Requisitos: 10.5, 10.6_

  - [x] 10.4 Substituir renderizações de nomes/avatares em mensagens, conexões e busca
    - Substituir por `UserLink`/`ProfileAvatar` em componentes de mensagens e conexões
    - Substituir por `CompanyLink`/`CompanyAvatar` em resultados de busca e listas de vagas
    - _Requisitos: 10.5, 10.6_

- [x] 11. Componentes reutilizáveis de UI — cards e hover card
  - [x] 11.1 Criar EmployeeCard
    - Criar `src/components/hotel/EmployeeCard.tsx` conforme design seção 13.6
    - Incluir selo "Verificado" condicional com ícone e estilo correto
    - Incluir cálculo de duração via `formatDuration(started_at, now)`
    - _Requisitos: 5.2, 5.3, 5.4_

  - [x] 11.2 Criar ProfileCard e CompanyCard
    - Criar `src/components/common/ProfileCard.tsx` conforme design seção 13.8 (foto, nome, cargo, cidade, conexões, botão "Conectar")
    - Criar `src/components/common/CompanyCard.tsx` conforme design seção 13.9 (logo, nome, segmento, localização, seguidores, vagas, botão "Seguir")
    - _Requisitos: 11.1, 11.2_

  - [x] 11.3 Criar HoverCard e integrar com UserLink/CompanyLink
    - Criar `src/components/common/HoverCard.tsx` conforme design seção 13.7: delay de 300ms, oculto em telas < 1024px via `useMediaQuery`
    - Envolver `UserLink` com `HoverCard` exibindo `ProfileCard` com dados buscados via query com `staleTime: 5 * 60 * 1000`
    - Envolver `CompanyLink` com `HoverCard` exibindo `CompanyCard`
    - Não renderizar em viewports < 1024px (Requisito 11.4)
    - _Requisitos: 11.1, 11.2, 11.3, 11.4, 11.5_

- [ ] 12. Testes unitários dos componentes reutilizáveis
  - [ ]* 12.1 Escrever testes de propriedade — Propriedade 14: Links clicáveis com href correto
    - **Propriedade 14: Links clicáveis com href correto**
    - Para qualquer `uuid` de usuário, verificar que `UserLink` renderiza `<a>` com `href=/users/{uuid}`
    - Para qualquer `slug` de hotel, verificar que `CompanyLink` renderiza `<a>` com `href=/hotels/{slug}`
    - **Valida: Requisitos 10.1, 10.2, 10.3, 10.4**

  - [ ]* 12.2 Escrever testes de propriedade — Propriedade 7: Cálculo de tempo na empresa
    - **Propriedade 7: Cálculo de tempo na empresa não-negativo e monotônico**
    - Para datas válidas no passado, verificar que `formatDuration` retorna valor >= 0 e que datas mais antigas produzem durações maiores
    - **Valida: Requisito 5.4**

  - [ ]* 12.3 Escrever testes unitários para HoverCard
    - Verificar que `HoverCard` não renderiza o card em viewports < 1024px
    - Verificar que o card é exibido após hover com delay de ~300ms
    - _Requisito: 11.4_

- [x] 13. Refatoração do AppLayout: Sidebar e Topbar
  - [x] 13.1 Remover "Criar Hotel" da Topbar e da ProfilePage
    - Remover o item `{ label: 'Criar hotel', to: '/hotels/new' }` do array de itens do dropdown do usuário no `AppLayout`
    - Remover o botão/link de criação de hotel da `ProfilePage` do usuário
    - _Requisitos: 2.1, 2.2_

  - [x] 13.2 Adicionar seção "Empresas / Hotéis" na Sidebar
    - Adicionar seção com título, `SideNavItem` para "Meus Hotéis" (`/hotels`) e `SideNavItem` para "Criar Hotel" (`/hotels/new`) conforme design seção 14.2
    - _Requisitos: 2.3, 2.4, 2.5_

- [x] 14. Roteamento frontend e nova página MyHotelsPage
  - [x] 14.1 Adicionar rota alias /company/:slug e proteger /hotels/new
    - Registrar `<Route path="/company/:slug" element={<HotelProfilePage />} />` no roteador conforme design seção 14.5
    - Garantir que `/hotels/new` redireciona para login quando não autenticado (Requisito 2.6)
    - _Requisitos: 1.2, 1.3, 2.6_

  - [x] 14.2 Criar MyHotelsPage
    - Criar `src/pages/hotel/MyHotelsPage.tsx` que lista hotéis do usuário via `findByMember` (admin ou employee aprovado)
    - Incluir link para criação de novo hotel
    - Registrar a rota `/hotels` apontando para `MyHotelsPage`
    - _Requisitos: 2.3, 2.4_

- [x] 15. Refatoração da HotelProfilePage
  - [x] 15.1 Implementar estrutura de abas e action bar
    - Reestruturar `HotelProfilePage` com abas "Sobre", "Publicações", "Colaboradores" e "Vagas" conforme design seção 14.4
    - Implementar a action bar com botões condicionais: "Seguir"/"Deixar de Seguir" (baseado em `is_following`), "Ver Vagas", "Compartilhar"
    - Botão "Editar Perfil do Hotel" visível apenas para admins; botão "Reivindicar este Hotel" visível apenas se `!hotel.has_admin`
    - Exibir estatísticas: `employees_count`, `jobs_count`, `followers_count`
    - _Requisitos: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8_

  - [x] 15.2 Implementar aba de Colaboradores com gestão admin
    - Usar `useHotelEmployees` e renderizar lista de `EmployeeCard` na aba "Colaboradores"
    - Exibir mensagem de ausência quando lista vazia (Requisito 5.5)
    - Para admins, exibir adicionalmente lista de pendentes com botões "Aprovar"/"Rejeitar" usando `usePendingEmployees`, `useApproveEmployee` e `useRejectEmployee`
    - _Requisitos: 5.1, 5.2, 5.5, 6.6, 6.7_

  - [x] 15.3 Implementar tratamento de erro 404 e exibição de banner/logo
    - Distinguir erro 404 (hotel não encontrado) de outros erros de rede e exibir página de erro adequada
    - Garantir que banner, logo, nome, descrição, segmento, localização, website, telefone e email são exibidos corretamente
    - _Requisitos: 1.5, 4.1_

- [x] 16. Integrar vínculo via experiência no formulário de Experience
  - [x] 16.1 Adicionar seletor de hotel no formulário de criação/edição de Experience
    - Adicionar campo de busca/seleção de hotel (autocomplete) no formulário de experiência
    - Ao selecionar um hotel, enviar `hotel_id` junto com os demais dados da experiência
    - _Requisitos: 6.3_

  - [x] 16.2 Exibir selo de vínculo confirmado no perfil do usuário
    - Quando `experience.verified = true`, exibir ícone/badge "Verificado" na experiência listada no `UserProfilePage`
    - _Requisito: 6.9_

- [x] 17. Checkpoint final — testes completos e integração
  - Executar `php artisan test` no backend e a suíte de testes do frontend. Verificar os fluxos críticos: criação de hotel, vínculo de colaborador, seguir/deixar de seguir, reivindicação, permissões admin e hover cards. Consultar o usuário se surgirem dúvidas.

- [ ] 18. Testes de integração e propriedades finais
  - [ ]* 18.1 Escrever teste de propriedade — Propriedade 4: Round-trip de persistência dos campos do Hotel
    - **Propriedade 4: Round-trip de persistência dos campos do Hotel**
    - Criar um hotel com conjunto aleatório de campos válidos (incluindo `segment`) e recuperá-lo pelo slug; verificar que os campos retornados são idênticos aos enviados
    - **Valida: Requisito 3.5**

  - [ ]* 18.2 Escrever teste de propriedade — Propriedade 5: Contagens corretas no endpoint de perfil
    - **Propriedade 5: Contagens corretas no endpoint de perfil**
    - Para N colaboradores aprovados, M vagas ativas e K seguidores, verificar que o endpoint retorna exatamente N, M e K nos campos correspondentes
    - **Valida: Requisitos 4.9, 8.2, 8.3**

  - [ ]* 18.3 Escrever teste de propriedade — Propriedade 6: Filtro correto de colaboradores aprovados
    - **Propriedade 6: Filtro correto de colaboradores aprovados**
    - Com N aprovados e M pendentes/rejeitados, verificar que `GET /hotels/:slug/employees` retorna exatamente N registros todos com `status='approved'`
    - **Valida: Requisito 5.1**

  - [ ]* 18.4 Escrever teste de propriedade — Propriedade 9: Round-trip de aprovação/rejeição de vínculo
    - **Propriedade 9: Round-trip de aprovação e rejeição de vínculo**
    - Após aprovar: verificar `hotel_users.status='approved'` E `experiences.verified=true`; após rejeitar: verificar `status='rejected'` e nunca `pending`
    - **Valida: Requisitos 6.6, 6.7**

  - [ ]* 18.5 Escrever teste de propriedade — Propriedade 11: Consistência de is_following
    - **Propriedade 11: Consistência de `is_following`**
    - Para qualquer par (usuário, hotel), `is_following=true` se e somente se existe registro em `hotel_followers`
    - **Valida: Requisito 8.6**

---

## Notes

- Tarefas marcadas com `*` são opcionais e podem ser puladas para uma entrega mais rápida
- Todas as tarefas referenciam requisitos específicos para rastreabilidade
- Checkpoints nas tarefas 6 e 17 garantem validação incremental
- Backend (Laravel) e frontend (React/TypeScript) podem ser executados em paralelo a partir da wave 1
- As tarefas de backend dependem das migrações (tarefa 1) antes de qualquer implementação de serviço
- O design não usa pseudocódigo — linguagens já determinadas: PHP/Laravel (backend) e TypeScript/React (frontend)

---

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "1.3"] },
    { "id": 2, "tasks": ["2.1", "2.2", "8.1"] },
    { "id": 3, "tasks": ["2.3", "3.1", "3.2", "3.3", "8.2"] },
    { "id": 4, "tasks": ["4.1", "4.2", "9.1", "9.2", "9.3"] },
    { "id": 5, "tasks": ["5.1", "5.2", "5.3", "10.1", "10.2"] },
    { "id": 6, "tasks": ["5.4", "7.1", "7.2", "7.3", "10.3"] },
    { "id": 7, "tasks": ["7.4", "7.5", "7.6", "7.7", "10.4"] },
    { "id": 8, "tasks": ["11.1", "11.2", "13.1", "14.1", "16.1"] },
    { "id": 9, "tasks": ["11.3", "12.1", "12.2", "13.2", "14.2"] },
    { "id": 10, "tasks": ["12.3", "15.1", "16.2"] },
    { "id": 11, "tasks": ["15.2", "15.3"] },
    { "id": 12, "tasks": ["18.1", "18.2", "18.3", "18.4", "18.5"] }
  ]
}
```
