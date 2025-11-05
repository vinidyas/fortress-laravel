#!/bin/bash

# Restaura banco, storage e .env a partir de artefatos baixados do OneDrive.
# Uso: sudo scripts/restore_from_backup.sh [/opt/fortress-backups/manual-restore]

set -euo pipefail

DEFAULT_DIR="/opt/fortress-backups/manual-restore"
RESTORE_DIR="${1:-$DEFAULT_DIR}"
PROJECT_DIR="/root/docker/fortress/fortress-laravel"

if [[ $EUID -ne 0 ]]; then
  echo "Este script precisa ser executado como root." >&2
  exit 1
fi

if [[ ! -d "$RESTORE_DIR" ]]; then
  echo "Diretório $RESTORE_DIR não encontrado. Crie-o e coloque os arquivos de backup nele." >&2
  exit 1
fi

cd "$RESTORE_DIR"

SQL_GZ="$(ls -1 fortress-*.sql.gz 2>/dev/null | sort | tail -n 1 || true)"
STORAGE_TAR="$(ls -1 storage-*.tar.gz 2>/dev/null | sort | tail -n 1 || true)"
ENV_FILE="$(ls -1 .env-* 2>/dev/null | sort | tail -n 1 || true)"

if [[ -z "$SQL_GZ" || -z "$STORAGE_TAR" || -z "$ENV_FILE" ]]; then
  echo "Arquivos esperados não encontrados em $RESTORE_DIR." >&2
  echo "Necessários: fortress-*.sql.gz, storage-*.tar.gz, .env-*" >&2
  exit 1
fi

echo "Utilizando:"
echo "  Dump SQL      : $SQL_GZ"
echo "  Storage tar   : $STORAGE_TAR"
echo "  Arquivo .env  : $ENV_FILE"
echo
read -rp "Confirma a restauração? Isso sobrescreverá os dados atuais. (digite 'sim'): " CONFIRM
if [[ "$CONFIRM" != "sim" ]]; then
  echo "Operação cancelada."
  exit 0
fi

cd "$PROJECT_DIR"

echo "[1/6] Criando snapshots antes da restauração..."
TIMESTAMP="$(date +%Y%m%d-%H%M)"
docker compose exec -T fortress-db sh -c 'mysqldump --no-tablespaces -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  | gzip > "$RESTORE_DIR/pre-restore-$TIMESTAMP-fortress.sql.gz"
docker run --rm -v fortress_storage:/data -v "$RESTORE_DIR":/backup alpine sh -c "cd /data && tar czf /backup/pre-restore-$TIMESTAMP-storage.tar.gz ."
cp .env "$RESTORE_DIR/.env-pre-restore-$TIMESTAMP"

echo "[2/6] Limpando banco de dados..."
docker compose exec -T fortress-db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE \`$MYSQL_DATABASE\`; CREATE DATABASE \`$MYSQL_DATABASE\`;"'

echo "[3/6] Restaurando banco de dados..."
gunzip -c "$RESTORE_DIR/$SQL_GZ" | docker compose exec -T fortress-db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'

echo "[4/6] Restaurando arquivos do storage..."
docker run --rm -v fortress_storage:/data -v "$RESTORE_DIR":/backup alpine sh -c "rm -rf /data/* && tar xzf /backup/$STORAGE_TAR -C /data"

echo "[5/6] Restaurando .env..."
mv -f .env ".env.before-restore-$TIMESTAMP"
cp "$RESTORE_DIR/$ENV_FILE" .env
chmod 600 .env
chown root:root .env

echo "[6/6] Reiniciando containers..."
docker compose down
docker compose up -d

echo
echo "Restauração concluída com sucesso."
echo "Snapshots gerados em $RESTORE_DIR:"
ls -1 "$RESTORE_DIR"/pre-restore-* 2>/dev/null || true
echo
echo "Verifique o sistema e, se tudo estiver em ordem, remova os arquivos temporários conforme necessário."
