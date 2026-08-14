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

echo "==> Pronto! Confira em https://$(grep DOMAIN .env.prod | cut -d= -f2)"
