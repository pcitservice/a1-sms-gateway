# Deployment

## Target

- Ubuntu 24.04 LTS, x86_64.
- ≥ 2 vCPU, 4 GB RAM, 40 GB SSD.
- Public IPv4 (here: 2.58.82.166).
- DNS A record `sms.a1techflow.com → 2.58.82.166`.
- (Optional but recommended) Cloudflare in front with "Full (strict)" SSL.

## One-command install

On the VPS as `root`:

```bash
curl -fsSL https://install.sms.a1techflow.com/install.sh | bash
```

### Deploying behind CyberPanel / OpenLiteSpeed

If the VPS already runs CyberPanel (very common on Hostinger), the
installer auto-detects it and skips its own nginx + certbot. Follow
[deploy/cyberpanel/README.md](../deploy/cyberpanel/README.md) for the
3-step CyberPanel-side setup (create site → paste rewrite rules → paste
vhost snippet → restart OLS).

The installer streams its progress; you can re-run it any time — it's
idempotent and will upgrade in place. Run with `INSTALL_REF=branch-name`
to install from a non-default branch.

## What the installer does

1. **System** — `apt-get update`, installs `curl git jq make ufw fail2ban
   certbot rsync logrotate`.
2. **Docker** — installs Docker CE + Compose v2 from Docker's own apt repo
   if missing.
3. **Repo** — clones `https://github.com/a1techflow/a1-sms-gateway.git` to
   `/opt/a1-sms-gateway` (or `git pull` if present).
4. **Env** — copies `.env.example` to `.env` on first run and fills in:
   - `APP_KEY` (32-byte random base64)
   - `DB_PASSWORD`, `RABBITMQ_PASSWORD`, `WEBHOOK_SIGNING_SECRET`,
     `NEXTAUTH_SECRET`, `BOOTSTRAP_ADMIN_PASSWORD` (each 32-byte random).
5. **Nginx** — writes `/etc/nginx/sites-available/sms.a1techflow.com.conf`
   (templated from `deploy/nginx/sms.a1techflow.com.conf`) and reloads.
   *If host Nginx isn't installed*, the installer instead uses the in-stack
   Nginx container exposed on the host's 80/443.
6. **TLS** — `certbot --nginx -d sms.a1techflow.com` non-interactively,
   then sets up the renew timer.
7. **Stack** — `docker compose pull && docker compose up -d --remove-orphans`
   with `COMPOSE_PROJECT_NAME=a1-sms` so containers/networks/volumes are
   namespaced.
8. **Migrate + seed** — runs migrations, seeds plans, creates the bootstrap
   admin (credentials printed once at the end).
9. **Hardening** — installs `deploy/scripts/backup.sh` to `/etc/cron.daily`,
   writes the fail2ban jail, opens UFW for 22/80/443 only.
10. **Health check** — curls `https://sms.a1techflow.com/api/v1/health` and
    refuses to declare success until it returns `{ "status": "ok" }`.

## Updating

```bash
cd /opt/a1-sms-gateway
git pull
./scripts/deploy.sh
```

`scripts/deploy.sh` pulls images, runs migrations, rolls services with
zero downtime (Nginx stays up; api/web are restarted one at a time).

## Backups

- **Postgres:** `pg_dump` to `/var/backups/a1-sms/postgres/YYYY-MM-DD.sql.gz`
  daily at 03:00. Last 14 days retained. Optional S3 offsite via
  `BACKUP_S3_BUCKET`.
- **Uploaded files:** `storage/app/public` snapshot via rsync (same path).
- Restore: `./deploy/scripts/restore.sh /var/backups/a1-sms/postgres/2026-06-10.sql.gz`.

## Monitoring

- `/api/v1/health` is the liveness endpoint (DB, Redis, RabbitMQ checked).
- `docker compose ps` plus `deploy/scripts/health-check.sh` runs every 5
  minutes as a systemd timer and alerts (via webhook) when any container
  is `unhealthy`.

## Rolling back

```bash
cd /opt/a1-sms-gateway
git checkout <previous-tag>
./scripts/deploy.sh
```

Migrations are forward-only by convention; for true rollback restore the
matching backup.

## Cloudflare-aware Nginx

If you sit behind Cloudflare, set `TRUSTED_PROXIES=cloudflare` in `.env`
and the Laravel `TrustProxies` middleware will pull Cloudflare's IP list at
boot. Nginx is configured to read `CF-Connecting-IP` for real IP.
