# Requirements Document

## Introduction

Este documento especifica os requisitos para o refatoramento arquitetural do sistema de hotéis. O escopo abrange a separação definitiva entre perfis de pessoa e de empresa/hotel, a criação de um fluxo de vínculo colaborador–hotel baseado em experiências, o sistema de seguidores de hotel, as permissões administrativas granulares, o sistema de reivindicação de hotel, e a introdução de componentes reutilizáveis de UI com hover cards. Todas as novas implementações devem seguir a arquitetura de domínio existente (Services / DTOs / Repositories / Policies).

---

## Glossary

- **Sistema**: O conjunto formado por backend Laravel e frontend React da plataforma.
- **HotelService**: Serviço de domínio responsável pelas regras de negócio relacionadas a hotéis.
- **EmployeeService**: Serviço de domínio responsável pelo gerenciamento de vínculos colaborador–hotel.
- **FollowerService**: Serviço de domínio responsável pela lógica de seguir/deixar de seguir hotéis.
- **ClaimService**: Serviço de domínio responsável pelo fluxo de reivindicação de hotel.
- **HotelUser**: Registro da tabela `hotel_users`, que representa um vínculo entre um usuário e um hotel com um papel (`role`) e status (`status`).
- **HotelFollower**: Registro da tabela `hotel_followers`, que representa um usuário seguindo um hotel.
- **Experience**: Registro da tabela `experiences`, que representa uma experiência profissional de um usuário, podendo estar vinculada a um hotel via `hotel_id`.
- **Vínculo**: Associação verificada entre a experiência de um usuário e um hotel, após aprovação por um administrador do hotel.
- **Admin**: Usuário com `role = 'admin'` na tabela `hotel_users` para um determinado hotel.
- **Employee**: Usuário com `role = 'employee'` e `status = 'approved'` na tabela `hotel_users` para um determinado hotel.
- **Owner**: Primeiro administrador de um hotel, criado automaticamente no momento da criação do hotel.
- **HotelProfilePage**: Página frontend localizada em `/hotels/:slug` que exibe o perfil público de um hotel.
- **UserProfilePage**: Página frontend que exibe o perfil público de um usuário.
- **UserLink**: Componente React reutilizável para renderizar um link clicável para o perfil de um usuário.
- **CompanyLink**: Componente React reutilizável para renderizar um link clicável para o perfil de um hotel.
- **ProfileAvatar**: Componente React reutilizável para renderizar o avatar de um usuário.
- **CompanyAvatar**: Componente React reutilizável para renderizar o logo de um hotel.
- **ProfileCard**: Componente React reutilizável para renderizar o cartão de perfil de um usuário.
- **CompanyCard**: Componente React reutilizável para renderizar o cartão de perfil de um hotel.
- **EmployeeCard**: Componente React reutilizável para renderizar o cartão de um colaborador.
- **HoverCard**: Componente desktop que exibe um cartão de prévia ao passar o cursor sobre um link de usuário ou hotel.
- **Sidebar**: Menu lateral de navegação da aplicação.
- **Slug**: Identificador único baseado no nome do hotel, usado nas URLs.

---

## Requirements

### Requisito 1 — Separação de Perfis: Pessoa e Hotel

**User Story:** Como usuário da plataforma, quero que perfis de pessoa e de hotel sejam entidades completamente distintas, com suas próprias URLs, permissões e páginas, para que a navegação entre os dois contextos seja clara e sem ambiguidade.

#### Critérios de Aceitação

1. THE Sistema SHALL manter `/hotels/:slug` como a URL canônica do perfil de hotel no backend.
2. THE Sistema SHALL aceitar `/company/:slug` como alias de frontend para `/hotels/:slug`, redirecionando internamente sem alterar as rotas de API.
3. THE Sistema SHALL exibir a `HotelProfilePage` ao acessar `/hotels/:slug` ou `/company/:slug`.
4. THE Sistema SHALL exibir a `UserProfilePage` ao acessar o perfil de um usuário, sem misturar dados de hotéis no layout da página.
5. WHEN um usuário acessa `/hotels/:slug` de um hotel inexistente, THE Sistema SHALL retornar o status HTTP 404 e exibir uma página de erro adequada.

---

### Requisito 2 — Criação de Hotel via Menu "Empresas / Hotéis"

**User Story:** Como usuário autenticado, quero criar um hotel exclusivamente pela seção "Empresas / Hotéis" da sidebar, para que o fluxo de criação seja consistente e fácil de localizar.

#### Critérios de Aceitação

