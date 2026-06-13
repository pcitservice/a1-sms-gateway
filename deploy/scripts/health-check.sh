#!/usr/bin/env bash
# Probes API + container health. Posts to ALERT_WEBHOOK if anything is broken.
set -Eeuo pipefail

INSTALL_DIR="${INSTALL_DIR:-/opt/a1-sms-gateway}"
COMPOSE_PROJECT="${COMPOSE_PROJECT:-a1-sms}"
ALERT_WEBHOOK="${ALERT_WEBHOOK:-}"
DOMAIN="${DOMAIN:-sms.a1techflow.com}"

cd "$INSTALL_DIR"

failures=()

# Container state
while IFS= read -r line; do
  name=$(echo "$line" | awk '{print $1}')
  state=$(echo "$line" | awk '{print $2}')
  if [ "$state" != "running" ] && [ -n "$name" ]; then
    failures+=("container $name is $state")
  fi
done < <(docker compose -p "$COMPOSE_PROJECT" ps --format '{{.Service}} {{.State}}' 2>/dev/null || true)

# API health
if ! curl -fsS --max-time 5 "https://$DOMAIN/api/v1/health" | grep -q '"status":"ok"'; then
  failures+=("API health probe failed")
fi

if [ "${#failures[@]}" -eq 0 ]; then
  exit 0
fi

printf 'A1 SMS health: %s\n' "${failures[@]}" >&2

if [ -n "$ALERT_WEBHOOK" ]; then
  body=$(printf '{"host":"%s","failures":["%s"]}' "$(hostname)" "$(IFS='","'; echo "${failures[*]}")")
  curl -fsS -X POST -H 'Content-Type: application/json' --data "$body" "$ALERT_WEBHOOK" || true
fi

exit 1
