#!/usr/bin/env bash
# =============================================================================
#  A1 SMS Gateway — one-command installer
#  Target: Ubuntu 24.04 LTS, run as root.
#
#  Usage:
#    curl -fsSL https://install.sms.a1techflow.com/install.sh | bash
#
#  Environment overrides:
#    INSTALL_DIR      (default /opt/a1-sms-gateway)
#    INSTALL_REF      (default main)         git ref to deploy
#    INSTALL_REPO     (default https://github.com/a1techflow/a1-sms-gateway.git)
#    DOMAIN           (default sms.a1techflow.com)
#    LETSENCRYPT_EMAIL
#    SKIP_TLS=1       skip certbot (useful behind Cloudflare with origin certs)
#    SKIP_FIREWALL=1  skip ufw/fail2ban configuration
#
#  This script is idempotent — re-running upgrades to the current ref.
# =============================================================================
set -Eeuo pipefail

INSTALL_DIR="${INSTALL_DIR:-/opt/a1-sms-gateway}"
INSTALL_REF="${INSTALL_REF:-main}"
INSTALL_REPO="${INSTALL_REPO:-https://github.com/pcitservice/a1-sms-gateway.git}"
DOMAIN="${DOMAIN:-sms.a1techflow.com}"
LETSENCRYPT_EMAIL="${LETSENCRYPT_EMAIL:-admin@a1techflow.com}"
COMPOSE_PROJECT="${COMPOSE_PROJECT:-a1-sms}"

c_blue='\033[1;34m'; c_green='\033[1;32m'; c_yellow='\033[1;33m'; c_red='\033[1;31m'; c_off='\033[0m'

log()  { printf "${c_blue}==>${c_off} %s\n" "$*"; }
ok()   { printf "${c_green}✓${c_off} %s\n" "$*"; }
warn() { printf "${c_yellow}!${c_off} %s\n" "$*"; }
err()  { printf "${c_red}✗${c_off} %s\n" "$*" >&2; }

trap 'err "Installer failed on line ${LINENO}. Re-run safely; the script is idempotent."' ERR

require_root() {
  if [ "$(id -u)" -ne 0 ]; then
    err "This installer must run as root. Try: sudo bash $0"
    exit 1
  fi
}

require_ubuntu() {
  if ! grep -q "Ubuntu" /etc/os-release 2>/dev/null; then
    warn "Detected non-Ubuntu OS. Continuing, but only Ubuntu 24.04 is supported."
  fi
}

apt_install_quiet() {
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq -o=Dpkg::Use-Pty=0 "$@"
}

step_system_packages() {
  log "Installing system packages"
  apt-get update -qq
  apt_install_quiet ca-certificates curl gnupg lsb-release git jq make rsync \
                    ufw fail2ban logrotate cron openssl unzip
  ok "System packages installed"
}

step_docker() {
  if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    ok "Docker already installed: $(docker --version)"
    return
  fi
  log "Installing Docker CE + Compose v2"
  install -m 0755 -d /etc/apt/keyrings
  if [ ! -f /etc/apt/keyrings/docker.gpg ]; then
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
      | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg
  fi
  . /etc/os-release
  cat > /etc/apt/sources.list.d/docker.list <<EOF
deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable
EOF
  apt-get update -qq
  apt_install_quiet docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  systemctl enable --now docker
  ok "Docker installed"
}

step_clone_or_update() {
  if [ -d "$INSTALL_DIR/.git" ]; then
    log "Updating existing checkout in $INSTALL_DIR"
    git -C "$INSTALL_DIR" fetch --depth 1 origin "$INSTALL_REF"
    git -C "$INSTALL_DIR" reset --hard "origin/$INSTALL_REF"
  else
    log "Cloning $INSTALL_REPO into $INSTALL_DIR (ref: $INSTALL_REF)"
    mkdir -p "$(dirname "$INSTALL_DIR")"
    git clone --depth 1 --branch "$INSTALL_REF" "$INSTALL_REPO" "$INSTALL_DIR"
  fi
  ok "Repository ready"
}

gen_secret() { openssl rand -base64 32 | tr -d '\n=+/' | cut -c1-40; }

