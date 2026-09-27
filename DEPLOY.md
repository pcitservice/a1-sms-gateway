# Deploying A1TechFlow SMS

Production-ready deployment guide for the multi-tenant SMS SaaS. This is
what actually runs at https://sms.a1techflow.com.

- **Stack:** Laravel 12 (PHP 8.3) + Postgres 16 + Redis 7 + RabbitMQ 3 +
  Horizon + Mosquitto 2.0 + Next.js 15 (React 19)
- **Runtime:** Docker Compose, all services on one host
- **Fronting:** CyberPanel / OpenLiteSpeed proxying to an in-stack nginx

## Prerequisites

- A VPS (2 vCPU / 4 GB RAM minimum, 20 GB disk). Ubuntu 22.04 or Debian 12.
- Docker Engine 24+ and the Docker Compose plugin.
- A domain (e.g. `sms.yourbrand.com`) with an A record pointing at the VPS.
- One Teltonika TRB140 (or compatible RUT-series) modem with an active SIM,
  reachable from the VPS. WireGuard is optional but recommended.
- A Stripe account (test-mode keys are fine for staging).
- Postfix on the host, or an external SMTP provider (Resend, Postmark, SES).

## 1. Clone and configure

```bash
git clone git@github.com:pcitservice/a1-sms-gateway.git /opt/a1-sms-gateway
cd /opt/a1-sms-gateway
cp .env.example .env
```

Edit `.env` — the required values:

```env
APP_NAME="A1TechFlow SMS"
APP_ENV=production
APP_KEY=                         # php artisan key:generate --show
APP_URL=https://sms.yourbrand.com
APP_TIMEZONE=UTC                 # keep UTC; frontend renders local time
APP_DEBUG=false

# Postgres — internal, no host publish
POSTGRES_DB=a1sms
POSTGRES_USER=a1sms
POSTGRES_PASSWORD=<generate 32 chars>

# Redis — internal
REDIS_HOST=redis
REDIS_PORT=6379

# RabbitMQ — internal
RABBITMQ_HOST=rabbitmq
RABBITMQ_USER=a1sms
RABBITMQ_PASSWORD=<generate 32 chars>

# MQTT broker (for gateway agents)
MQTT_HOST=mosquitto
MQTT_PORT=1883                   # move to 8883 with TLS before public launch
MQTT_ADMIN_USER=a1sms-broker
MQTT_ADMIN_PASSWORD=<generate 32 chars>
MQTTS_PUBLIC_HOST=sms.yourbrand.com

# Mail — Postfix on host over docker bridge
MAIL_MAILER=smtp
MAIL_URL="smtp://172.19.0.1:25?verify_peer=0&verify_peer_name=0&auto_tls=0"
MAIL_FROM_ADDRESS="no-reply@sms.yourbrand.com"
MAIL_FROM_NAME="${APP_NAME}"

# Stripe (live for prod, test for staging)
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
CASHIER_CURRENCY=DKK

# Sanctum + agent auth
SANCTUM_STATEFUL_DOMAINS=sms.yourbrand.com
AGENT_CONFIG_TOKEN=<generate 32 chars>

# Trial config
TRIAL_SMS_LIMIT=25
TRIAL_DAYS=14

# Frontend build-time
NEXT_PUBLIC_APP_NAME="A1TechFlow SMS"
```

## 2. Bring up the stack

```bash
docker compose -p a1-sms up -d
docker compose -p a1-sms exec api php artisan migrate --force
docker compose -p a1-sms exec api php artisan db:seed --class=PlansSeeder --force
```

You should see 12 containers healthy after ~60 s:

```bash
docker compose -p a1-sms ps
# api, web, worker (×2), horizon, scheduler, mqtt_listener,
# nginx, mosquitto, postgres, redis, rabbitmq
```

Smoke test:

```bash
curl -s http://127.0.0.1:8181/api/v1/health | jq
# { "status": "ok", "db": "ok", "redis": "ok", "time": "...", "version": "1.0.0" }
```

