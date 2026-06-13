# A1 SMS Gateway — REST API

Base URL: `https://sms.a1techflow.com/api/v1`

All requests authenticate with a Bearer token:

```http
Authorization: Bearer a1sk_live_…
```

Tokens are created in **Dashboard → API Keys**. Each token belongs to a
team and inherits that team's rate-limit and balance.

Full interactive docs (Swagger UI): <https://sms.a1techflow.com/api/documentation>.

## Conventions

- All bodies are JSON.
- Times are RFC 3339 (`2026-06-11T18:00:00Z`).
- Phone numbers are E.164 (`+4512345678`). Two-letter `country_hint` may
  be passed to normalise local numbers.
- Errors follow [RFC 7807](https://www.rfc-editor.org/rfc/rfc7807):

  ```json
  {
    "type": "https://sms.a1techflow.com/errors/insufficient-balance",
    "title": "Insufficient balance",
    "status": 402,
    "detail": "Sending 1 SMS requires 0.18 DKK; balance is 0.04 DKK."
  }
  ```

- Standard rate-limit headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`,
  `Retry-After`.

## Send a single SMS

`POST /send-sms`

```json
{
  "to": "+4512345678",
  "message": "Hello {{name}}",
  "variables": { "name": "Anna" },
  "from": "A1SMS",
  "gateway_id": null,
  "callback_url": "https://example.com/sms/delivery"
}
```

Response `202 Accepted`:

```json
{
  "id": "msg_01HW…",
  "status": "queued",
  "estimated_cost": "0.18",
  "currency": "DKK"
}
```

## Send bulk

`POST /send-bulk`

```json
{
  "messages": [
    { "to": "+4512345678", "message": "Hej" },
    { "to": "+4587654321", "message": "Hej" }
  ],
  "batch_label": "june-newsletter"
}
```

Returns the same shape per message plus a `batch_id`.

## Contacts

| Method | Path                       | Notes                          |
| ------ | -------------------------- | ------------------------------ |
| GET    | `/contacts`                | paginated, `?q=` for search    |
| POST   | `/contacts`                | single or array                |
| GET    | `/contacts/{id}`           |                                |
| PATCH  | `/contacts/{id}`           |                                |
| DELETE | `/contacts/{id}`           | soft-delete                    |
| POST   | `/contacts/import`         | multipart `file=…csv|xlsx`     |
| GET    | `/contacts/export`         | csv stream                     |
| GET    | `/groups`                  |                                |
| POST   | `/groups`                  |                                |
| POST   | `/groups/{id}/contacts`    | attach array of contact IDs    |

## Messages

| Method | Path                             | Notes                                |
| ------ | -------------------------------- | ------------------------------------ |
| GET    | `/messages`                      | filter by `?status=`, `?direction=`  |
| GET    | `/messages/{id}`                 |                                      |
| GET    | `/messages/{id}/events`          | full status timeline                 |

## Reports

| Method | Path                       | Notes                              |
| ------ | -------------------------- | ---------------------------------- |
| GET    | `/reports/usage`           | `?from=&to=&granularity=day|week`  |
| GET    | `/reports/delivery`        | per-gateway delivery rates         |
| GET    | `/reports/revenue`         | admin only                         |

## Webhooks (subscription mgmt)

| Method | Path                | Notes                       |
| ------ | ------------------- | --------------------------- |
| GET    | `/webhooks`         |                             |
| POST   | `/webhooks`         | `{ url, events:[…], secret }` |
| DELETE | `/webhooks/{id}`    |                             |

Outbound events are signed:

```
X-A1Sms-Signature: t=1717948800,v1=8f9c…
```

`v1` is `HMAC_SHA256(secret, "{t}.{raw_body}")`. Verify before trusting
the payload.

Event types:

- `message.queued`
- `message.sent`
- `message.delivered`
- `message.failed`
- `message.received`
- `gateway.offline`
- `gateway.online`
- `balance.low`

## Health

`GET /health` → `{ "status": "ok", "db": "ok", "redis": "ok", "queue":
"ok", "time": "…" }`. No auth required.
