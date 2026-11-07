#!/usr/bin/env bash
# Sobe/atualiza o ambiente de demonstração na VPS.
# Uso: ./deploy.sh
set -euo pipefail
cd "$(dirname "$0")"

if [ ! -f .env.prod ]; then
  echo "Falta o arquivo .env.prod (copie .env.prod.example e preencha DOMAIN/DB_*)." >&2
  exit 1
fi
if [ ! -f backend/.env ]; then
  echo "Falta o arquivo backend/.env (copie backend/.env.production.example e preencha)." >&2
  exit 1
fi

echo "==> Buildando frontend (Vite)"
docker run --rm \
  -v "$(pwd)/frontend:/app" \
  -w /app \
  node:20-alpine \
  sh -c "npm ci && npm run build"

echo "==> Buildando/subindo containers"
docker compose --env-file .env.prod -f docker-compose.prod.yml up -d --build

echo "==> Rodando migrations e caches do Laravel"
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T php php artisan migrate --force
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T php php artisan config:cache
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T php php artisan route:cache
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T php php artisan storage:link || true

echo "==> Reiniciando fila e agendador para carregar o código novo"
docker compose --env-file .env.prod -f docker-compose.prod.yml exec -T php php artisan queue:restart || true
docker compose --env-file .env.prod -f docker-compose.prod.yml restart queue scheduler

DOMAIN_NAME="$(grep '^DOMAIN=' .env.prod | cut -d= -f2)"
echo "==> Verificando saúde (https://$DOMAIN_NAME/up)"
for i in 1 2 3 4 5 6; do
  if curl -fsS "https://$DOMAIN_NAME/up" > /dev/null 2>&1; then echo "    OK"; break; fi
  [ "$i" = 6 ] && echo "    AVISO: /up não respondeu; confira os logs (docker compose logs php caddy)" >&2
  sleep 5
done

echo "==> Pronto! Confira em https://$(grep DOMAIN .env.prod | cut -d= -f2)"
