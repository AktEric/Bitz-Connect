# Levantamento de melhorias — segurança, arquitetura, produto e design

> Objetivo: deixar o produto pronto para venda corporativa. Data do levantamento: 07/11/2026.
> Base: leitura do código e da configuração do repositório (branch `feature/workspace-rh-ponto`), da stack Docker de
> produção e dos testes. **Não** foi feito pentest, teste de carga nem parecer jurídico — onde há tema legal,
> está marcado "validar com jurídico/contador".

---

## 1. Resumo executivo

**Onde estamos:** o Workspace (intranet de RH) já tem o que uma demonstração precisa: papéis de acesso que restringem de
verdade, ponto com escalas, banco de horas, fechamento mensal, localização/foto, auditoria básica e 148 testes passando.

**O que impede de chamar de "pronto para empresa" hoje** (os itens que um comprador técnico ou o jurídico dele vão achar em minutos):

| # | Bloqueio | Por que importa |
|---|----------|-----------------|
| 1 | Produção sem **fila (worker) nem agendador**, com `mailpit` no lugar de e-mail real | Convite, recuperação de senha e notificações podem simplesmente não chegar |
| 2 | **Sem backup** do Postgres/arquivos | Um erro de disco e o cliente perde holerites e histórico de ponto |
| 3 | **Sem cabeçalhos de segurança** (CSP, HSTS, X-Frame-Options…) e CORS aberto | Item padrão de questionário de segurança de qualquer empresa média/grande |
| 4 | Token de login em `localStorage`, **sem 2FA**, senha só `min:8` | Contas de RH/CEO/financeiro são alvos de alto valor |
| 5 | Mural do Workspace é **só texto** (sem fotos/vídeos), **sem aniversários nem calendário** | É o que faz o colaborador abrir o app todo dia |
| 6 | Gestor = só quem tem subordinados diretos; **não existe "gestor de área"** nem papéis granulares | Pedido explícito: gestor por área, CEO, financeiro, com limites |
| 7 | Ponto grava IP, mas **não identifica o dispositivo** (e o "nome do computador" não é obtível pelo navegador — ver §5) | Pedido explícito; precisa de uma alternativa honesta e melhor |
| 8 | Fotos e vídeos em disco **público** e sem tratamento (EXIF com GPS, sem antivírus, sem transcodificação) | Vazamento de localização e risco de malware em arquivos |

**Veredito honesto:** em 2 dias dá para fechar os itens 1–4 e 8 (infra e segurança), entregar uma versão boa de 5 (fotos no
mural, aniversários, calendário) e o núcleo de 6 e 7. **Não** dá para entregar tudo deste documento em 2 dias, nem convém
prometer ao cliente conformidade legal do ponto (§5.4) sem validação. O plano da §12 separa o que é "antes da venda" do que é roadmap.

---

## 1.1 Status — Dia 1 executado (07/11/2026)

| Item | Status | O que foi feito / como foi verificado |
|------|--------|---------------------------------------|
| S1/A1 fila, agendador, e-mail | **Feito** | `queue` e `scheduler` no `docker-compose.prod.yml`; mailpit removido do prod; exemplos de SMTP real. Compose validado (`config -q`). **Falta você:** contratar o SMTP e preencher `backend/.env`; configurar SPF/DKIM/DMARC |
| S2/A2 backup e restauração | **Feito e testado** | Serviço `backup` (diário, criptografia AES-256, retenção); `verify-restore.sh` executado de verdade: 53 tabelas, 8 usuários, 62 migrações, saída OK. O teste achou e corrigiu um defeito real (índices de busca não voltavam ao restaurar — migração 000062). **Falta você:** sincronizar `./backups` para fora do servidor |
| A3 saúde | **Feito** | `/up` exposto pelo Caddy; `deploy.sh` verifica `/up` e reinicia fila/agendador |
| S3 cabeçalhos | **Feito e testado** | HSTS, CSP, nosniff, frame-ancestors, Referrer/Permissions-Policy no Caddy (validado com `caddy validate`) e no nginx local (navegando Workspace/Ponto/Mural/Configurações: 0 violações de CSP) |
| S4 CORS | **Feito e testado** | `config/cors.php`: só `APP_URL`/`CORS_ALLOWED_ORIGINS` (+ localhost fora de produção); origem estranha não recebe `Allow-Origin` |
| S6 `role` | **Feito e testado** | Fora do `$fillable`; seeder da conta de plataforma atribui explicitamente |
| S7 limite da API | **Feito** | `throttleApi` com 300/min por usuário (ou IP) |
| S16 IP real | **Feito** | `TRUSTED_PROXIES` configurável (atrás de Cloudflare/LB) |
| S17 seeders | **Feito e testado** | Seeders de demonstração abortam em produção; **o seeder admin tinha `admin@admin.com`/`admin`** — em produção agora exige `ADMIN_EMAIL`/`ADMIN_PASSWORD` (12+ caracteres) |
| S5 mídia | **Parcial** | EXIF/GPS/comentários removidos de todos os uploads de imagem (JPEG preserva só a orientação; PNG/WebP limpos), com testes. **Falta (Dia 2):** mídia do Workspace em disco privado e antivírus |
| S15 CI | **Feito** | GitHub Actions (Pest, tipos, testes e build do front, Caddy/compose/shellcheck) + `composer/npm audit` + Dependabot. Ainda não rodou no GitHub (só roda após o push do workflow) |