1. THE Sistema SHALL remover o botão/opção "Criar Hotel" da `ProfilePage` do usuário.
2. THE Sistema SHALL remover o botão/opção "Criar Hotel" do dropdown da `AppLayout` topbar.
3. THE Sistema SHALL exibir uma seção chamada "Empresas / Hotéis" na `Sidebar`, contendo a opção de criar um novo hotel.
4. WHEN um usuário autenticado acessa a seção "Empresas / Hotéis" na Sidebar, THE Sistema SHALL exibir a lista de hotéis dos quais o usuário é admin ou employee aprovado.
5. WHEN um usuário autenticado aciona a criação de hotel pela seção "Empresas / Hotéis", THE Sistema SHALL exibir o formulário de criação de hotel.
6. IF um usuário não autenticado tenta acessar a criação de hotel, THEN THE Sistema SHALL redirecionar para a página de login.

---

### Requisito 3 — Criação de Hotel e Atribuição Automática de Owner

**User Story:** Como usuário autenticado, quero que ao criar um hotel eu me torne automaticamente o owner e admin principal, para que eu tenha controle imediato sobre o hotel criado.

#### Critérios de Aceitação

1. WHEN um usuário autenticado cria um hotel com nome, segmento e localização válidos, THE HotelService SHALL gerar um slug único a partir do nome do hotel e persistir o registro.
2. WHEN um hotel é criado com sucesso, THE HotelService SHALL criar um registro em `hotel_users` associando o criador com `role = 'admin'` e `status = 'approved'`.
3. THE HotelService SHALL garantir que cada hotel possua ao menos um registro em `hotel_users` com `role = 'admin'` em todo momento após a criação.
4. WHEN o nome de um hotel já resulta em um slug existente, THE HotelService SHALL acrescentar um sufixo numérico incremental ao slug para garantir unicidade.
5. THE Sistema SHALL armazenar os campos `name`, `slug`, `description`, `segment`, `logo_path`, `cover_path`, `city`, `state`, `country`, `website`, `phone` e `contact_email` no modelo `Hotel`.

---

### Requisito 4 — Página de Perfil do Hotel

**User Story:** Como visitante da plataforma, quero visualizar o perfil completo de um hotel, incluindo informações, estatísticas e publicações, para que eu possa avaliar o hotel como potencial empregador ou parceiro.

#### Critérios de Aceitação

1. THE `HotelProfilePage` SHALL exibir banner, logo, nome, descrição, segmento, localização, website, telefone e e-mail de contato do hotel.
2. THE `HotelProfilePage` SHALL exibir as estatísticas: contagem de colaboradores aprovados (`employees_count`), contagem de vagas ativas (`jobs_count`) e contagem de seguidores (`followers_count`).
3. THE `HotelProfilePage` SHALL exibir as publicações do hotel em ordem cronológica decrescente.
4. THE `HotelProfilePage` SHALL exibir o botão "Seguir" para usuários autenticados que ainda não seguem o hotel.
5. THE `HotelProfilePage` SHALL exibir o botão "Deixar de Seguir" para usuários autenticados que já seguem o hotel.
6. THE `HotelProfilePage` SHALL exibir o botão "Ver Vagas" que navega para a listagem de vagas do hotel.
7. THE `HotelProfilePage` SHALL exibir o botão "Compartilhar" que copia a URL canônica do hotel para a área de transferência.
8. WHEN o usuário autenticado é admin do hotel, THE `HotelProfilePage` SHALL exibir o botão "Editar Perfil do Hotel".
9. THE Sistema SHALL retornar `followers_count`, `employees_count` e `jobs_count` calculados via eager loading ou contagem otimizada no endpoint de perfil do hotel.

---

### Requisito 5 — Aba de Colaboradores do Hotel

**User Story:** Como visitante da plataforma, quero visualizar os colaboradores verificados de um hotel, com suas informações profissionais e o selo de vínculo confirmado, para que eu possa entender a equipe do hotel.

#### Critérios de Aceitação

1. THE `HotelProfilePage` SHALL exibir uma aba "Colaboradores" listando todos os usuários com `role = 'employee'` e `status = 'approved'` na tabela `hotel_users`.
2. THE `EmployeeCard` SHALL exibir foto, nome, cargo, departamento, tempo na empresa e o selo de vínculo confirmado para cada colaborador.
3. WHEN um visitante clica no nome ou foto de um colaborador no `EmployeeCard`, THE Sistema SHALL navegar para o `UserProfilePage` desse colaborador.
4. THE Sistema SHALL calcular o tempo na empresa a partir da data de início da experiência vinculada ao hotel.
5. WHEN um hotel não possui colaboradores aprovados, THE `HotelProfilePage` SHALL exibir uma mensagem informando que não há colaboradores listados.

---

### Requisito 6 — Fluxo de Vínculo Pessoa–Hotel via Experiência

**User Story:** Como usuário autenticado, quero vincular uma experiência profissional a um hotel cadastrado na plataforma, para que meu vínculo seja verificado e eu apareça como colaborador na página do hotel.

