#!/usr/bin/env bash
# Daily backup: pg_dump + uploaded files. Idempotent and self-pruning.
set -Eeuo pipefail

INSTALL_DIR="${INSTALL_DIR:-/opt/a1-sms-gateway}"
COMPOSE_PROJECT="${COMPOSE_PROJECT:-a1-sms}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/a1-sms}"
RETAIN_DAYS="${RETAIN_DAYS:-14}"
STAMP="$(date -u +%Y-%m-%dT%H%M%SZ)"

mkdir -p "$BACKUP_DIR/postgres" "$BACKUP_DIR/storage"

cd "$INSTALL_DIR"
# shellcheck disable=SC1091
. ./.env

echo "[a1-sms backup] starting at $STAMP"

# ---- Postgres dump from inside the running container ----
docker compose -p "$COMPOSE_PROJECT" exec -T postgres \
  pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" --no-owner --no-privileges \
  | gzip -9 > "$BACKUP_DIR/postgres/${STAMP}.sql.gz"

# ---- Storage (uploads, generated invoices) ----
docker compose -p "$COMPOSE_PROJECT" cp api:/var/www/html/storage "$BACKUP_DIR/storage/_tmp" >/dev/null
tar -czf "$BACKUP_DIR/storage/${STAMP}.tar.gz" -C "$BACKUP_DIR/storage" _tmp
rm -rf "$BACKUP_DIR/storage/_tmp"

# ---- Prune old ----
find "$BACKUP_DIR/postgres" -type f -mtime "+$RETAIN_DAYS" -delete
find "$BACKUP_DIR/storage"  -type f -mtime "+$RETAIN_DAYS" -delete

# ---- Optional S3 offsite ----
if [ -n "${BACKUP_S3_BUCKET:-}" ] && command -v aws >/dev/null 2>&1; then
  aws s3 sync "$BACKUP_DIR" "s3://${BACKUP_S3_BUCKET}/a1-sms" --quiet
fi

echo "[a1-sms backup] done"