Verificação: 154 testes de backend passando, 9 de front, type-check e shellcheck limpos.

### Dia 2 — Gestor por área (feito)

| Item | Status | O que foi feito / como foi verificado |
|------|--------|---------------------------------------|
| Chefe de área | **Feito e testado** | Cada setor pode ter um chefe, que enxerga e aprova solicitações, ponto, metas e PDI de todo o setor |
| Hierarquia | **Feito e testado** | Gestor de gestores enxerga toda a árvore abaixo (até 12 níveis, sem laço); quem está fora da árvore/área continua barrado |
| Substituição | **Feito e testado** | Substituto ganha o alcance do gestor só no período (início/fim por data); fora do período perde o acesso |
| Roteamento de pedidos | **Feito e testado** | Pedido de quem não tem gestor vai para o chefe da área; sem chefe, para dono/RH; substitutos também são avisados |
| Tela | **Feito** | Seção "Áreas e chefias" em Papéis e estrutura (chefe por área e substituições) |
| Auditoria | **Feito** | Troca de chefe e criação/remoção de substituição ficam no log |

### Dia 2 — Mural com fotos, reações e menções (feito)

| Item | Status | O que foi feito / como foi verificado |
|------|--------|---------------------------------------|
| M1 fotos | **Feito e testado** | Até 6 fotos por publicação; redimensionadas no aparelho (1600 px, JPEG) e limpas de EXIF/GPS no servidor; **disco privado**, entregues por **URL assinada de 30 min** (sem login, mas só com assinatura válida; adulterar = 403); apagar a publicação apaga os arquivos e invalida o link. Verificado no navegador: 2 fotos publicadas, carregadas e removidas, 0 violações de CSP |
| M3 reações | **Feito e testado** | 👍 ❤️ 🎉 👏 (uma por pessoa), contagem por tipo, aviso ao autor só na primeira reação |
| M3 menções | **Feito e testado** | `@` abre sugestão de pessoas (teclado e mouse); menção em publicação e comentário avisa a pessoa; nome exibido vem do cadastro (não do texto); gente de fora da empresa ou de outro setor não é notificada |
| Permissão | Mantida | Publicar continua para RH/gestores (colaborador reage e comenta). Liberar publicação para todos é uma decisão de produto |

### Dia 2 — Aniversários e calendário (feito)

| Item | Status | O que foi feito / como foi verificado |
|------|--------|---------------------------------------|
| C1 aniversários | **Feito e testado** | Data de nascimento por pessoa; **o ano é privado** (só a pessoa e o RH); dia/mês aparecem se ela permitir (opt-out); 29/02 vira 28/02 em ano não bissexto |
| Tempo de casa | **Feito e testado** | A partir de `hired_at`, a partir de 1 ano ("5 anos de empresa") |
| C3 calendário | **Feito e verificado** | Grade mensal com filtros, lista do dia, eventos (dia inteiro ou com horário, local, público por setor) e feriados da empresa; **feriados nacionais calculados** (inclui Carnaval, Sexta-feira Santa e Corpus Christi); **férias aprovadas** visíveis só para a pessoa, seu gestor/chefe e o RH. Verificado no navegador: criar evento, confirmar presença, remover |
| RSVP | **Feito e testado** | Vou / Talvez / Não vou, com contagem; só quem vê o evento confirma |
| C4 ICS | **Feito e testado** | Link pessoal de assinatura (Google/Outlook/Apple), protegido por segredo, com renovação que invalida o anterior; testado por requisição, **não** testado importando num cliente real |
| Home | **Feito e verificado** | Card com aniversariantes, tempo de casa e próximos eventos; convite para a pessoa informar o aniversário |

### Dia 2 — Dispositivo, IP e rede no ponto (feito)

| Item | Status | O que foi feito / como foi verificado |
|------|--------|---------------------------------------|
| §5.2 a — aparelho | **Feito e testado** | Toda marcação guarda IP, navegador, sistema e tipo (ex.: "Chrome 130 · Windows 10/11 · desktop"), mesmo com o recurso desligado |
| §5.2 b — dispositivo cadastrado | **Feito, testado e verificado no navegador** | Três modos: só registrar / cadastrar automaticamente / **exigir aprovação do RH**. No modo exigido, o aparelho novo pede um nome ("Recepção — PC 01"), o RH é avisado, aprova/bloqueia/renomeia/remove na tela, e só então marca. Computador compartilhado serve a várias pessoas. A chave do aparelho fica no navegador e o servidor guarda só o hash |
| §5.2 c — redes autorizadas | **Feito e testado** | Lista de IPs/faixas (IPv4 e IPv6) com nome ("Matriz — Wi-Fi"); sinaliza "fora da rede" ou bloqueia; botão "usar meu IP atual" |
| Espelho | **Feito e verificado** | Ícone de detalhes em cada marcação (dispositivo, navegador, IP, rede, local) e selos "fora da rede" e "aparelho novo"; visível só para a pessoa, o gestor e o RH |

