# Requirements Document

## Introduction

Esta sprint cobre correções e melhorias no sistema de autenticação e vínculos da plataforma. O escopo inclui: separação completa dos fluxos de login e cadastro entre Pessoa e Hotel (com URLs e visuais distintos), isolamento total de perfis e dashboards por tipo de conta, correção do fluxo de vínculo profissional via `ExperienceForm`, ajuste na listagem de colaboradores, validação de permissões com redirecionamentos adequados, e melhorias gerais de UX (loaders, confirmações, estados vazios e feedbacks). O backend utiliza Laravel + Sanctum com arquitetura de domínio; o frontend utiliza React + TypeScript + React Query + React Router.

---

## Glossary

- **Sistema**: O conjunto formado pelo backend Laravel e pelo frontend React da plataforma.
- **Pessoa**: Usuário comum registrado na tabela `users` com `role = 'user'` ou `role = 'platform_admin'`.
- **Hotel**: Entidade autenticável independente registrada na tabela `hotels`, com sua própria tabela de tokens/sessão via Sanctum.
- **HotelAdmin**: Usuário com registro em `hotel_users` com `role = 'admin'` e `status = 'approved'` para um determinado hotel.
- **HotelEmployee**: Usuário com registro em `hotel_users` com `role = 'employee'` e `status = 'approved'` para um determinado hotel.
- **AuthService**: Serviço de domínio responsável pela autenticação de Pessoa (tabela `users`).
- **HotelAuthService**: Serviço de domínio responsável pela autenticação de Hotel (tabela `hotels`).
- **EmployeeService**: Serviço de domínio responsável pelo gerenciamento de vínculos colaborador–hotel.
- **DashboardPessoa**: Interface de navegação e conteúdo exclusiva para usuários do tipo Pessoa, acessível após login em `/login/pessoa`.
- **DashboardHotel**: Interface de navegação e conteúdo exclusiva para contas de Hotel, acessível após login em `/login/hotel`.
- **ExperienceForm**: Formulário React utilizado para criar e editar experiências profissionais de um usuário Pessoa.
- **Vínculo**: Associação entre a experiência de um Pessoa e um Hotel, iniciada pelo usuário e confirmada por aprovação de um HotelAdmin.
- **Selo de Verificação**: Indicador visual exibido em experiências e no `EmployeeCard` quando `experiences.verified = true` e `hotel_users.status = 'approved'`.
- **hotel_users**: Tabela de pivô que associa usuários a hotéis com campos `role` (`admin` | `employee`) e `status` (`pending` | `approved` | `rejected`).
- **GuestRoute**: Componente React que impede acesso a rotas de autenticação para usuários já logados.
- **PrivateRoute**: Componente React que redireciona para login usuários não autenticados.

---

## Requirements

### Requisito 1 — Login Separado por Tipo de Conta

**User Story:** Como usuário da plataforma, quero acessar telas de login distintas para Pessoa e Hotel, com URLs e visuais separados, para que não haja ambiguidade sobre qual tipo de conta estou autenticando.

#### Acceptance Criteria

1. THE Sistema SHALL disponibilizar a rota `/login/pessoa` para autenticação de Pessoa, utilizando a tabela `users` e tokens Sanctum de usuário.
2. THE Sistema SHALL disponibilizar a rota `/login/hotel` para autenticação de Hotel, utilizando credenciais vinculadas à tabela `hotels` e tokens Sanctum separados.
3. WHEN um Pessoa se autentica com sucesso em `/login/pessoa`, THE Sistema SHALL redirecionar para o feed pessoal em `/feed`.
4. WHEN um Hotel se autentica com sucesso em `/login/hotel`, THE Sistema SHALL redirecionar para o dashboard administrativo do hotel.
5. THE Sistema SHALL exibir visual (layout, cores, logotipo e título) distinto entre `/login/pessoa` e `/login/hotel`.
6. IF um usuário não autenticado acessa uma rota protegida de Pessoa, THEN THE Sistema SHALL redirecionar para `/login/pessoa`.
7. IF um usuário não autenticado acessa uma rota protegida de Hotel, THEN THE Sistema SHALL redirecionar para `/login/hotel`.
8. WHEN um Pessoa já autenticado tenta acessar `/login/pessoa`, THE Sistema SHALL redirecionar para `/feed`.
9. WHEN um Hotel já autenticado tenta acessar `/login/hotel`, THE Sistema SHALL redirecionar para o dashboard do hotel.

---