step_env() {
  cd "$INSTALL_DIR"
  if [ ! -f .env ]; then
    log "Bootstrapping .env"
    cp .env.example .env
    sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
    sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(gen_secret)|" .env
    sed -i "s|^RABBITMQ_PASSWORD=.*|RABBITMQ_PASSWORD=$(gen_secret)|" .env
    sed -i "s|^WEBHOOK_SIGNING_SECRET=.*|WEBHOOK_SIGNING_SECRET=$(gen_secret)|" .env
    sed -i "s|^NEXTAUTH_SECRET=.*|NEXTAUTH_SECRET=$(gen_secret)|" .env
    BOOT_PW="$(gen_secret)"
    sed -i "s|^BOOTSTRAP_ADMIN_PASSWORD=.*|BOOTSTRAP_ADMIN_PASSWORD=${BOOT_PW}|" .env
    echo "$BOOT_PW" > /root/.a1-sms-bootstrap-admin-password
    chmod 600 /root/.a1-sms-bootstrap-admin-password
    ok ".env generated; bootstrap admin password saved to /root/.a1-sms-bootstrap-admin-password"
  else
    ok ".env already exists — preserved"
  fi
  chmod 600 .env
}

step_compose_up() {
  cd "$INSTALL_DIR"
  log "Pulling images"
  COMPOSE_PROJECT_NAME="$COMPOSE_PROJECT" docker compose pull --quiet || true
  log "Building images"
  COMPOSE_PROJECT_NAME="$COMPOSE_PROJECT" docker compose build --pull
  log "Starting stack"
  COMPOSE_PROJECT_NAME="$COMPOSE_PROJECT" docker compose up -d --remove-orphans
  ok "Stack is up"
}

step_wait_for_api() {
  log "Waiting for API to become healthy"
  for i in $(seq 1 60); do
    if curl -fsS "http://127.0.0.1:$(grep -E '^API_PORT=' "$INSTALL_DIR/.env" | cut -d= -f2)/api/v1/health" >/dev/null 2>&1; then
      ok "API is healthy"
      return
    fi
    sleep 2
  done
  err "API never became healthy. See: docker compose logs api"
  exit 1
}

# Belt-and-suspenders: ensure the RabbitMQ user exists with the .env password.
# The image's RABBITMQ_DEFAULT_USER env var is supposed to do this on first
# boot, but doesn't reliably fire when definitions.json is also mounted.
step_ensure_rabbit_user() {
  cd "$INSTALL_DIR"
  local user pass
  user=$(grep -E '^RABBITMQ_USER='     .env | cut -d= -f2 | tr -d '\r')
  pass=$(grep -E '^RABBITMQ_PASSWORD=' .env | cut -d= -f2 | tr -d '\r')
  [ -z "$user" ] || [ -z "$pass" ] && { warn "Skipping RabbitMQ user setup — credentials missing in .env"; return; }

  for i in $(seq 1 30); do
    if COMPOSE_PROJECT_NAME="$COMPOSE_PROJECT" docker compose exec -T rabbitmq rabbitmqctl status >/dev/null 2>&1; then break; fi
    sleep 2
  done

  COMPOSE_PROJECT_NAME="$COMPOSE_PROJECT" docker compose exec -T rabbitmq sh -c "
    rabbitmqctl list_users 2>/dev/null | grep -q '^$user\b' || rabbitmqctl add_user $user $pass
    rabbitmqctl change_password $user $pass
    rabbitmqctl set_user_tags $user administrator
    rabbitmqctl set_permissions -p / $user '.*' '.*' '.*'
  " >/dev/null 2>&1 || warn "RabbitMQ user provisioning had issues — check 'docker compose logs rabbitmq'"
  ok "RabbitMQ user '$user' provisioned"
}

step_migrate_seed() {
  cd "$INSTALL_DIR"
  log "Running migrations + seeders"
  COMPOSE_PROJECT_NAME="$COMPOSE_PROJECT" docker compose exec -T api php artisan migrate --force --seed --no-interaction
  COMPOSE_PROJECT_NAME="$COMPOSE_PROJECT" docker compose exec -T api php artisan a1:bootstrap-admin --no-interaction || true
  ok "Database migrated + seeded"
}

