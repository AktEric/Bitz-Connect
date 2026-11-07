# Backup e restauração

O serviço `backup` do `docker-compose.prod.yml` gera, a cada 24 h (configurável por `BACKUP_INTERVAL_SECONDS`):

- `db-AAAAMMDD-HHMMSS.dump` — banco (pg_dump formato custom);
- `storage-AAAAMMDD-HHMMSS.tar.gz` — arquivos enviados (`backend/storage/app`: holerites, fotos de ponto, mídia).

Com `BACKUP_PASSPHRASE` definida, os arquivos saem criptografados (AES-256) com a extensão `.enc`. **Guarde a senha
fora do servidor**: sem ela a cópia não abre. Retenção padrão: 14 dias (`BACKUP_RETENTION_DAYS`).

## Importante: cópia na mesma máquina não é backup completo
A pasta `./backups` está no mesmo servidor. Sincronize-a com um local externo (ex.: `rclone sync ./backups remoto:bucket`
por cron no host, ou volume de outro provedor). Sem isso, perder o servidor significa perder tudo.

## Ensaio de restauração (faça agora e todo mês)
```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml run --rm --no-deps backup sh /scripts/verify-restore.sh
```
Restaura a cópia mais recente num banco temporário, conta tabelas/usuários/migrações e apaga o temporário. Termina com `OK`.

## Restauração real
Ver o cabeçalho de `restore.sh` (exige `CONFIRM=yes`, pare `php queue scheduler` antes).

## Monitoramento
- `/up` responde 200 quando a aplicação está de pé (aponte UptimeRobot/BetterStack para `https://DOMINIO/up`).
- `backups/.last-success` guarda o horário da última cópia bem-sucedida; alerte se passar de 26 h.