### Requisito 2 — Cadastro Separado por Tipo de Conta

**User Story:** Como visitante da plataforma, quero ver claramente as duas opções de cadastro (Pessoa e Hotel) e seguir fluxos distintos para cada tipo, para que a criação de conta seja intuitiva e adequada ao meu perfil.

#### Acceptance Criteria

1. THE Sistema SHALL exibir na tela inicial de cadastro duas opções visuais distintas: "Criar conta de Pessoa" e "Criar conta de Hotel".
2. WHEN um visitante seleciona "Criar conta de Pessoa", THE Sistema SHALL exibir o formulário com os campos: Nome, E-mail e Senha.
3. WHEN um visitante seleciona "Criar conta de Hotel", THE Sistema SHALL exibir um fluxo de dois passos: Passo 1 — criar conta de Pessoa (Nome, E-mail, Senha); Passo 2 — criar o Hotel (Nome, CNPJ, E-mail, Senha, Cidade e Website opcional).
4. WHEN o Passo 2 do cadastro de Hotel é concluído com sucesso, THE EmployeeService SHALL criar um registro em `hotel_users` associando o usuário Pessoa ao Hotel com `role = 'admin'` e `status = 'approved'`.
5. IF o CNPJ informado no cadastro de Hotel já estiver vinculado a outro hotel existente, THEN THE Sistema SHALL rejeitar o cadastro e retornar mensagem de erro informando duplicidade.
6. THE Sistema SHALL validar formato de CNPJ no backend antes de persistir o registro do Hotel.
7. WHEN o cadastro de Pessoa é concluído com sucesso, THE Sistema SHALL autenticar o usuário automaticamente e redirecionar para `/feed`.
8. WHEN o cadastro de Hotel (ambos os passos) é concluído com sucesso, THE Sistema SHALL autenticar o usuário e redirecionar para o dashboard do hotel recém-criado.

---

### Requisito 3 — Perfis Independentes e Dashboards Separados

**User Story:** Como usuário da plataforma, quero que minha sessão como Pessoa nunca exiba menus ou funcionalidades de administração de hotel, e vice-versa, para que a interface seja focada e livre de opções irrelevantes.

#### Acceptance Criteria

1. THE DashboardPessoa SHALL exibir exclusivamente menus de: Feed, Rede de Contatos, Perfil Pessoal, Busca, Mensagens, Notificações e Vagas.
2. THE DashboardHotel SHALL exibir exclusivamente menus de: Editar Perfil do Hotel, Gerenciar Vagas, Gerenciar Colaboradores, Publicações do Hotel, Configurações e Administradores.
3. THE Sistema SHALL nunca exibir menus do DashboardHotel dentro da sessão de Pessoa.
4. THE Sistema SHALL nunca exibir menus do DashboardPessoa dentro da sessão de Hotel.
5. WHEN um Pessoa autenticado tenta acessar uma rota do DashboardHotel, THE Sistema SHALL redirecionar para `/feed` com mensagem de acesso negado.
6. WHEN um Hotel autenticado tenta acessar uma rota do DashboardPessoa, THE Sistema SHALL redirecionar para o dashboard do hotel com mensagem de acesso negado.
7. THE Sistema SHALL manter contextos de autenticação separados em memória e em armazenamento local para Pessoa e Hotel.

---

### Requisito 4 — Correção do Fluxo de Vínculo Profissional via ExperienceForm

**User Story:** Como usuário Pessoa autenticado, quero que ao selecionar um hotel no ExperienceForm seja criada uma solicitação de vínculo pendente, para que o admin do hotel possa aprovar ou recusar minha associação profissional.

#### Acceptance Criteria

1. WHEN um usuário Pessoa cria ou edita uma experiência e seleciona um hotel cadastrado no campo de seleção, THE EmployeeService SHALL associar o `hotel_id` à experiência e criar um registro em `hotel_users` com `role = 'employee'` e `status = 'pending'`, se o registro ainda não existir.
2. WHEN a solicitação de vínculo é criada com `status = 'pending'`, THE Sistema SHALL notificar todos os HotelAdmins do hotel selecionado.
3. WHEN um HotelAdmin aprova a solicitação de vínculo, THE EmployeeService SHALL atualizar `hotel_users.status` para `'approved'` e `experiences.verified` para `true`.
4. WHEN um HotelAdmin rejeita a solicitação de vínculo, THE EmployeeService SHALL atualizar `hotel_users.status` para `'rejected'`.
5. WHEN a solicitação é aprovada ou rejeitada, THE Sistema SHALL notificar o usuário Pessoa solicitante sobre o resultado.
6. WHEN `experiences.verified = true`, THE Sistema SHALL exibir o Selo de Verificação na experiência listada no perfil do usuário Pessoa.
7. THE ExperienceForm SHALL exibir o campo de seleção de hotel com autocomplete que busca hotéis cadastrados pelo nome via API.
8. IF o usuário Pessoa já possui um vínculo `pending` ou `approved` com o hotel selecionado, THEN THE Sistema SHALL informar o usuário sobre o status atual do vínculo sem criar duplicidade.

