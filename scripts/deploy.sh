#!/usr/bin/env bash
# Rolling update: pull, build, migrate, restart services one at a time.
set -Eeuo pipefail
cd "$(dirname "$0")/.."

COMPOSE_PROJECT="${COMPOSE_PROJECT:-a1-sms}"
TS="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

echo "[$TS] deploy: pulling latest ref"
git pull --ff-only

echo "[$TS] deploy: building"
docker compose -p "$COMPOSE_PROJECT" pull --quiet
docker compose -p "$COMPOSE_PROJECT" build --pull

echo "[$TS] deploy: migrating"
docker compose -p "$COMPOSE_PROJECT" exec -T api php artisan migrate --force --no-interaction

# Roll services one at a time so Nginx stays up.
for svc in api worker scheduler horizon web; do
  echo "[$TS] deploy: rolling $svc"
  docker compose -p "$COMPOSE_PROJECT" up -d --no-deps --build "$svc"
done

echo "[$TS] deploy: reloading nginx"
docker compose -p "$COMPOSE_PROJECT" exec -T nginx nginx -s reload || true

echo "[$TS] deploy: done"
