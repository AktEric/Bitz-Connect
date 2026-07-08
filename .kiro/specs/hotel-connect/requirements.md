# Requirements Document — HotelConnect MVP

## Introduction

HotelConnect é uma rede social profissional exclusiva para o setor hoteleiro, inspirada no LinkedIn.
A plataforma possui dois tipos de perfil: **Hotel** (empresa) e **Candidato** (profissional).
Hotéis podem publicar vagas, novidades e interagir com outros hotéis.
Candidatos podem buscar vagas, seguir hotéis e gerenciar seu perfil profissional.
O MVP é desenvolvido em PT-BR com foco em escalabilidade futura (IA, CRM, automações).

---

## Glossary

- **User**: Pessoa física cadastrada na plataforma (candidato ou admin de hotel).
- **Hotel**: Perfil empresarial criado e gerenciado por um User.
- **HotelAdmin**: User com papel de administrador de um Hotel específico.
- **PlatformAdmin**: User com acesso administrativo global à plataforma.
- **Post**: Publicação de texto/mídia feita por um Hotel ou User no feed.
- **Job**: Vaga de emprego publicada por um Hotel.
- **Application**: Candidatura de um User a uma Job.
- **Feed**: Listagem cronológica de Posts visíveis ao usuário autenticado.
- **Search**: Mecanismo de busca de Hotéis, Vagas e Usuários.
- **Sanctum**: Pacote de autenticação via token do Laravel utilizado na API.
- **UUID**: Identificador público universal único usado em todas as entidades expostas na API.

---

## Requirements

### Requirement 1: Autenticação e Conta de Usuário

**User Story:** Como visitante, quero me cadastrar e autenticar na plataforma, para acessar os recursos do HotelConnect.

#### Acceptance Criteria

1. WHEN um visitante submete nome, e-mail e senha válidos, THE System SHALL criar uma conta de usuário e retornar um token de autenticação Sanctum.
2. WHEN um visitante submete e-mail já cadastrado, THE System SHALL retornar erro com mensagem informando que o e-mail já está em uso.
3. WHEN um usuário submete e-mail e senha corretos, THE System SHALL retornar token de autenticação e dados básicos do perfil.
4. WHEN um usuário submete credenciais incorretas, THE System SHALL retornar erro de autenticação sem revelar qual campo está errado.
5. WHEN um usuário autenticado solicita logout, THE System SHALL invalidar o token atual.
6. WHEN um usuário submete e-mail cadastrado para recuperação de senha, THE System SHALL enviar e-mail com link de redefinição válido por 60 minutos.
7. IF o link de redefinição de senha estiver expirado, THEN THE System SHALL retornar erro informando expiração e instruindo nova solicitação.
8. WHEN um usuário autenticado altera sua senha com senha atual correta e nova senha válida, THE System SHALL atualizar a senha e invalidar todos os tokens ativos do usuário.

---

### Requirement 2: Perfil do Usuário (Candidato)

**User Story:** Como usuário cadastrado, quero gerenciar meu perfil profissional, para me apresentar ao mercado hoteleiro.

#### Acceptance Criteria

1. THE System SHALL manter para cada usuário: nome completo, e-mail, foto de perfil, banner, biografia, cidade, estado, website pessoal e links de redes sociais.
2. WHEN um usuário autenticado submete dados válidos de perfil, THE System SHALL atualizar e retornar o perfil atualizado.
3. WHEN um usuário faz upload de foto de perfil ou banner, THE System SHALL armazenar a imagem e retornar a URL pública do arquivo.
4. IF o arquivo enviado não for uma imagem válida (jpg, png, webp) ou exceder 5MB, THEN THE System SHALL rejeitar o upload com mensagem descritiva.
5. THE System SHALL permitir que um usuário adicione múltiplas experiências profissionais ao perfil, cada uma com: cargo, hotel/empresa, data início, data fim (opcional), e descrição.
6. WHEN um usuário remove uma experiência profissional, THE System SHALL excluir o registro e retornar o perfil atualizado.