---

### Requisito 5 — Listagem de Colaboradores Aprovados

**User Story:** Como visitante da página de um hotel, quero visualizar apenas os colaboradores com vínculo confirmado, com informações completas e o selo de verificação, para que a lista reflita apenas vínculos legítimos.

#### Acceptance Criteria

1. THE Sistema SHALL listar na aba "Colaboradores" somente os registros de `hotel_users` com `role = 'employee'` e `status = 'approved'`.
2. THE `EmployeeCard` SHALL exibir: foto do colaborador, nome, cargo (título da experiência vinculada), data de entrada e Selo de Verificação.
3. THE Sistema SHALL calcular a data de entrada a partir do campo `started_at` da experiência vinculada ao hotel.
4. WHEN a lista de colaboradores aprovados está vazia, THE Sistema SHALL exibir uma mensagem indicando que não há colaboradores listados.
5. THE Sistema SHALL excluir da listagem pública todos os registros com `status = 'pending'` ou `status = 'rejected'`.

---

### Requisito 6 — Validação de Permissões e Redirecionamentos

**User Story:** Como desenvolvedor e usuário da plataforma, quero que tentativas de acesso a páginas ou ações não autorizadas resultem em redirecionamentos claros e mensagens de feedback, para que a segurança e a experiência do usuário sejam mantidas.

#### Acceptance Criteria

1. WHEN um Pessoa autenticado tenta acessar uma página administrativa de hotel (ex.: gerenciar vagas, gerenciar colaboradores), THE Sistema SHALL retornar HTTP 403 no backend e redirecionar para `/feed` no frontend com mensagem informativa.
2. WHEN um Hotel autenticado tenta acessar páginas de perfil pessoal de Pessoa ou funcionalidades restritas a Pessoas, THE Sistema SHALL retornar HTTP 403 no backend e redirecionar para o dashboard do hotel no frontend.
3. THE Sistema SHALL verificar permissões via Policies Laravel em todas as ações de escrita administrativas do hotel.
4. WHEN um usuário não autenticado tenta acessar qualquer rota protegida, THE Sistema SHALL redirecionar para a tela de login correspondente ao tipo de rota.
5. THE Sistema SHALL exibir mensagem de feedback clara ao usuário sempre que um redirecionamento por falta de permissão ocorrer.

---

### Requisito 7 — Melhorias Gerais de UX

**User Story:** Como usuário da plataforma, quero que ações destrutivas exijam confirmação, que estados de carregamento sejam visíveis, que listas vazias exibam mensagens adequadas, e que feedbacks de sucesso e erro sejam apresentados, para que a interface seja previsível e confiável.

#### Acceptance Criteria

1. WHEN o usuário aciona qualquer ação destrutiva (excluir vaga, excluir publicação, remover colaborador), THE Sistema SHALL exibir um modal ou diálogo de confirmação antes de executar a operação.
2. WHEN uma requisição de rede está em andamento, THE Sistema SHALL exibir um indicador de carregamento (loader ou skeleton) na área correspondente da interface.
3. WHEN uma lista de dados está vazia (vagas, colaboradores, publicações, notificações), THE Sistema SHALL exibir uma mensagem de estado vazio contextualizada para o conteúdo ausente.
4. WHEN uma operação de escrita é concluída com sucesso, THE Sistema SHALL exibir uma notificação de sucesso (toast) ao usuário.
5. WHEN uma operação de escrita falha por erro de validação ou servidor, THE Sistema SHALL exibir uma notificação de erro com descrição do problema.
6. THE Sistema SHALL garantir que a navegação após ações (criação, edição, exclusão) redirecione o usuário para a tela contextualmente correta.
7. WHEN o usuário cancela um modal de confirmação, THE Sistema SHALL fechar o modal sem executar a operação e sem alterar o estado da aplicação.