**Limite honesto:** o navegador não informa o *nome do computador* (hostname). O nome exibido é o que o RH/colaborador deu ao dispositivo cadastrado. Se limparem os dados do navegador, o aparelho precisa ser recadastrado. Passkey/biometria, quiosque e agente instalado seguem como P1/P2 da §5.2.

### Segurança da conta (feito — P1 S8/S9/S10)

| Item | Status | O que foi feito / como foi verificado |
|------|--------|---------------------------------------|
| S10 senha | **Feito e testado** | 10+ caracteres com maiúscula, minúscula e número; em produção também recusa senhas vazadas (HIBP, k-anonimato) |
| **Mensagens de erro** | **Corrigido** | Os formulários mostravam chaves cruas ("validation.email"); agora há tradução completa em `lang/pt_BR/validation.php` |
| S9 2FA | **Feito, testado e verificado no navegador** | TOTP (RFC 6238, vetores oficiais testados), QR code, 10 códigos de recuperação de uso único (só hash guardado), anti-replay do código, limite de tentativas por conta, segredo criptografado. Pede o código no login de todas as telas (pessoa, funcionário e empresa) por uma caixa global |
| 2FA obrigatório | **Feito e testado** | O dono pode exigir 2FA de dono/RH/financeiro; quem não ativou só acessa o aviso e a tela de Segurança |
| S8 sessões | **Parcial** | Tela "Onde você está conectado" (aparelho, IP, último uso), encerrar uma ou todas as outras; trocar a senha encerra tudo. **Falta:** mover o token do `localStorage` para cookie httpOnly |
| **APP_KEY** | **Corrigido** | O `.env` de desenvolvimento não tinha APP_KEY (quebraria qualquer segredo criptografado); gerada, e o `deploy.sh` agora se recusa a subir sem ela |

Pendente: aviso/publicação automática de parabéns (precisa de opt-in da empresa), feriados estaduais/municipais por localidade (hoje o RH cadastra como "feriado da empresa"), feriados alimentando banco de horas e fechamento (C5), cobertura/conflito de férias (C6) e edição de evento pela interface (a API já permite).

Pendente do M1/S5: antivírus (ClamAV) nas fotos, miniaturas geradas no servidor (hoje só o redimensionamento no aparelho) e vídeo (M2).

Não coberto ainda: papéis personalizados com matriz de permissões (P1 da §8.3), escopo por unidade/filial e a tela de "minhas substituições" para o próprio gestor (a API já permite).

---

## 2. Como ler as prioridades

- **P0** = antes da venda (2 dias). **P1** = primeiros 30 dias. **P2** = 60–90 dias.
- Esforço: **S** (horas), **M** (1 dia), **L** (2+ dias).
- "Evidência" = onde foi observado no código/config.

---

## 3. Segurança

### 3.1 Achados (ordenados por risco)

