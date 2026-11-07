@echo off
REM Gera o build de producao do front (frontend/dist), que o nginx serve em http://localhost:9595
docker compose -p hotelconnect exec node npx vite build
