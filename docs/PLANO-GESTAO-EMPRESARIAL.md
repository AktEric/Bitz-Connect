# Plano de ação — HotelConnect como plataforma de gestão empresarial

## Visão
O app tem hoje duas camadas: (1) a **rede social profissional** pública (feed, vagas, cursos, mentorias,
perfis) e (2) o **Workspace** — a intranet privada de cada empresa, onde só entra quem tem vínculo aprovado.
O Workspace é o "terceiro link" do produto: a empresa convida seus funcionários e passa a ter uma rede
social interna com chat, grupos, feed de comunicados, solicitações ao RH, recebimentos, metas e PDI.

## Decisões de arquitetura
- **Empresa = `Hotel`** (sem renomear tabelas). Vínculo/papel em `hotel_users` (`admin` | `employee`),
  agora com `job_title`, `hired_at`, `manager_id`, `department_id`.
- **Gestor** = quem é `manager_id` de alguém; enxerga solicitações, metas e PDI da própria equipe.
  **Administrador da empresa** = RH/gestão: enxerga tudo da empresa.
- Tudo do Workspace é isolado por `hotel_id` e exige vínculo **aprovado** (checado em cada rota).
- Arquivos sensíveis (holerites) em disco **privado**, entregues por rota autenticada — nunca por URL pública.
- Migrations só aditivas. Padrão do código mantido (Controllers `Api/V1`, `ApiResponseTrait`, React Query).
- Tempo real por polling por enquanto; WebSocket (Reverb) é item da Fase 5.

## Fases
| # | Fase | Conteúdo | Estado |
|---|------|----------|--------|
| 0 | Revisão de produção | Falhas de segurança/LGPD/escala (ver `REVISAO-PRODUCAO.md`) | correções críticas feitas |
| 1 | Chat corporativo | Grupos, modo aviso, setores, barra lateral | feito |
| 2 | Base do Workspace | `/me/workspaces`, área `/w/:slug`, menu, guard | feito (backend testado via API; telas verificadas) |
| 3 | Pessoas | Convites por link, perfil interno (cargo/gestor/admissão), diretório da equipe | feito (backend testado via API; telas verificadas) |
| 4 | Comunicação | Feed interno de comunicados com comentários e curtidas | feito (backend testado via API; telas verificadas) |
| 5 | Solicitações | Férias, documentos, reembolso, outros — fluxo de aprovação | feito (backend testado via API; telas verificadas) |
| 6 | Recebimentos | Holerites/comprovantes anexados pelo RH, privados por funcionário | feito (backend testado via API; telas verificadas) |
| 7 | Metas | Metas individuais com progresso; painel do gestor | feito (backend testado via API; telas verificadas) |
| 8 | Desenvolvimento | PDI: objetivos, ações, prazos, anotações | feito (backend testado via API; telas verificadas) |
| 9 | Escala e governança | WebSocket, papéis granulares (RH/Financeiro), auditoria, SSO, exportação LGPD, testes E2E, observabilidade | planejado |

## Regras de acesso (resumo)
- Membro aprovado: vê o Workspace da própria empresa, diretório, feed, seus recebimentos/solicitações/metas/PDI.
- Gestor: + solicitações, metas e PDI da sua equipe direta.
- Administrador da empresa: + tudo da empresa, convites, setores, recebimentos de todos, publicar comunicados.

## Critérios para "pronto para empresa grande"
Rate limit e tokens com expiração; e-mail/PII não vazam em rotas públicas; paginação nas listas;
índices nas tabelas novas; logs de auditoria para ações de RH; testes automatizados dos fluxos críticos;
backup do Postgres; HTTPS e headers de segurança em produção.

## Estado ao fim da rodada noturna (06/11/2026)
Entregue e verificado: fases 1–8 (backend + telas). Verificação: sintaxe PHP (`php -l`), TypeScript (`tsc --noEmit`), migrações aplicadas e ~40 chamadas reais à API com perfis diferentes (RH, gestor, colaborador, estranho), upload/download privado de holerite e telas renderizadas no navegador com dados de demonstração.

**Testes automatizados:** 7 testes Pest novos do Workspace (`tests/Feature/Workspace`) + suíte completa executada: 114 passaram. Isso revelou e corrigiu um bug meu: o login/cadastro tinha parado de devolver o e-mail do próprio usuário (efeito da correção de privacidade).

**Não verificado:** fluxo de convite com usuário novo (cadastro → aceitar) só testado na API/tela de preview; layout em telas largas (verificado em largura de celular).

**Ambiente local:** o Vite do Docker não enxerga edições em arquivos já existentes (OneDrive/Docker). Reiniciar o container `hotelconnect_node` resolve. Enquanto isso, `http://localhost:9599/dev-verify/index.html?path=/w/inovacao` monta as rotas atuais (harness só de dev, ignorado pelo git).

**Dados de demonstração:** usuários `*@inovacao.local` em `backend/storage/app/dev-seed-credentials.txt` (ignorado pelo git). Para rodar migrações/seeders automaticamente em dev, use `DEV_AUTO_BOOTSTRAP=true` (desligado agora). **Nunca em produção.**

## Login do funcionário (06/11/2026)
- Tela `/entrar` (alias `/login/funcionario`): e-mail e senha, **sem CNPJ**; após entrar vai direto ao Workspace (ou ao link de convite aberto, via `?redirect=`).
- Google: `POST /auth/google` valida o ID token no Google (client id, e-mail verificado, validade), cria a conta se for novo e entra se já existir. **Só aparece o botão quando `GOOGLE_CLIENT_ID` estiver no `backend/.env`.** 4 testes Pest cobrem (com o Google simulado).
- Não verificado: o clique real no botão do Google (precisa de um client ID real).