| ID | Pri | Esf | Achado | Evidência | Correção recomendada |
|----|-----|-----|--------|-----------|----------------------|
| S1 | P0 | M | Produção **sem worker de fila e sem scheduler**; `mailpit` sobe junto e o `.env.production.example` aponta `QUEUE_CONNECTION=redis` | `docker-compose.prod.yml` (serviços: caddy, php, postgres, redis, mailpit) | Adicionar serviços `queue` (`php artisan queue:work --tries=3`) e `scheduler` (`schedule:work`); trocar mailpit por SMTP real (SES/Postmark/Mailgun) com SPF/DKIM/DMARC; remover mailpit do prod |
| S2 | P0 | M | **Sem backup** (Postgres, `storage/`) e sem teste de restauração | nenhum job/script em `docker/`, `scripts/` | `pg_dump` diário criptografado + cópia externa (S3/Backblaze), retenção 30 dias, `storage/` em bucket versionado; documentar e **ensaiar** a restauração |
| S3 | P0 | S | **Sem cabeçalhos de segurança** no Caddy/nginx | `docker/caddy/Caddyfile` (só gzip, upload e cache) | `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`/`frame-ancestors 'none'`, `Referrer-Policy`, `Permissions-Policy: geolocation=(self), camera=(self), microphone=()` e **CSP** (script-src 'self'; connect-src 'self'; img-src 'self' data: blob:) |
| S4 | P0 | S | CORS sem arquivo próprio → padrão do Laravel (origem `*` em `api/*`) | ausência de `backend/config/cors.php` | Publicar `config/cors.php` com a(s) origem(ns) reais; `supports_credentials` só se migrar para cookie |
| S5 | P0 | M | Uploads de foto/vídeo **sem normalização**: EXIF (inclui GPS) preservado; sem antivírus; arquivos em `public` (URL adivinhável só pelo hash, mas pública) | `FeedController` (`store('posts','public')`), `VideoController`, `StoryController` | Reencodar imagens (GD/Imagick) removendo EXIF; ClamAV na fila; mídia do Workspace em disco **privado** com URL assinada/rota autenticada (como já é nos holerites); limites por empresa (cota) |
| S6 | P0 | S | `User.role` está em `$fillable` | `backend/app/Models/User.php` linha ~40 | Remover de `$fillable`; atribuir explicitamente (risco de escalada de privilégio por mass-assignment) |
| S7 | P0 | S | Só há rate limit em `/auth/*` (10/min por IP). O restante da API não tem limite | `AppServiceProvider` (`RateLimiter::for('auth')`), `routes/api.php` | `throttle:api` por usuário+IP; limites específicos (ponto, upload, exportação, busca); bloqueio progressivo por conta no login |
| S8 | P1 | M | Sessão: token em `localStorage` (exposto a XSS), 30 dias, sem lista de dispositivos/sessões | `frontend/src/contexts/AuthContext.tsx`, `config/sanctum.php` | Cookie `httpOnly; SameSite=Lax` (Sanctum modo SPA) + CSP; expiração curta com renovação; tela "Meus dispositivos" com encerrar sessão; trocar senha revoga tokens |
| S9 | P1 | L | **Sem 2FA/SSO** | — | TOTP (obrigatório para dono/RH/financeiro) + códigos de recuperação; SSO Google/Microsoft (OIDC) — já existe login Google, falta amarrar a política da empresa |
| S10 | P1 | S | Política de senha fraca (`min:8`) | `Requests/Auth/*` | `Password::min(10)->mixedCase()->numbers()->uncompromised()`; bloquear senhas do próprio e-mail/nome |
| S11 | P1 | M | **Isolamento entre empresas depende de `where hotel_id` em cada consulta** (sem escopo global) | nenhum `GlobalScope`/`ScopedBy` em `app/Models` | Suíte "tenant isolation" que percorre todas as rotas `/workspaces/{slug}/*` com usuário de **outra empresa**; opcional: escopo global por empresa e Row-Level Security no Postgres como defesa em profundidade |
| S12 | P1 | M | Auditoria cobre ações de RH, mas **não** logins/falhas, exportações genéricas, leitura de holerite pelo RH nem mudanças de configuração em geral; sem tela e sem imutabilidade | `AuditLog`, endpoint `/audit` (sem UI) | Registrar login/logout/falha/novo dispositivo; tela de auditoria com filtros e exportação; tabela somente-anexar (revogar `UPDATE/DELETE` do usuário da aplicação) com retenção definida |
| S13 | P1 | M | **Dados sensíveis sem criptografia em repouso** (latitude/longitude, fotos de ponto, holerites) | `time_entries.lat/lng`, `storage/app/private` | `encrypted` cast para coordenadas; disco criptografado no host/volume; backups criptografados |
| S14 | P1 | M | **LGPD operacional ausente**: política de privacidade/termos, registro de consentimento da foto/localização, retenção, direitos do titular (exportar/apagar) | — | Aceite no primeiro uso do ponto com foto/GPS; prazo de retenção configurável (ex.: apagar fotos após N meses); rotina de exportação e anonimização; encarregado (DPO) e contrato de operador com o cliente — **validar com jurídico** |
| S15 | P1 | S | Cadeia de suprimentos sem verificação: **sem CI**, sem `composer audit`/`npm audit`/Dependabot | `.github/` inexistente | GitHub Actions: Pest, `tsc`, ESLint, `composer audit`, `npm audit`, build; Dependabot |
| S16 | P1 | S | Atrás de CDN/balanceador o IP real exige **TrustProxies** configurado | `bootstrap/app.php` sem `trustProxies` | Configurar proxies confiáveis (Cloudflare/LB) para `X-Forwarded-For`; sem isso o IP gravado no ponto pode ser o do proxy |
| S17 | P2 | M | Resíduos de desenvolvimento no repositório: `Claude outputs/`, `_to_delete/`, scripts `.bat/.ps1`, seeders com senha conhecida (`Teste@12345`) | raiz do repo, `DevWorkspaceSeeder` | Garantir que seeders de demonstração **nunca** rodem em produção (`APP_ENV` guard); limpar a raiz |
| S18 | P2 | M | Convites: link aberto pode ser repassado | `CompanyInviteController` | Convite preso a e-mail, uso único e validade curta como padrão |

### 3.2 O que já está bom (para constar no questionário de segurança)
Holerites em disco privado com download autenticado e `no-store`; vínculo aprovado em toda rota do Workspace; rate limit e
resposta anti-enumeração na recuperação de senha; tokens com expiração; e-mail não exposto em perfis públicos; auditoria de
ações de RH (novo); lista de funcionários só para logados (novo); fechamento de ponto com travas e auditoria (novo);
foto de ponto privada com auditoria de acesso de terceiros (novo); sem `dangerouslySetInnerHTML` no front.

---

## 4. Arquitetura e operação