step_nginx_vhost() {
  if [ -d /etc/nginx/sites-available ] && command -v nginx >/dev/null 2>&1; then
    log "Installing host Nginx vhost for $DOMAIN"
    install -m 0644 "$INSTALL_DIR/deploy/nginx/sms.a1techflow.com.conf" \
                    "/etc/nginx/sites-available/${DOMAIN}.conf"
    sed -i "s|sms.a1techflow.com|${DOMAIN}|g" "/etc/nginx/sites-available/${DOMAIN}.conf"
    ln -sf "/etc/nginx/sites-available/${DOMAIN}.conf" "/etc/nginx/sites-enabled/${DOMAIN}.conf"
    nginx -t && systemctl reload nginx
    ok "Host Nginx configured"
    return 0
  fi
  warn "Host Nginx not detected — using the in-stack Nginx container on 80/443"
  return 1
}

step_tls() {
  if [ -n "${SKIP_TLS:-}" ]; then
    warn "SKIP_TLS set — skipping certbot"
    return
  fi
  if ! command -v certbot >/dev/null 2>&1; then
    apt_install_quiet certbot python3-certbot-nginx
  fi
  if [ -d /etc/nginx/sites-available ] && command -v nginx >/dev/null 2>&1; then
    log "Requesting Let's Encrypt cert for $DOMAIN via certbot --nginx"
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$LETSENCRYPT_EMAIL" --redirect || \
      warn "certbot failed — leaving HTTP-only; re-run after fixing DNS"
  else
    log "Requesting Let's Encrypt cert in standalone mode (will briefly use :80)"
    docker compose -p "$COMPOSE_PROJECT" stop nginx >/dev/null 2>&1 || true
    certbot certonly --standalone -d "$DOMAIN" --non-interactive --agree-tos \
      -m "$LETSENCRYPT_EMAIL" || warn "certbot failed; re-run after fixing DNS"
    docker compose -p "$COMPOSE_PROJECT" start nginx >/dev/null 2>&1 || true
  fi
  systemctl enable --now certbot.timer 2>/dev/null || true
  ok "TLS configured"
}

step_firewall() {
  if [ -n "${SKIP_FIREWALL:-}" ]; then
    warn "SKIP_FIREWALL set — skipping ufw/fail2ban"
    return
  fi
  log "Configuring UFW"
  ufw --force reset >/dev/null
  ufw default deny incoming
  ufw default allow outgoing
  ufw allow 22/tcp
  ufw allow 80/tcp
  ufw allow 443/tcp
  ufw --force enable
  log "Configuring fail2ban jail"
  install -m 0644 "$INSTALL_DIR/deploy/scripts/jail.a1sms.local" /etc/fail2ban/jail.d/a1sms.local
  systemctl restart fail2ban
  ok "Firewall + fail2ban active"
}

step_cron_backups() {
  log "Installing daily backups + healthcheck timer"
  install -m 0755 "$INSTALL_DIR/deploy/scripts/backup.sh"        /etc/cron.daily/a1-sms-backup
  install -m 0755 "$INSTALL_DIR/deploy/scripts/health-check.sh"  /usr/local/sbin/a1-sms-healthcheck
  install -m 0644 "$INSTALL_DIR/deploy/systemd/a1-sms-healthcheck.service" /etc/systemd/system/
  install -m 0644 "$INSTALL_DIR/deploy/systemd/a1-sms-healthcheck.timer"   /etc/systemd/system/
  systemctl daemon-reload
  systemctl enable --now a1-sms-healthcheck.timer
  ok "Backups + healthcheck scheduled"
}

step_done() {
  cat <<EOF

$(printf "${c_green}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${c_off}")
$(printf "${c_green} A1 SMS Gateway is installed.${c_off}")
$(printf "${c_green}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${c_off}")

  URL:          https://${DOMAIN}
  Install dir:  ${INSTALL_DIR}
  Compose:      docker compose -p ${COMPOSE_PROJECT} ps
  Admin email:  $(grep -E '^BOOTSTRAP_ADMIN_EMAIL=' "$INSTALL_DIR/.env" | cut -d= -f2)
  Admin pw:     (saved at /root/.a1-sms-bootstrap-admin-password)

  Tail logs:    docker compose -p ${COMPOSE_PROJECT} logs -f api web nginx
  Update:       cd ${INSTALL_DIR} && ./scripts/deploy.sh

EOF
}

main() {
  require_root
  require_ubuntu
  step_system_packages
  step_docker
  step_clone_or_update
  step_env
  step_compose_up
  step_wait_for_api
  step_ensure_rabbit_user
  step_migrate_seed
  step_nginx_vhost || true
  step_tls
  step_firewall
  step_cron_backups
  step_done
}

main "$@"
