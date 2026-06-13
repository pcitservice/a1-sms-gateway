#!/usr/bin/env bash
set -Eeuo pipefail

cd /var/www/html

# First-boot housekeeping. Safe to repeat.
php artisan storage:link >/dev/null 2>&1 || true
php artisan config:cache  >/dev/null 2>&1 || true
php artisan route:cache   >/dev/null 2>&1 || true
php artisan view:cache    >/dev/null 2>&1 || true
php artisan event:cache   >/dev/null 2>&1 || true

role="${CONTAINER_ROLE:-${1:-api}}"

case "$role" in
  api)
    exec php artisan serve --host=0.0.0.0 --port=8000
    ;;
  scheduler)
    exec php artisan schedule:work
    ;;
  worker)
    # Multiple queues, prioritised. Webhooks first so deliveries don't lag.
    exec php artisan queue:work rabbitmq \
      --queue=webhooks,sms.outbound,sms.inbound,default \
      --tries=8 --backoff=5,15,30,60,120,300,600 --timeout=120 \
      --max-jobs=1000 --max-time=3600 --sleep=1
    ;;
  horizon)
    exec php artisan horizon
    ;;
  artisan)
    shift || true
    exec php artisan "$@"
    ;;
  *)
    exec "$@"
    ;;
esac