| ID | Pri | Esf | Tema | Situação | Recomendação |
|----|-----|-----|------|----------|--------------|
| A1 | P0 | M | Filas, agendador, e-mail real | ver S1 | — |
| A2 | P0 | M | Backup/restauração | ver S2 | — |
| A3 | P0 | S | Health check e monitoramento | sem endpoint de saúde nem alertas | `/up` (Laravel) + monitor externo (UptimeRobot/BetterStack); alerta de disco/Postgres |
| A4 | P1 | M | Observabilidade | logs em arquivo, sem rastreio de erro | Sentry (front e back), logs estruturados JSON, métricas básicas (tempo de resposta p95, filas) |
| A5 | P1 | M | CI/CD | inexistente; deploy manual (`deploy.sh`) | Actions: testes → build → deploy por SSH/registry com rollback; ambiente de **staging** separado |
| A6 | P1 | L | Tempo real | chat por **polling** (5 s) | Laravel Reverb (WebSocket) para chat, notificações e "quem está trabalhando agora"; polling só como fallback |
| A7 | P1 | L | Mídia em escala | arquivos no disco local, vídeo 200 MB sem transcodificação | Armazenamento S3-compatível (privado) + CDN; fila de transcodificação (ffmpeg → HLS/MP4 720p + pôster); miniaturas de imagem; cotas |
| A8 | P1 | M | Consultas e índices | chat 1:1 carrega todas as mensagens; N+1 em grupos (já listado em `REVISAO-PRODUCAO.md`); bancos de horas/fechamento fazem 1 consulta por pessoa | Agregar em SQL; índices compostos (`hotel_id,user_id,punched_at` já existe); paginação em todas as listas; cache de contadores |
| A9 | P1 | M | Multi-tenant "de vitrine" | rota por `slug` | Subdomínio por empresa (`empresa.seuapp.com`), **marca própria** (logo/cor), domínio personalizado como upsell |
| A10 | P1 | M | Qualidade de testes | 148 testes de backend; front com 3 arquivos de teste | Playwright com 5–6 fluxos críticos (login → bater ponto → pedir férias → aprovar → fechar mês); contrato de API (OpenAPI) |
| A11 | P2 | M | Ambiente de dev | bind mount no OneDrive/Windows deixa PHP/Vite lentos | Repo em disco local ou WSL2; cache de dependências |
| A12 | P2 | M | Nomenclatura | `Hotel`/`hotel_users` no código e em e-mails/painel antigo | Camada de rótulos (`empresa`) nas respostas/e-mails; migrar internamente só se compensar |
| A13 | P2 | M | Performance de PHP | opcache ajustado (dev); prod usa `Dockerfile.prod` | Conferir opcache/JIT em prod, `route:cache`/`config:cache` (já no `deploy.sh`), considerar Octane/FrankenPHP |

---

## 5. Ponto profissional: local, IP, dispositivo e conformidade

### 5.1 O que já existe
Marcação com hora do **servidor**, IP, latitude/longitude/precisão, foto privada, cerca geográfica (sinaliza ou bloqueia), jornada por
pessoa, banco de horas, ajustes com aprovação, fechamento mensal com CSV e travas, auditoria.

### 5.2 "IP com nome do computador" — o que é e o que não é possível
O navegador **não consegue** ler o nome (hostname) do computador por segurança; nenhum site consegue. Alternativas reais, da mais simples à mais forte:

| Opção | Pri | Esf | O que entrega | Limite |
|-------|-----|-----|---------------|--------|
| **a. Navegador/SO/aparelho** a partir do User-Agent e *Client Hints* (ex.: "Chrome 130 · Windows 11 · desktop") | P0 | S | Grava e mostra em cada marcação | Não identifica *qual* computador |
| **b. Dispositivo cadastrado** — no primeiro uso gera um `device_id` (cookie/IndexedDB), a pessoa dá um nome ("Recepção — PC 01") e o RH aprova; toda marcação mostra o nome | **P0/P1** | M | É o "nome do computador" que importa para o negócio; permite bloquear marcação de aparelho não aprovado | Se limparem os dados do navegador, precisa recadastrar |
| **c. Redes autorizadas** — IPs fixos do escritório recebem um rótulo ("Matriz — Wi-Fi") e marcações fora deles são sinalizadas | P1 | S | Reforça o local sem depender de GPS | Exige IP fixo; IPv6/CGNAT exigem cuidado |
| **d. Passkey/WebAuthn** (biometria do aparelho) para confirmar a marcação | P1 | L | Prova de presença + aparelho, resiste a "bater ponto pelo colega" | Requer aparelho compatível |
| **e. Modo quiosque** (tablet fixo, cadastrado, com QR/PIN/foto) | P2 | L | Padrão em recepção, cozinha, obra | — |
| **f. Agente instalado** (serviço Windows) que informa o hostname real | P2 | L | Hostname verdadeiro | Instalação e suporte por máquina; só se o cliente exigir |

**Recomendação para 2 dias:** (a) + (b) + exibir IP/aparelho/rede no espelho e no relatório. O texto de venda deve dizer
"identificação do dispositivo cadastrado", não "nome do computador".