#### Critérios de Aceitação

1. THE Sistema SHALL adicionar o campo `hotel_id` (nullable, FK para `hotels.id`) ao modelo `Experience`.
2. THE Sistema SHALL adicionar o campo `verified` (boolean, default `false`) ao modelo `Experience`.
3. WHEN um usuário cria ou edita uma experiência e seleciona um hotel cadastrado, THE EmployeeService SHALL associar o `hotel_id` correspondente à experiência.
4. WHEN uma experiência é associada a um `hotel_id`, THE EmployeeService SHALL criar um registro em `hotel_users` com `role = 'employee'` e `status = 'pending'` para o usuário, caso ainda não exista.
5. WHEN um registro de vínculo `pending` é criado em `hotel_users`, THE Sistema SHALL notificar todos os admins do hotel sobre a solicitação pendente.
6. WHEN um admin do hotel aprova uma solicitação de vínculo, THE EmployeeService SHALL atualizar `hotel_users.status` para `'approved'` e `experiences.verified` para `true`.
7. WHEN um admin do hotel rejeita uma solicitação de vínculo, THE EmployeeService SHALL atualizar `hotel_users.status` para `'rejected'`.
8. WHEN uma solicitação de vínculo é aprovada ou rejeitada, THE Sistema SHALL notificar o usuário solicitante sobre o resultado.
9. WHEN `experiences.verified = true`, THE Sistema SHALL exibir o selo de vínculo confirmado na experiência do perfil do usuário.

---

### Requisito 7 — Reivindicação de Hotel

**User Story:** Como representante de um hotel sem admin cadastrado, quero reivindicar a administração do hotel, para que eu possa gerenciar o perfil e as vagas do hotel.

#### Critérios de Aceitação

1. WHEN um hotel não possui nenhum registro em `hotel_users` com `role = 'admin'`, THE `HotelProfilePage` SHALL exibir o botão "Reivindicar este Hotel".
2. WHEN um usuário autenticado aciona "Reivindicar este Hotel", THE ClaimService SHALL criar uma solicitação de reivindicação associando o usuário ao hotel com status `pending`.
3. WHEN uma solicitação de reivindicação é criada, THE Sistema SHALL notificar os administradores da plataforma (`role = 'platform_admin'`) sobre a nova solicitação.
4. WHEN um administrador da plataforma aprova uma reivindicação, THE ClaimService SHALL criar um registro em `hotel_users` com `role = 'admin'` e `status = 'approved'` para o usuário reivindicante.
5. WHEN uma solicitação de reivindicação é aprovada ou rejeitada, THE Sistema SHALL notificar o usuário reivindicante sobre o resultado.
6. IF um usuário autenticado já possui uma solicitação de reivindicação `pending` para o mesmo hotel, THEN THE ClaimService SHALL rejeitar a criação de uma nova solicitação e retornar erro HTTP 409.

---

### Requisito 8 — Sistema de Seguidores de Hotel

**User Story:** Como usuário autenticado, quero seguir e deixar de seguir hotéis, para que eu possa acompanhar as novidades e vagas dos hotéis de meu interesse.

#### Critérios de Aceitação

1. THE Sistema SHALL criar a tabela `hotel_followers` com colunas `hotel_id`, `user_id` e `created_at`.
2. WHEN um usuário autenticado aciona "Seguir" em um hotel que ainda não segue, THE FollowerService SHALL inserir um registro em `hotel_followers` e incrementar `hotels.followers_count`.
3. WHEN um usuário autenticado aciona "Deixar de Seguir" em um hotel que já segue, THE FollowerService SHALL remover o registro de `hotel_followers` e decrementar `hotels.followers_count`.
4. IF um usuário autenticado tenta seguir um hotel que já segue, THEN THE FollowerService SHALL retornar erro HTTP 409 sem duplicar o registro.
5. IF um usuário não autenticado tenta seguir um hotel, THEN THE Sistema SHALL retornar erro HTTP 401.
6. THE Sistema SHALL retornar o estado `is_following` (boolean) para o usuário autenticado no endpoint de perfil do hotel.

---

### Requisito 9 — Permissões Administrativas do Hotel

**User Story:** Como admin de um hotel, quero ter controle exclusivo sobre a edição do perfil, gestão de vagas, aprovação de colaboradores e gerenciamento de admins, para que dados incorretos não sejam publicados por terceiros.

#### Critérios de Aceitação

