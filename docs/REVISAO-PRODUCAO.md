# Revisão de produção — achados e status

## Corrigidos nesta rodada
| Severidade | Achado | Correção |
|-----------|--------|----------|
| Alta | `GET /users/{user}` (público) devolvia o **e-mail** de qualquer usuário | `UserResource` só expõe e-mail ao próprio usuário |
| Alta | Login/cadastro/recuperação sem **rate limit** (força bruta) | `throttle:10,1` nas rotas `/auth/*` públicas |
| Média | Recuperação de senha revelava se o e-mail existe (**enumeração**) | Resposta idêntica com ou sem conta |
| Média | Tokens Sanctum **sem expiração** | Expiram em 30 dias |
| Média | `isHotelAdminOf` aceitava admin **pendente** | Exige `status = approved` |
| Baixa | Pastas `bootstrap/cache` e `storage/framework` ausentes quebravam a API (500) | Criadas |

## Abertos (priorizados)
| Severidade | Achado | Recomendação |
|-----------|--------|--------------|
| Alta | `/hotels/{hotel}/employees` e `/admins` são **públicos**: lista de funcionários exposta (LGPD) | Tornar opt-in por empresa ou exigir login; decisão de produto |
| Alta | Sem **auditoria** de ações de RH (quem viu/baixou holerite, quem aprovou) | Tabela de auditoria (Fase 9) |
| Média | Chat 1:1: `conversations()` carrega **todas** as mensagens em memória | Consulta agregada/paginada |
| Média | Chat por **polling** a cada 5 s: não escala para milhares de usuários simultâneos | WebSocket (Laravel Reverb) |
| Média | Tokens em `localStorage` (exposição a XSS) | Cookie httpOnly (Sanctum SPA) + CSP |
| Média | Sem 2FA / SSO | SSO (Google/Microsoft) para empresas grandes |
| Baixa | `User.role` é `fillable` | Remover de `$fillable` e atribuir explicitamente |
| Baixa | `formatGroup` faz consultas por grupo (N+1) | Agregar contagens em uma consulta |
| Baixa | Testes do Workspace cobrem acesso e holerite, mas não convites nem PDI/feed em detalhe | Ampliar a cobertura |
| Info | `APP_DEBUG=true` no `.env.example` | Garantir `false` em produção (já há `.env.production.example`) |

## Notas sobre o Workspace (rodada 06/11/2026)
- Holerites ficam em disco **privado** (`storage/app/private`), nunca em URL pública; download passa por autenticação, só o dono ou o RH, com `nosniff` e `no-store`. Tipos aceitos: PDF/JPG/PNG até 10 MB.
- Acesso por **vínculo aprovado**; RH vê tudo da empresa; gestor vê apenas a equipe direta; colaborador vê só o próprio.
- Abertos específicos: (1) notificações de publicação no mural não são enviadas em massa (evita fan-out) — usar fila ao escalar; (2) holerite sem antivírus/varredura de arquivo; (3) sem trilha de auditoria de downloads além de `first_viewed_at`; (4) convite aberto (sem e-mail) pode ser repassado — use convite com e-mail para controle; (5) `uses` do convite pode vir `null` até o primeiro uso (cosmético).
