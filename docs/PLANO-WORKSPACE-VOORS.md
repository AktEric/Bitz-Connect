# Plano — Workspace no nível do Voors (Viasoft)

Objetivo: aproximar o Workspace do Bitz Connect do layout/fluxos do Voors, com permissões por hierarquia
aplicadas de ponta a ponta e perfis de pessoas navegáveis.

## Diagnóstico (o que já existe)

| Área | Estado atual |
|---|---|
| Layout | Rail de ícones, busca central, atalhos, fileira de pessoas à direita, Início com mural + destaques |
| Reações | 4 tipos (like, love, celebrate, clap); sem lista de quem reagiu |
| Vagas | Vagas internas + candidatura/retirada já existem (`WorkspaceJobController`); falta histórico de carreira |
| Perfil | Perfil social, ficha cadastral (RH), aba "Equipe e conexões" montada no cliente |
| Permissões | `WorkspaceAccess` no backend (owner/hr/finance/director/manager/employee + árvore hierárquica). Frontend decide visibilidade com `is_admin`/`is_manager` espalhados e cada página reimplementa a regra |
| Pessoas clicáveis | Só em alguns pontos (equipe/organograma); autor de post, comentário e curtidas não levam ao perfil |

## Arquitetura

### 1. Permissões (fonte única da verdade)
- **Backend**: `WorkspacePermissions` (`app/Domain/Workspace/Services`) traduz papel de acesso + hierarquia em uma lista de
  *capabilities* (`jobs.manage`, `people.manage`, `career.manage`, `feed.moderate`, `roles.manage`, `audit.view`,
  `reports.view`, `dashboard.view`, `payslips.manage`, `time.team`, `time.settings`, `calendar.manage`, `climate.manage`...).
  É exposta em `GET /workspaces/{slug}` → `me.permissions`. Os controllers continuam sendo a barreira real (a UI só esconde).
- **Frontend**: `useCan(capability)` + `<Can>` + `<RequireCan>` (guard de rota). Menu, botões e rotas leem **só** isso.
  Regra: se a pessoa não pode agir na tela, a opção não aparece; se não pode ver a tela, a rota redireciona.

### 2. Reações
- 8 reações: curtir, amei, parabéns, palmas, **risada, triste, pensando, surpreso** (`REACTIONS` no backend, `ReactionType` no front).
- `GET /feed/{post}/reactions` → quem reagiu, agrupado por tipo (modal com abas). Comentários já têm lista; o modal de
  "Comentários" lista autores com link para o perfil.

### 3. Histórico de carreira
- Tabela `career_entries` (hotel_id, user_id, title, department_id?, started_on, ended_on?, kind, note).
- Leitura: qualquer membro vê a trajetória interna de colegas (cargo/período). Escrita: RH/dono (`career.manage`).
- Automação: ao marcar candidato como **contratado** numa vaga interna, encerra o cargo atual e abre o novo.
- Seed do Eric: 2010–2014 Suporte Técnico · 2014–2016 Líder de Suporte · 2016–2022 Gestor de Consultoria · 2022–atual Diretor.

### 4. Perfis navegáveis
- Componente único `PersonLink` (nome/avatar → `/w/:slug/pessoas/:uuid`) usado em posts, comentários, reações, menções,
  ausências, novos colaboradores, candidatos, organograma.
- `GET /people/{uuid}/org` → cadeia de líderes (até o topo), gestor, equipe direta, setor (chefe + colegas).
- Perfil ganha abas **Carreira** e **Equipe e liderança** (server-side, com links).

### 5. Vagas (autoatendimento)
- Candidatura interna já existe; adicionar "Minhas candidaturas" e o cartão do Início aponta direto para a vaga.

### 6. Layout (aproximação do Voors)
- Início em 3 colunas: **esquerda** (meu cartão + atalhos pessoais/gestão conforme permissão), **centro** (mural),
  **direita** (ausências, novos colaboradores, vagas). Cartões brancos com sombra suave sobre fundo azul-claro.

## Ordem de execução
1. Plano (este arquivo)
2. Backend: permissões → reações → carreira → org → testes (Pest)
3. Frontend: tipos/serviço → permissões → reações + modal → PersonLink → perfil (carreira/equipe) → Início em 3 colunas
4. Seed da carreira do Eric, build, testes, verificação
