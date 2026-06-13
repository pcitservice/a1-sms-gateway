# A1 SMS Gateway

Commercial-grade SMS Gateway SaaS — send and receive SMS through Teltonika TRB140 (and other) LTE gateways, with full multi-tenant billing, automation, webhooks, and a customer + admin UI.

- **Domain:** sms.a1techflow.com
- **Server:** 2.58.82.166 (Ubuntu 24.04)
- **Backend:** Laravel 12 (PHP 8.3) + Horizon + Sanctum + Cashier
- **Frontend:** Next.js 15 (App Router, TypeScript, Tailwind)
- **Data:** PostgreSQL 16 · Redis 7 · RabbitMQ 3
- **Hardware:** Teltonika TRB140 via RUTOS HTTP API, with SSH/AT-command fallbacks
- **Deploy:** Docker Compose behind Nginx + Let's Encrypt

> This project lives in its own Docker network and uses a dedicated port range so it can coexist with other projects on the same host without conflict.

## One-command install (on the VPS)

```bash
curl -fsSL https://install.sms.a1techflow.com/install.sh | bash
```

The installer:

1. Installs system deps (curl, git, jq, ufw, fail2ban, certbot, Docker, Compose v2).
2. Clones this repo to `/opt/a1-sms-gateway` (or pulls if it already exists).
3. Generates `.env` from `.env.example` with cryptographically random secrets.
4. Provisions the Nginx vhost for `sms.a1techflow.com` and obtains a Let's Encrypt cert.
5. Brings up the `a1-sms` Docker stack on its own bridge network.
6. Runs migrations + seeds the plans + creates the first super-admin.
7. Installs daily Postgres backups, log rotation, fail2ban, UFW.

The script is idempotent — re-running it upgrades to the current main.

## Local development

```bash
cp .env.example .env
docker compose up -d
docker compose exec api php artisan migrate --seed
docker compose exec api php artisan a1:create-admin admin@example.com
```

Open:

- App: http://localhost:3100
- API: http://localhost:8100/api/v1
- API docs (Swagger): http://localhost:8100/api/documentation
- RabbitMQ UI: http://localhost:15772 (guest/guest)
- Horizon: http://localhost:8100/horizon

## Architecture

```
                   ┌────────────────┐
  Customers ─────► │  Nginx + SSL   │ ─────► Next.js (web)
                   │  sms.a1tech…   │ ─────► Laravel (api)
                   └────────────────┘
                          │
                          ▼
        ┌──────────┬──────────┬────────────┐
        │ Postgres │  Redis   │  RabbitMQ  │
        └──────────┴──────────┴────────────┘
                          │
                          ▼
                ┌─────────────────────┐
                │  Gateway Manager    │
                │  ├─ Trb140Driver    │  RUTOS HTTP / SSH / AT
                │  ├─ HuaweiDriver    │
                │  └─ MockDriver      │
                └─────────────────────┘
                          │
                          ▼
                  Teltonika TRB140
                  (LTE + SIM)
```

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), [docs/TRB140.md](docs/TRB140.md), [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md), [docs/API.md](docs/API.md).

## Ports (host-side)

| Service        | Host port | Notes                              |
| -------------- | --------- | ---------------------------------- |
| Nginx HTTP     | 8181      | redirect to HTTPS in prod          |
| Nginx HTTPS    | 8443      | terminated by Let's Encrypt        |
| Next.js (web)  | 3100      | dev only; behind Nginx in prod     |
| Laravel (api)  | 8100      | dev only; behind Nginx in prod     |
| Postgres       | 5532      | bound to 127.0.0.1                 |
| Redis          | 6479      | bound to 127.0.0.1                 |
| RabbitMQ AMQP  | 5772      | bound to 127.0.0.1                 |
| RabbitMQ UI    | 15772     | bound to 127.0.0.1                 |

These were chosen to avoid colliding with the existing `a1techflow` project on the same host.

## License

Proprietary — © A1 Tech Flow. All rights reserved.
