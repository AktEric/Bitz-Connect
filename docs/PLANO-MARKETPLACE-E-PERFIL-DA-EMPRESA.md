# Plano — Perfil da empresa no feed e Marketplace interno

## 1. Perfil da empresa no feed (fase 1)

**Objetivo:** o mural ganha um perfil oficial com o nome e a logo da empresa, que parabeniza aniversariantes e dá boas-vindas a quem chega, sempre com a foto da pessoa, sem ninguém precisar publicar.

- `workspace_posts.as_company` (bool). Quando verdadeiro, o autor exibido é a empresa (`{name, avatar_url, is_company}`), não a pessoa. `author_id` continua apontando para quem originou (aniversariante, novo colaborador ou o admin que publicou) para auditoria e notificações.
- **Aniversário** (`workspace:birthday-posts`, 07:00): texto da empresa com menção `@[Nome](uuid)` (link para o perfil), cargo e setor. A foto vem do perfil da pessoa na hora de exibir.
- **Boas-vindas** (`workspace:welcome-posts`, 07:05): quem foi admitido hoje (com janela de recuperação `--days=3`) recebe um post da empresa mencionando o setor e o cargo. Idempotente (um por pessoa).
- **Administradores** publicam "como a empresa" (checkbox no compositor, só para quem modera o mural).
- Posts antigos de aniversário são migrados para `as_company = true`.

## 2. Marketplace interno

Inspirado no Facebook Marketplace, no estilo do Workspace: grade de cartões com foto quadrada, preço em destaque, filtros à esquerda, anúncio em tela grande (galeria + vendedor + ações).

### Modelo de dados
| Tabela | Campos principais |
|---|---|
| `marketplace_listings` | uuid, hotel_id, seller_id, title, description, price_cents (nulo = "a combinar"), category, condition, location, status (`active`/`sold`/`hidden`), views_count, likes_count, reposts_count, sold_at, soft delete |
| `marketplace_images` | uuid, listing_id, path (disco privado), mime, size, position |
| `marketplace_likes` | listing_id, user_id (único) |
| `marketplace_reposts` | listing_id, user_id (único), post_id (publicação criada no mural) |
| `workspace_posts.listing_id` | cartão do anúncio dentro de um repost |

### Regras
- Só membros aprovados da empresa veem e anunciam; tudo escopado por `hotel_id`.
- Até 6 fotos (JPG/PNG/WebP, 5 MB), sanitizadas (sem EXIF/GPS) e guardadas em disco privado, entregues por URL assinada curta, como as fotos do mural.
- Até 20 anúncios ativos por pessoa. Preço ≥ 0 e limitado; texto sempre tratado como texto puro.
- Vendedor edita, marca como vendido e remove; quem modera (`marketplace.moderate`, dono/RH) oculta ou remove qualquer anúncio. Todo moderado entra na auditoria.
- Capacidades: `marketplace.view`, `marketplace.sell`, `marketplace.moderate` em `WorkspacePermissions`.

### API (`/workspaces/{slug}/marketplace`)
`GET /` (q, category, condition, min, max, sort, mine, liked, seller) · `GET /{uuid}` · `POST /` · `PATCH /{uuid}` · `DELETE /{uuid}` · `POST /{uuid}/like` · `POST /{uuid}/repost` · `POST /{uuid}/images` · `DELETE /{uuid}/images/{img}`

### Experiência
1. **Lista**: busca, categorias, faixa de preço, condição, ordenação; abas Todos / Meus anúncios / Favoritos; no celular os filtros viram gaveta.
2. **Anunciar**: modal com fotos (prévia, reordenar, remover), título, preço, categoria, condição e descrição.
3. **Anúncio**: galeria, preço, descrição, vendedor com link para o perfil, **Chamar no privado** (abre a janela de chat com um cartão do anúncio), curtir, repostar no mural, copiar link, e para o dono: editar, marcar como vendido, remover.
4. **Mural**: o repost aparece como cartão do anúncio dentro da publicação.

## Fases de entrega
1. Perfil da empresa (aniversário, boas-vindas, publicar como empresa)
2. Marketplace: banco, API, permissões e testes
3. Marketplace: lista, filtros e criação de anúncio
4. Marketplace: detalhe, curtir, chat com cartão, marcar vendido, repost no mural
5. Revisão: acessibilidade, celular, build e verificação
