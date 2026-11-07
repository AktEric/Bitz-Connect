#!/bin/sh
# Ensaio de restauração: restaura a cópia mais recente num banco TEMPORÁRIO, confere o conteúdo e apaga o temporário.
# Não toca no banco real. Rode de tempos em tempos (pelo menos a cada mês) e antes de assinar contrato com SLA.
# Uso (na pasta do projeto, produção):
#   docker compose --env-file .env.prod -f docker-compose.prod.yml run --rm --no-deps backup sh /scripts/verify-restore.sh
set -eu

BACKUP_DIR="${BACKUP_DIR:-/backups}"
LATEST="$(find "$BACKUP_DIR" -maxdepth 1 -name 'db-*.dump*' 2>/dev/null | sort | tail -n 1)"
[ -n "$LATEST" ] || { echo "[verify] nenhuma cópia de banco encontrada em $BACKUP_DIR" >&2; exit 1; }

TMP_DB="restore_check_$(date +%s)"
WORK="$(mktemp -d)"
trap 'psql -h "$PGHOST" -U "$PGUSER" -d postgres -qc "DROP DATABASE IF EXISTS \"$TMP_DB\"" >/dev/null 2>&1 || true; rm -rf "$WORK"' EXIT

case "$LATEST" in
  *.enc)
    [ -n "${BACKUP_PASSPHRASE:-}" ] || { echo "[verify] cópia criptografada: defina BACKUP_PASSPHRASE" >&2; exit 1; }
    command -v openssl > /dev/null 2>&1 || apk add --no-cache openssl > /dev/null
    openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_PASSPHRASE -in "$LATEST" -out "$WORK/db.dump"
    DUMP="$WORK/db.dump" ;;
  *) DUMP="$LATEST" ;;
esac

echo "[verify] restaurando $(basename "$LATEST") em $TMP_DB"
psql -h "$PGHOST" -U "$PGUSER" -d postgres -qc "CREATE DATABASE \"$TMP_DB\""
pg_restore -h "$PGHOST" -U "$PGUSER" -d "$TMP_DB" --no-owner "$DUMP"

TABLES="$(psql -h "$PGHOST" -U "$PGUSER" -d "$TMP_DB" -tAc "SELECT count(*) FROM information_schema.tables WHERE table_schema='public'")"
USERS="$(psql -h "$PGHOST" -U "$PGUSER" -d "$TMP_DB" -tAc "SELECT count(*) FROM users")"
MIGRATIONS="$(psql -h "$PGHOST" -U "$PGUSER" -d "$TMP_DB" -tAc "SELECT count(*) FROM migrations")"

echo "[verify] tabelas=$TABLES usuários=$USERS migrações=$MIGRATIONS"
[ "$TABLES" -gt 10 ] && [ "$MIGRATIONS" -gt 0 ] || { echo "[verify] FALHOU: conteúdo inesperado" >&2; exit 1; }
echo "[verify] OK — restauração funcionou ($(basename "$LATEST"))"