1. WHEN um usuário sem `role = 'admin'` em `hotel_users` tenta editar o perfil do hotel, THE Sistema SHALL retornar erro HTTP 403.
2. WHEN um admin do hotel aciona a edição do perfil, THE HotelService SHALL persistir as alterações nos campos permitidos do modelo `Hotel`.
3. WHEN um usuário sem `role = 'admin'` tenta criar ou excluir vagas do hotel, THE Sistema SHALL retornar erro HTTP 403.
4. WHEN um admin do hotel responde a candidaturas de vagas, THE Sistema SHALL permitir a alteração do status da candidatura.
5. WHEN um admin do hotel aprova ou rejeita solicitações de vínculo de colaboradores, THE EmployeeService SHALL processar a solicitação conforme o Requisito 6.
6. WHEN um admin do hotel promove um colaborador aprovado a admin, THE Sistema SHALL atualizar `hotel_users.role` para `'admin'`.
7. WHEN um admin do hotel revoga o papel de admin de outro usuário, THE Sistema SHALL atualizar `hotel_users.role` para `'employee'`, desde que o hotel mantenha ao menos um admin restante.
8. IF a remoção de um admin resultaria em um hotel sem nenhum admin, THEN THE Sistema SHALL rejeitar a operação e retornar erro HTTP 422.

---

### Requisito 10 — Links Clicáveis em Toda a Plataforma

**User Story:** Como usuário da plataforma, quero que todos os nomes de pessoas e hotéis no feed, comentários, curtidas, mensagens, notificações, conexões e busca sejam links clicáveis que levam ao respectivo perfil.

#### Critérios de Aceitação

1. THE `UserLink` SHALL renderizar o nome de um usuário como um link clicável que navega para o `UserProfilePage` correspondente.
2. THE `CompanyLink` SHALL renderizar o nome de um hotel como um link clicável que navega para a `HotelProfilePage` correspondente.
3. THE `ProfileAvatar` SHALL renderizar o avatar de um usuário como um elemento clicável que navega para o `UserProfilePage` correspondente.
4. THE `CompanyAvatar` SHALL renderizar o logo de um hotel como um elemento clicável que navega para a `HotelProfilePage` correspondente.
5. THE Sistema SHALL utilizar `UserLink` em publicações do feed, comentários, curtidas, notificações, mensagens, conexões e resultados de busca.
6. THE Sistema SHALL utilizar `CompanyLink` em publicações do feed, comentários, notificações, resultados de busca e listas de vagas.

---

### Requisito 11 — Hover Cards de Prévia de Perfil (Desktop)

**User Story:** Como usuário desktop, quero que ao passar o cursor sobre o nome ou avatar de uma pessoa ou hotel apareça um cartão de prévia com as informações mais relevantes, para que eu possa decidir se quero visitar o perfil completo.

#### Critérios de Aceitação

1. WHILE o cursor estiver sobre um `UserLink` ou `ProfileAvatar` em um dispositivo desktop, THE `HoverCard` SHALL exibir o `ProfileCard` com foto, nome, cargo atual, cidade, contagem de conexões e botão "Conectar".
2. WHILE o cursor estiver sobre um `CompanyLink` ou `CompanyAvatar` em um dispositivo desktop, THE `HoverCard` SHALL exibir o `CompanyCard` com logo, nome, segmento, localização, contagem de seguidores, contagem de vagas ativas e botão "Seguir".
3. WHEN o cursor sair do `HoverCard` ou do elemento que o ativou, THE `HoverCard` SHALL ser ocultado.
4. THE Sistema SHALL não exibir `HoverCard` em dispositivos com largura de tela inferior a 1024px.
5. WHEN o `HoverCard` é acionado, THE Sistema SHALL buscar os dados do perfil via API somente se os dados ainda não estiverem em cache local.

---

### Requisito 12 — Conformidade com a Arquitetura de Domínio

**User Story:** Como desenvolvedor do projeto, quero que todas as novas funcionalidades respeitem a arquitetura de domínio existente (Services, Policies, DTOs, Resources, Repositories), para que o código permaneça consistente, testável e de fácil manutenção.

#### Critérios de Aceitação

1. THE Sistema SHALL implementar `EmployeeService`, `FollowerService` e `ClaimService` dentro de `app/Domain/Hotel/Services/`.
2. THE Sistema SHALL implementar DTOs para cada operação de escrita dos novos serviços dentro de `app/Domain/Hotel/DTOs/`.
3. THE Sistema SHALL implementar Policies Laravel para autorização de cada ação administrativa do hotel dentro de `app/Policies/`.
4. THE Sistema SHALL implementar API Resources para serializar respostas de hotéis, colaboradores e seguidores dentro de `app/Http/Resources/`.
5. WHERE o acesso a dados de hotel requer consultas complexas ou reutilizáveis, THE Sistema SHALL implementar os métodos correspondentes na `HotelRepositoryInterface` e `EloquentHotelRepository`.
6. THE Sistema SHALL garantir que nenhum controller acesse o banco de dados diretamente, delegando toda lógica de negócio aos respectivos Services.
