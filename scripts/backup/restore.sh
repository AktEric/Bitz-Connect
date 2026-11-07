#!/bin/sh
# RESTAURAÇÃO REAL (destrutiva): substitui o conteúdo do banco atual pela cópia informada.
# Antes: pare os serviços que escrevem no banco (php, queue, scheduler) e faça uma cópia nova.
#   docker compose --env-file .env.prod -f docker-compose.prod.yml stop php queue scheduler
#   docker compose --env-file .env.prod -f docker-compose.prod.yml run --rm --no-deps -e CONFIRM=yes backup \
#       sh /scripts/restore.sh /backups/db-AAAAMMDD-HHMMSS.dump[.enc]
#   docker compose --env-file .env.prod -f docker-compose.prod.yml start php queue scheduler
# Arquivos enviados: extraia storage-*.tar.gz (decifre antes, se .enc) sobre backend/storage/.
set -eu

FILE="${1:-}"
[ -n "$FILE" ] && [ -f "$FILE" ] || { echo "uso: CONFIRM=yes sh restore.sh /backups/db-XXXX.dump[.enc]" >&2; exit 1; }
[ "${CONFIRM:-}" = "yes" ] || { echo "Operação destrutiva. Rode de novo com CONFIRM=yes." >&2; exit 1; }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
case "$FILE" in
  *.enc)
    [ -n "${BACKUP_PASSPHRASE:-}" ] || { echo "defina BACKUP_PASSPHRASE" >&2; exit 1; }
    command -v openssl > /dev/null 2>&1 || apk add --no-cache openssl > /dev/null
    openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_PASSPHRASE -in "$FILE" -out "$WORK/db.dump"
    FILE="$WORK/db.dump" ;;
esac

pg_restore -h "$PGHOST" -U "$PGUSER" -d "$PGDATABASE" --clean --if-exists --no-owner "$FILE"
echo "[restore] concluído em $PGDATABASE"
