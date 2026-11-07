#!/bin/sh
# Cópia do banco (pg_dump) e dos arquivos enviados (storage/app). Roda dentro do serviço "backup".
# Variáveis: PGHOST PGUSER PGPASSWORD PGDATABASE, BACKUP_DIR, STORAGE_DIR, BACKUP_RETENTION_DAYS, BACKUP_PASSPHRASE (opcional).
set -eu

BACKUP_DIR="${BACKUP_DIR:-/backups}"
STORAGE_DIR="${STORAGE_DIR:-/storage/app}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"

mkdir -p "$BACKUP_DIR"
echo "[backup] $(date -Iseconds) iniciando"

DB_FILE="$BACKUP_DIR/db-$STAMP.dump"
pg_dump -h "$PGHOST" -U "$PGUSER" -d "$PGDATABASE" -Fc -f "$DB_FILE"
pg_restore -l "$DB_FILE" > /dev/null   # garante que o arquivo é legível

FILES="$DB_FILE"
if [ -d "$STORAGE_DIR" ]; then
  ST_FILE="$BACKUP_DIR/storage-$STAMP.tar.gz"
  tar -czf "$ST_FILE" -C "$(dirname "$STORAGE_DIR")" "$(basename "$STORAGE_DIR")"
  FILES="$FILES $ST_FILE"
fi

if [ -n "${BACKUP_PASSPHRASE:-}" ]; then
  command -v openssl > /dev/null 2>&1 || apk add --no-cache openssl > /dev/null
  for f in $FILES; do
    openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_PASSPHRASE -in "$f" -out "$f.enc"
    rm -f "$f"
  done
  echo "[backup] arquivos criptografados (AES-256)"
else
  echo "[backup] AVISO: BACKUP_PASSPHRASE não definida; cópias sem criptografia"
fi

find "$BACKUP_DIR" -type f \( -name 'db-*' -o -name 'storage-*' \) -mtime +"$RETENTION_DAYS" -delete
date -Iseconds > "$BACKUP_DIR/.last-success"
echo "[backup] concluído: $(find "$BACKUP_DIR" -maxdepth 1 -name "*$STAMP*" | wc -l) arquivo(s) em $BACKUP_DIR"