## 3. Front with CyberPanel / OpenLiteSpeed

If the host runs CyberPanel (recommended — handles Let's Encrypt for you),
create a vhost for `sms.yourbrand.com` and paste this into its **rewrite
rules** or vhost.conf `extprocessor + context /`:

```
extprocessor a1smsApp {
  type                    proxy
  address                 127.0.0.1:8181
  maxConns                200
  pcKeepAliveTimeout      60
  initTimeout             60
  respBuffer              0
}

context / {
  type                    proxy
  handler                 a1smsApp
  addDefaultCharset       off
}
```

Then issue the cert:

```bash
certbot --apache -d sms.yourbrand.com
```

If you're not on CyberPanel, terminate TLS with Caddy or Nginx and proxy
`127.0.0.1:8181` in the same way.

## 4. Wire the modem

Follow `deploy/trb140/README.md`:

1. WireGuard tunnel from VPS (`10.99.0.1`) → TRB (`10.99.0.2`).
2. Install `python3` + `paho-mqtt` on the TRB via `opkg`.
3. Copy `gateway_agent.py` + `config.json` (broker URL + AGENT_CONFIG_TOKEN)
   to `/root/a1sms/`.
4. Register the systemd/procd service so it survives reboots.
5. Admin → Gateways → create a `trb140-mqtt` row with the device_id and
   set `team_id = NULL` for platform-pool routing.

Verify:

```bash
docker compose -p a1-sms logs -f mqtt_listener | grep heartbeat
```

You should see `[LISTENER] heartbeat recv on sms/heartbeat/…` within 30 s.

## 5. Wire Postfix mail (host side)

The stack talks to Postfix over the docker bridge (`172.19.0.1:25`). Two
tweaks on the host:

```bash
# Trust the SMS docker subnet
postconf -e 'mynetworks = 127.0.0.0/8 [::1]/128 172.19.0.0/16'
systemctl reload postfix

# Confirm Postfix listens on all interfaces
ss -tlnp | grep :25
```

Test:

```bash
docker compose -p a1-sms exec api php -r '
  require "/var/www/html/vendor/autoload.php";
  $app = require "/var/www/html/bootstrap/app.php";
  $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  Illuminate\Support\Facades\Mail::raw("hello", fn($m) => $m->to("you@example.com")->subject("smtp test"));
  echo "sent\n";
'
```

DKIM: sign for `sms.yourbrand.com` with OpenDKIM on the host — deliverability
is otherwise poor.

## 6. Wire Stripe

- Add a **webhook endpoint** in the Stripe dashboard:
  - URL: `https://sms.yourbrand.com/api/v1/webhooks/stripe`
  - Events: `checkout.session.completed`, `invoice.paid`,
    `customer.subscription.deleted`
  - Copy the signing secret into `STRIPE_WEBHOOK_SECRET`.
- Cashier auto-creates Stripe customers per team on first checkout.
- Test-mode top-up: hit `POST /api/v1/billing/checkout` from the dashboard.

## 7. Verify the whole loop

```bash
# Signup + verify (mail lands in your inbox because DKIM is set)
curl -sS -X POST https://sms.yourbrand.com/api/v1/auth/signup \
  -H 'Content-Type: application/json' \
  -d '{"name":"Ops Test","email":"ops@you.com","password":"strong-pass-9","country":"DK"}'

# Log in with the verified user, POST /send-sms to a real number, then
# check the recipient's phone. delivered_at populates within seconds.
```

## Operations

### Zero-downtime redeploy

```bash
cd /opt/a1-sms-gateway
git pull --ff-only
docker compose -p a1-sms build api web
docker compose -p a1-sms up -d --force-recreate api web
docker compose -p a1-sms exec api php artisan migrate --force
docker exec a1-sms-nginx-1 nginx -s reload
```

The mqtt_listener, scheduler and workers only need force-recreate when
you change their code or env.

### Backups

Postgres nightly dump:

```cron
0 3 * * * docker compose -p a1-sms exec -T postgres pg_dump -U a1sms a1sms | gzip > /var/backups/a1sms-$(date +\%F).sql.gz
```

RabbitMQ + Redis are ephemeral (jobs are re-tried). Volume snapshots via
your hosting provider cover disaster recovery.

### Monitoring

Health checks:
- `GET /api/v1/health` — JSON envelope (`{status, db, redis, time, version}`),
  200 if healthy, 503 if a probe fails
- `GET /up` — Laravel's built-in lightweight probe (200 = process alive)
- Admin `/admin` — live gateway/queue/failed-job counters, auto-refreshes

Horizon UI (queue depth, throughput, retries):

```
https://sms.yourbrand.com/horizon
```

Restrict with basic auth or your admin middleware.

### Common runbook

| Symptom | Likely cause | Fix |
|---|---|---|
| 502 on redeploy | nginx cached the old api IP after `--force-recreate` | `docker exec a1-sms-nginx-1 nginx -s reload` |
| SMS says "sent" but nothing arrives | Message routed to the mock gateway | `UPDATE gateways SET status='offline' WHERE kind='mock';` and set `team_id=NULL` on your real gateway |
| Schedule fires 2 hours off | `APP_TIMEZONE ≠ UTC` or Postgres in `Europe/Copenhagen` | Set `APP_TIMEZONE=UTC` in `.env` and restart api + listener + scheduler |
| Verification email 500s | `MAIL_HOST=smtp.example.com` from example env | Set `MAIL_URL` to Postfix on the bridge (`smtp://172.19.0.1:25?verify_peer=0&auto_tls=0`) |
| Stripe checkout 400 "customer/customer_email" | Cashier auto-attaches the customer | Remove `customer_email` from the session options |

## Security checklist before opening to public signup

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] MQTT broker moved from plain 1883 to TLS 8883, with proper certs
- [ ] Sanctum tokens rate-limited per key (add per-`token_id` throttle
      on `POST /send-sms` — see `app/Http/Kernel.php`)