### 5.3 Antifraude e qualidade da marcação
- Marcação "fora do local" e "aparelho novo" geram alerta para o RH (resumo diário).
- Detectar "viagem impossível" (duas marcações distantes em pouco tempo), mesma foto/aparelho para pessoas diferentes, precisão de GPS ruim.
- Prova de vida simples na foto (piscar/virar) e comparação com a foto de perfil (opcional, tratar como dado biométrico → LGPD).
- Marcação **offline** (fila local) com carimbo do servidor ao sincronizar, marcada como tal.
- **Múltiplos locais** (unidades/obras) e exceções (home office, externo) por pessoa/área.
- Comprovante ao colaborador (PDF/e-mail) com número sequencial e hash.

### 5.4 Conformidade (validar com jurídico/contador antes de prometer)
O registro eletrônico de ponto por programa no Brasil é regulado por portaria do MTE (Portaria 671/2021 e correlatas) e há
regras de CLT/convenção coletiva. Pontos que costumam ser exigidos e que o produto **ainda não cobre**:
arquivos fiscais/legais (AFD/AEJ), imutabilidade e numeração sequencial das marcações, comprovante ao trabalhador,
tolerância de atraso, intervalo mínimo e interjornada, horas extras 50%/100%, adicional noturno, DSR, feriados e abonos
(atestado, falta justificada), banco de horas com prazo de compensação. **Hoje o produto serve como controle gerencial de
jornada, não como sistema legal de ponto** — é assim que deve ser posicionado até haver parecer.

---

## 6. Mural, fotos e vídeos (hoje o Workspace é só texto)

| ID | Pri | Esf | Item |
|----|-----|-----|------|
| M1 | P0 | M | **Fotos no mural do Workspace** (até 6 por publicação, galeria, visualização ampliada), mídia **privada** com URL assinada, EXIF removido, miniaturas |
| M2 | P1 | L | **Vídeo** no mural: upload com limite por empresa, transcodificação em fila, pôster, player com streaming |
| M3 | P0 | S | Reações (👍❤️🎉👏) em vez de só curtir; menções `@pessoa` com notificação |
| M4 | P1 | M | **Público-alvo da publicação**: empresa toda, área, unidade ou grupo (hoje existe `department_id` no modelo, falta a interface) |
| M5 | P1 | M | **Comunicado com "confirmar leitura"** e relatório de quem leu (política, segurança, mudança de escala) |
| M6 | P1 | M | Enquetes, publicação agendada, fixar, destaque do mês |
| M7 | P1 | M | Moderação: denunciar, ocultar, apagar com trilha de auditoria; filtro de palavras (opcional) |
| M8 | P2 | M | Álbuns de eventos e mural de reconhecimento (kudos) com selos |

---

## 7. Aniversários e calendário (hoje não existem)

| ID | Pri | Esf | Item |
|----|-----|-----|------|
| C1 | P0 | M | **Data de nascimento** (dia/mês visíveis por opção; ano privado) e **tempo de casa** a partir de `hired_at`; widget "Aniversariantes da semana/mês" na Home |
| C2 | P0 | S | Publicação automática de parabéns no mural (com opt-out por pessoa); notificação ao time |
| C3 | P0 | L | **Calendário da empresa**: feriados (nacional/estadual/municipal), eventos com confirmação de presença, **férias aprovadas do time**, folgas das escalas, aniversários — filtrável por área, respeitando permissões |
| C4 | P1 | M | **Assinatura ICS** por usuário (Google/Outlook/Apple) e envio de convite por e-mail |
| C5 | P1 | M | Feriados alimentando jornada, banco de horas e fechamento (hoje feriado conta como falta) |
| C6 | P1 | M | Visão de **cobertura**: quem está de férias/folga por área, conflito de férias antes de aprovar |

---

## 8. Gestor por área, CEO e financeiro (controle de acesso)

### 8.1 Situação
Papéis atuais: dono, RH, financeiro, gestor (apenas quem tem subordinados diretos) e colaborador. RH/dono enxergam a empresa toda;
gestor vê a equipe direta; financeiro lida com holerites.

### 8.2 Lacunas
- **Sem gestor de área/diretor**: quem chefia um setor inteiro (e sub-setores) não vê a área, só os subordinados diretos.
- Sem **hierarquia recursiva** (diretor vê gerentes e equipes abaixo); sem **substituto** durante férias (aprovações travam).
- Sem **papel diretoria/CEO somente leitura** com painéis agregados, sem ver dados pessoais sensíveis.
- Sem **escopo por unidade/filial** e sem **RH por área** (HRBP).
- Permissões são "pacotes" fixos; clientes vão pedir "financeiro vê holerite mas não vê ponto", "gestor aprova férias mas não vê metas".

### 8.3 Modelo recomendado
Separar **capacidade** (o que pode fazer) de **escopo** (sobre quem):

| Capacidades (exemplos) | Escopos |
|------------------------|---------|
| `ponto.ver`, `ponto.ajustar`, `ponto.fechar`, `ponto.exportar` | `self` — o próprio |
| `ferias.aprovar`, `solicitacoes.ver` | `reports` — subordinados diretos |
| `holerite.ver`, `holerite.anexar` | `tree` — toda a árvore abaixo (recursivo) |
| `metas.ver`, `pdi.ver` | `area` — um ou mais setores |
| `mural.publicar`, `mural.moderar` | `unit` — uma ou mais unidades |
| `papeis.gerenciar`, `config.ponto`, `auditoria.ver` | `company` — a empresa toda |

