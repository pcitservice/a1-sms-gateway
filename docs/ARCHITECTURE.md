# Architecture

## High level

```
┌──────────────┐   HTTPS    ┌─────────────┐   HTTP   ┌──────────────┐
│  Browser /   ├───────────►│   Nginx     ├─────────►│  Next.js 15  │
│  Mobile UA   │            │  (SSL)      │          │  (App Router)│
└──────────────┘            └──────┬──────┘          └──────┬───────┘
                                   │ /api                    │ SSR fetch
                                   ▼                         ▼
                          ┌────────────────┐         ┌──────────────┐
                          │  Laravel 12    │◄────────┤  Next.js BFF │
                          │  (Octane/FPM)  │         └──────────────┘
                          └──────┬─────────┘
              ┌──────────────────┼─────────────────┐
              ▼                  ▼                 ▼
        ┌──────────┐       ┌──────────┐       ┌──────────┐
        │ Postgres │       │  Redis   │       │ RabbitMQ │
        └──────────┘       └────┬─────┘       └────┬─────┘
                                ▼                  ▼
                         ┌─────────────┐   ┌──────────────────┐
                         │  Horizon    │   │  Queue Workers   │
                         │ (in-process │   │  (sms, webhooks, │
                         │   redis)    │   │   automation)    │
                         └─────────────┘   └────────┬─────────┘
                                                    ▼
                                       ┌──────────────────────┐
                                       │  Gateway Manager     │
                                       │  ├─ Trb140Driver     │
                                       │  ├─ HuaweiDriver     │
                                       │  └─ MockDriver       │
                                       └──────────┬───────────┘
                                                  ▼
                                       Teltonika TRB140 (LTE)
```

## Bounded contexts (Laravel)

- `App\Domain\Gateway` — abstraction + drivers. Pure: knows nothing about users or billing.
- `App\Domain\Sms` — messages, queues, jobs, delivery tracking.
- `App\Domain\Contacts` — contacts, groups, tags, import/export.
- `App\Domain\Campaigns` — campaigns, schedules, templates.
- `App\Domain\Billing` — plans, subscriptions, invoices, overages, Stripe.
- `App\Domain\Webhooks` — outbound delivery with HMAC signing + retry.
- `App\Domain\Automation` — trigger/action engine (incoming SMS → reply, keyword, etc.).
- `App\Domain\Audit` — immutable audit log of admin and security-relevant actions.

Each context has its own `Models/`, `Services/`, `Jobs/`, `Events/`, `Listeners/` namespaces. HTTP controllers in `App\Http\Controllers\Api\V1\…` and `App\Http\Controllers\Admin\…` are thin — they call services.

## Multi-tenancy

A `team_id` foreign key on every customer-owned table. Default team is created per signup. The `EnsureTeamContext` middleware binds `app('current_team')` so queries can scope automatically via the `BelongsToCurrentTeam` global scope on each tenant model.

## Gateway abstraction

`App\Domain\Gateway\Contracts\SmsGateway`:

```php
interface SmsGateway {
    public function send(OutgoingMessage $message): SendResult;
    public function pollIncoming(): iterable;            // yields IncomingMessage
    public function status(string $providerId): MessageStatus;
    public function health(): GatewayHealth;             // signal, sim, lte, uptime
    public function reboot(): void;
    public function configure(array $config): void;
}
```

Drivers:

- `Trb140Driver` — Teltonika RUTOS HTTP API (`/api/login` → JWT, `/api/messages/actions/send`, `/api/messages/inbox`, `/api/system/info`, `/api/modem/status`). SSH/AT-command fallback for primitives RUTOS doesn't expose.
- `HuaweiDriver` — Hilink HTTP API stub (E3372, B525). Implements the same contract.
- `MockDriver` — in-memory, useful for dev/CI; persists to redis so multi-worker test runs see consistent state.

`GatewayManager` resolves drivers by `gateway.kind` column and caches authenticated clients in Redis.

## SMS pipeline

```
POST /api/v1/send-sms
       │
       ▼
  Validate + bill
       │
       ▼
  enqueue SendSmsJob ──► RabbitMQ exchange "sms.outbound"
                              │
                              ▼
                       Worker pulls, picks gateway
                              │
                       ┌──────┴──────┐
                       ▼             ▼
                  send via driver   record attempt
                              │
                       ┌──────┴──────┐
                       ▼             ▼
                   success      transient fail
                       │             │
                       │             └─► retry w/ exponential backoff
                       ▼
                  status=sent, fire MessageSent event
```

Incoming SMS:

```
schedule: every 30s ─► PollIncomingSmsJob per gateway
       │
       ▼
  driver->pollIncoming() yields IncomingMessage
       │
       ▼
  persist, fire MessageReceived event
       │
       ├─► WebhookDispatcher (customer's webhook)
       └─► AutomationEngine (keyword, reply rules)
```

## Billing

Laravel Cashier wraps Stripe. Plans are seeded as DB rows that mirror Stripe Products/Prices. Trials are tracked locally (so the 50-SMS cap is enforceable even before Stripe sees a subscription). Overages are billed via Stripe metered usage records at end of period.

## Security baseline

- HTTPS-only, HSTS, OWASP secure headers via Nginx.
- API auth via Sanctum personal access tokens (header `Authorization: Bearer …`).
- Per-route rate limiting (Sanctum's `throttle:api` + custom `throttle:sms`).
- Webhook payloads are signed with HMAC-SHA256; verify on customer side via `X-A1Sms-Signature`.
- All admin and credential-changing actions are written to `audit_logs`.
- Fail2Ban jail watches Nginx for repeated 401/403/429.
- UFW: only 22 (SSH), 80, 443 open. Internal ports (5532/6479/5772/15772) are bound to 127.0.0.1.