---

### Requirement 3: Perfil do Hotel

**User Story:** Como usuário, quero criar e gerenciar o perfil do meu hotel, para divulgar a empresa na plataforma.

#### Acceptance Criteria

1. WHEN um usuário autenticado submete nome, descrição e localização válidos, THE System SHALL criar um perfil de Hotel e vincular o usuário como HotelAdmin.
2. THE System SHALL manter para cada hotel: nome, slug único, descrição, logo, capa, localização (cidade, estado, país), website, telefone e e-mail de contato.
3. WHEN um HotelAdmin submete dados válidos de edição, THE System SHALL atualizar o perfil do hotel e retornar os dados atualizados.
4. WHEN um HotelAdmin faz upload de logo ou capa, THE System SHALL armazenar a imagem e retornar a URL pública.
5. IF o arquivo enviado não for uma imagem válida ou exceder 5MB, THEN THE System SHALL rejeitar o upload com mensagem descritiva.
6. THE System SHALL garantir que o slug do hotel seja único na plataforma.
7. WHEN um usuário tenta editar um hotel sem ser HotelAdmin, THE System SHALL retornar erro 403 Forbidden.
8. WHERE um usuário é vinculado a múltiplos hotéis como HotelAdmin, THE System SHALL listar todos os hotéis gerenciados por esse usuário.

---

### Requirement 4: Feed de Publicações

**User Story:** Como usuário ou hotel, quero publicar e interagir com conteúdo no feed, para manter a comunidade hoteleira engajada.

#### Acceptance Criteria

1. WHEN um usuário ou HotelAdmin autenticado submete texto (mínimo 1 caractere, máximo 3000 caracteres), THE System SHALL criar uma publicação no feed.
2. THE System SHALL exibir o feed com publicações ordenadas por data de criação decrescente, com paginação cursor-based.
3. WHEN o autor de uma publicação submete conteúdo editado, THE System SHALL atualizar a publicação e registrar que foi editada.
4. WHEN o autor de uma publicação solicita exclusão, THE System SHALL remover a publicação e seus comentários associados.
5. WHEN um usuário autenticado curte uma publicação, THE System SHALL registrar a curtida e retornar o total atualizado.
6. WHEN um usuário autenticado remove a curtida de uma publicação, THE System SHALL remover o registro e retornar o total atualizado.
7. WHEN um usuário autenticado submete comentário válido em uma publicação, THE System SHALL criar o comentário vinculado à publicação.
8. WHEN o autor de um comentário solicita exclusão, THE System SHALL remover o comentário.
9. IF um usuário tenta editar ou excluir publicação/comentário de outro usuário, THEN THE System SHALL retornar erro 403 Forbidden.

---

### Requirement 5: Vagas de Emprego

**User Story:** Como HotelAdmin, quero publicar e gerenciar vagas de emprego, e como candidato quero encontrar e me candidatar a vagas.

#### Acceptance Criteria

1. WHEN um HotelAdmin submete dados válidos de vaga (título, descrição, tipo de contrato, localização), THE System SHALL criar a vaga vinculada ao hotel.
2. THE System SHALL manter para cada vaga: título, descrição, requisitos, benefícios, tipo de contrato, modalidade (presencial/remoto/híbrido), localização, faixa salarial (opcional) e status (ativa/pausada/encerrada).
3. WHEN um HotelAdmin edita uma vaga existente com dados válidos, THE System SHALL atualizar os dados da vaga.
4. WHEN um HotelAdmin encerra ou pausa uma vaga, THE System SHALL atualizar o status da vaga.
5. WHEN um usuário autenticado se candidata a uma vaga ativa, THE System SHALL registrar a candidatura vinculando usuário e vaga.
6. IF um usuário tenta se candidatar a uma vaga já encerrada ou pausada, THEN THE System SHALL retornar erro informando que a vaga não está disponível.
7. IF um usuário tenta se candidatar à mesma vaga mais de uma vez, THEN THE System SHALL retornar erro informando candidatura duplicada.
8. WHEN um HotelAdmin lista candidaturas de uma vaga, THE System SHALL retornar a lista paginada de candidatos com dados básicos de perfil.
9. WHEN um usuário lista suas candidaturas, THE System SHALL retornar todas as vagas para as quais ele se candidatou com status atual.