Papéis padrão (editáveis): **Dono, Diretoria/CEO (leitura + painéis), RH, RH da área, Financeiro, Gerente de área, Gestor de equipe, Colaborador**.
Implementar em **uma** camada central (Gates/Policies) e cobrir com uma **matriz de testes** (papel × recurso × escopo). Hoje a regra está espalhada em
`WorkspaceAccess` e nos controllers; centralizar reduz o risco de buraco de acesso. **P0:** gestor/diretor de **área** (`area` + `tree`) e substituto; **P1:** papéis personalizados.

### 8.4 Painel do CEO/diretoria (P1, L)
Efetivo e movimentação, absenteísmo, horas extras e banco de horas por área, marcações fora do local/sem marcação, solicitações pendentes e tempo de resposta,
aniversariantes/tempo de casa, engajamento do mural, pesquisa de clima/eNPS. Sempre com *drill-down* limitado pelo escopo do leitor e **sem** dados individuais sensíveis por padrão.

---

## 9. Design e experiência

| ID | Pri | Esf | Item |
|----|-----|-----|------|
| D1 | P0 | M | **Assistente de implantação** (onboarding da empresa): modelo de segmento → setores → convidar pessoas → jornadas → regras do ponto → marca. É a primeira impressão de quem compra |
| D2 | P0 | M | **Busca global (Ctrl+K)** por pessoas, páginas e solicitações; **central de notificações** dentro do Workspace |
| D3 | P0 | S | Estados vazios e *skeletons* no lugar de *spinners*; mensagens de erro úteis; confirmações destrutivas padronizadas (hoje usa `window.confirm`) |
| D4 | P1 | M | **Acessibilidade (WCAG 2.2 AA)**: foco visível, captura de foco nos modais, contraste (textos de 11 px em cinza claro), navegação por teclado, rótulos; auditoria com axe |
| D5 | P1 | M | **Tema escuro** (hoje 0 telas com `dark:`) e respeito a `prefers-color-scheme` |
| D6 | P1 | M | **PWA instalável** (manifest, ícones, cache), **notificações push** (aviso de ponto, aprovação) e modo offline do relógio |
| D7 | P1 | M | **Marca da empresa** (logo, cor primária) aplicada na casca; e-mails com a marca |
| D8 | P1 | M | Espelho de ponto e fechamento em **PDF** para impressão/assinatura; comprovante de marcação |
| D9 | P1 | S | Tokens de design (cores, espaçamentos, tipografia) documentados; remover estilos soltos; biblioteca de componentes única |
| D10 | P2 | M | Internacionalização (pt-BR/en/es), formatos de data/hora/moeda por empresa |
| D11 | P2 | M | Painel de Home por papel (colaborador, gestor, RH, CEO) com atalhos e métricas relevantes |

---

## 10. Diferenciais de mercado (para vender melhor)

Ordenados por impacto percebido versus esforço:

1. **Reconhecimento e cultura:** kudos com selos, mural de conquistas, aniversários e tempo de casa (§7).
2. **Comunicação com prova:** comunicado com confirmação de leitura e ciência eletrônica de documentos/holerites.
3. **Onboarding e offboarding** com checklist por área (documentos, acessos, equipamentos) e trilha de integração.
4. **Pesquisa de clima/eNPS** com painel por área e anonimato mínimo garantido.
5. **Central de atendimento do RH** (tickets com SLA, categorias, base de conhecimento) — diferencia de "um mural".
6. **Avaliação de desempenho 360 e ciclos de metas** (já há metas e PDI; faltam ciclos e calibração).
7. **Organograma** navegável (usa a hierarquia da §8).
8. **Integrações:** exportação para layouts de folha (Domínio, Alterdata, Totvs, ADP), SSO Google/Microsoft, WhatsApp/Slack/Teams para avisos, **API pública + webhooks**.
9. **IA assistiva (opcional, com limites):** assistente que responde dúvidas de políticas do RH com a base da empresa; alertas de anomalia de ponto. Só vender depois de definir privacidade e custo.

---

## 11. Riscos de venda a declarar com transparência
- Ponto como **controle gerencial**, não sistema legal certificado (§5.4).
- Foto/localização são dados pessoais: exigir aceite e política de retenção do cliente.
- Tempo real é por *polling* até a entrada do Reverb; para centenas de usuários simultâneos, planejar A6.
- Sem SLA formal, DR testado e monitoramento ainda — fechar S2/A3 antes de assinar contrato com SLA.

---

## 12. Plano sugerido

### Antes da venda (2 dias)

**Dia 1 — Fundação e segurança (tudo P0 de §3/§4)**
1. Worker + scheduler + SMTP real no `docker-compose.prod.yml`; remover mailpit do prod (S1/A1).
2. Backup diário do Postgres + `storage/` e **teste de restauração** (S2); `/up` + monitor externo (A3).
3. Cabeçalhos de segurança + CSP + CORS fechado (S3/S4); `throttle:api` (S7); remover `role` de `$fillable` (S6); TrustProxies configurável (S16).
4. Mídia do Workspace privada, EXIF removido, miniaturas (S5/M1); GitHub Actions com testes e `audit` (S15).
5. Guard para impedir seeders de demonstração em produção (S17).