- [ ] Turnstile / hCaptcha wired on `POST /auth/signup`
      (`TURNSTILE_SECRET` env)
- [ ] Postfix DKIM signing for the sending domain
- [ ] Stripe webhook secret matches
- [ ] All admin routes require `is_admin` middleware
- [ ] `/horizon` and `/telescope` require auth
- [ ] Postgres + Redis + RabbitMQ have no host-published ports
- [ ] Backups verified restorable

## Architecture at a glance

```
CyberPanel/OLS  ─┐
(TLS 443)        │
                 ▼
          nginx:8181  ─────────► web (Next.js) ── / (SPA + /docs)
                     └─────────► api (Laravel) ── /api/v1/*
                                        │
                                        ├── postgres (data)
                                        ├── redis    (cache, rate limit, sessions)
                                        ├── rabbitmq (sms.outbound, webhooks queues)
                                        └── mosquitto (MQTT broker)

  workers ────────────────── consume sms.outbound → publish MQTT sms/outbound/<device>
  horizon ────────────────── job dashboards
  scheduler ──────────────── minutely artisan schedule:run
    ├── sms:flush-scheduled  → future-dated sends
    ├── a1:gateway:poll      → poll TRB for inbound (fallback path)
    └── a1:gateway:watchdog  → mark gateways online/offline
  mqtt_listener ──────────── subscribes to sms/status/#, sms/inbound/#, sms/heartbeat/#

  Postfix (host) ─────── outbound mail
  TRB140 (10.99.0.2) ─── MQTT-pull agent talks to mosquitto:1883
```

## Where to find things

- API surface: `api/routes/api.php`, `api/routes/admin.php`
- Domain code: `api/app/Domain/{Sms,Gateway,Billing,Webhooks}/`
- Dispatcher: `api/app/Domain/Sms/Services/SmsDispatcher.php`
- Frontend: `web/app/**` — dashboard, admin, public /join, /docs
- Public API docs: served from `web/public/openapi.yaml` by Scalar at `/docs`
- Migrations: `api/database/migrations/`
- Deploy scripts: `deploy/`