---

### Requirement 6: Pesquisa

**User Story:** Como usuário, quero pesquisar hotéis, vagas e outros usuários, para encontrar oportunidades e conexões relevantes.

#### Acceptance Criteria

1. WHEN um usuário autenticado submete uma query de busca, THE System SHALL pesquisar em hotéis (nome, descrição, localização) e retornar resultados paginados.
2. WHEN um usuário autenticado submete uma query de busca de vagas, THE System SHALL pesquisar em vagas ativas (título, descrição, localização) e retornar resultados paginados.
3. WHEN um usuário autenticado submete uma query de busca de usuários, THE System SHALL pesquisar por nome e retornar resultados paginados.
4. IF a query de busca for vazia ou contiver apenas espaços, THEN THE System SHALL retornar erro de validação.
5. THE System SHALL retornar resultados de busca com paginação e indicar o total de resultados encontrados.

---

### Requirement 7: Permissões e Políticas de Acesso

**User Story:** Como plataforma, quero garantir que cada ação seja executada apenas por quem tem permissão, para manter a integridade e segurança do sistema.

#### Acceptance Criteria

1. THE System SHALL aplicar três níveis de permissão: User (comum), HotelAdmin (admin de hotel específico) e PlatformAdmin (admin global).
2. WHEN um usuário não autenticado tenta acessar qualquer endpoint protegido, THE System SHALL retornar erro 401 Unauthorized.
3. WHEN um User tenta executar ação exclusiva de HotelAdmin ou PlatformAdmin, THE System SHALL retornar erro 403 Forbidden.
4. WHEN um PlatformAdmin acessa qualquer recurso da plataforma, THE System SHALL conceder acesso irrestrito.
5. THE System SHALL utilizar Laravel Policies para centralizar toda a lógica de autorização.

---

### Requirement 8: Padrão de Resposta da API

**User Story:** Como desenvolvedor frontend, quero que a API retorne respostas em formato consistente, para simplificar o tratamento de dados no cliente.

#### Acceptance Criteria

1. THE API SHALL retornar todas as respostas de sucesso no formato: `{ "success": true, "message": "...", "data": {} }`.
2. THE API SHALL retornar todas as respostas de erro no formato: `{ "success": false, "message": "...", "errors": [] }`.
3. THE API SHALL utilizar códigos HTTP semanticamente corretos (200, 201, 400, 401, 403, 404, 422, 500).
4. THE API SHALL retornar UUIDs como identificadores públicos de todas as entidades nos responses.
5. WHEN ocorre um erro de validação, THE API SHALL retornar status 422 com o campo `errors` listando cada campo inválido e o motivo.

---

### Requirement 9: Infraestrutura Docker

**User Story:** Como desenvolvedor, quero um ambiente dockerizado completo, para garantir consistência entre ambientes de desenvolvimento e produção.

#### Acceptance Criteria

1. THE System SHALL fornecer um `docker-compose.yml` com os containers: nginx, php (8.4), postgresql, redis, node e mailpit.
2. WHEN o ambiente é iniciado com `docker compose up`, THE System SHALL subir todos os serviços sem erros de dependência.
3. THE System SHALL mapear volumes persistentes para postgresql e redis para evitar perda de dados entre restarts.
4. THE System SHALL expor a API na porta 8000 e o frontend na porta 3000 via nginx.
5. THE System SHALL incluir container mailpit para captura de e-mails em desenvolvimento, acessível na porta 8025.