**Dia 2 — O que o comprador vê (produto)**
1. Fotos no mural + reações + menções (M1/M3).
2. Aniversários + tempo de casa + calendário (feriados, férias do time, eventos) (C1–C3).
3. Gestor/diretor de **área** com árvore e substituto (§8.3 P0).
4. Dispositivo no ponto: aparelho/navegador + dispositivo cadastrado + IP/rede no espelho (§5.2 a/b).
5. Assistente de implantação, busca global e central de notificações (D1/D2); roteiro de demonstração e dados de exemplo.

**Critério de pronto para a venda:** suíte de testes verde; matriz de acesso (papel × recurso) coberta; teste de restauração feito; roteiro de demo ensaiado em celular e desktop; checklist LGPD entregue ao cliente.

### 30 dias (P1)
2FA/SSO, sessões e dispositivos, política de senha, auditoria com tela e imutabilidade, criptografia de dados sensíveis, LGPD operacional, vídeo com transcodificação,
público-alvo e confirmação de leitura, painel do CEO, PDF do espelho/comprovante, tema escuro e acessibilidade, PWA/push, Reverb, Playwright, Sentry, staging.

### 60–90 dias (P2)
Papéis personalizados completos, kiosk/passkeys/agente, conformidade legal do ponto (com parecer), integrações de folha e API pública, 360, clima/eNPS, helpdesk do RH, i18n.

---

## 13. Decisões que dependem de você
1. **Como posicionar o ponto** (controle gerencial × sistema legal)? Define o investimento em §5.4.
2. **Onde hospedar** (VPS única × nuvem gerenciada) e **quem opera** backup/monitoramento? Define S2/A3.
3. **Mídia:** aceitam vídeo no primeiro lançamento ou só fotos (recomendado: só fotos agora)?
4. **Dispositivo do ponto:** basta "dispositivo cadastrado" (§5.2 b) ou o cliente exige quiosque/passkey?
5. **Marca branca:** cada cliente terá subdomínio e marca própria já na venda?

## Status — atualização (ponto, LGPD, PWA)

Entregue desde a última revisão:

- **Auditoria:** tela de auditoria (dono/RH) com filtros e exportação CSV.
- **Busca global** (Ctrl+K), **feriados e férias** zerando a jornada esperada, **comunicado com confirmação de leitura**, **painel da diretoria** (papel somente leitura) e **assistente de implantação** (Primeiros passos).
- **Ponto à prova de adulteração:** NSR por empresa + hash SHA-256 encadeado; comprovante imprimível; verificação de integridade (Papéis e estrutura). É evidência de adulteração — **não** é certificação de REP/Portaria 671; tratar como controle gerencial.
- **LGPD:** aceite de foto/localização antes da primeira marcação (registrado e auditado); "Baixar meus dados" (Segurança); retenção automática — `ponto:purge-sensitive` apaga foto/coordenadas com mais de 12 meses (`PUNCH_SENSITIVE_RETENTION_MONTHS`).
- **PWA básico:** manifest + service worker mínimo (instalável, aviso offline). Não guarda dados em cache.

Ainda pendente: tema escuro, acessibilidade (focus trap/contraste), sessão por cookie httpOnly, matriz de permissões customizada, parabéns automático, antivírus/miniaturas/vídeo, Reverb (tempo real), Playwright/Sentry, revisão jurídica (Portaria 671, termos LGPD), SMTP, backup offsite e passphrase de backup em produção.

## Status — rede social interna e fluxos de RH

Entregue na branch `feature/workspace-social`:

- **Início com mural:** o mural é o centro da tela inicial; parabéns automático de aniversário (com foto, só de quem deixou visível, a data é cadastrada pelo RH), comentários visíveis, vídeo, reações, fixar/desafixar, reconhecimentos entre colegas e enquetes anônimas.
- **Perfil em abas:** perfil social (a pessoa edita), dados cadastrais (RH edita; criptografados; leitura pela própria pessoa), avisos e equipe/conexões. RH abre o perfil de qualquer pessoa em `/pessoas/:id`.
- **Chamados ao RH:** categoria, prazo de 48h, assumir e resolver com justificativa obrigatória; o gestor não vê. Relatório próprio com tempos de resposta.
- **Vagas internas** com candidatura confidencial e divulgação por link público (WhatsApp).
- **Organograma** navegável; **chat lateral** minimizável/ampliável; **relatórios** por papel (horas, ocorrências, ajustes, chamados, banco, quadro).
- **CI:** branch `fix/ci-deps` corrige dependências incompatíveis do `main` (vitest/@vitest/ui, TypeScript 7 × typescript-eslint, imports de tipos) e usa Node 22.

Pendente: pesquisa de clima recorrente, grupos por área no mural, painel visual dos chamados, matriz de permissões editável, sessão por cookie httpOnly, antivírus/miniaturas de mídia, tempo real (Reverb).
