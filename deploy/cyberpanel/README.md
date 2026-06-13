# Deploying behind CyberPanel / OpenLiteSpeed

When this VPS already runs CyberPanel (OpenLiteSpeed on 80/443), our stack
must NOT take those ports. The installer detects CyberPanel and does:

- skips its own host Nginx vhost
- skips certbot (CyberPanel manages Let's Encrypt)
- binds the in-stack nginx to `127.0.0.1:8181` / `127.0.0.1:8443` only

You then point CyberPanel's OLS vhost for `sms.a1techflow.com` at the Docker
stack via two reverse-proxy rules. Two files in this folder give you the
exact config to paste.

## Steps

1. **Create the website in CyberPanel** (you've done this):
   - Websites → Create Website → Domain `sms.a1techflow.com`, package
     Default, email any, PHP 8.3.
   - Click **Issue SSL** on the site card to get a Let's Encrypt cert.

2. **Add the reverse-proxy rules** to OLS:
   - In CyberPanel: **Websites → List Websites → sms.a1techflow.com → Manage**
   - Scroll to **Rewrite Rules** → click **Select Rewrite Rules → "Custom"**
   - Paste the contents of [`rewrite.conf`](./rewrite.conf) and save.

3. **Add an external app** so OLS knows where to send the traffic:
   - In CyberPanel: **Websites → List Websites → sms.a1techflow.com → vHost
     Conf** (advanced edit).
   - Paste the `extprocessor` and `context` blocks from
     [`vhost.conf`](./vhost.conf) into the vHost configuration and save.
   - Click **Restart OLS** at the top to reload.

4. **Run the installer** on the VPS (as root):
   ```bash
   curl -fsSL https://raw.githubusercontent.com/pcitservice/a1-sms-gateway/main/install.sh | bash
   ```
   The installer will:
   - skip nginx/certbot (because it sees CyberPanel)
   - bring up the Docker stack on `127.0.0.1:8181`
   - migrate + seed + provision RabbitMQ user

5. **Verify**:
   ```bash
   curl -fsS https://sms.a1techflow.com/api/v1/health
   ```
   should print `{"status":"ok",...}`.

## Why this shape

| Layer         | Listens on            | Talks to                |
| ------------- | --------------------- | ----------------------- |
| OpenLiteSpeed | 0.0.0.0:80,443        | 127.0.0.1:8181 (HTTP)   |
| In-stack nginx| 127.0.0.1:8181        | api:8000, web:3000      |
| api / web     | container network     | postgres, redis, rabbit |

OLS terminates TLS with CyberPanel's Let's Encrypt cert and forwards the
already-decrypted request as plain HTTP over loopback to our nginx
container, which dispatches to api or web based on path.

## Troubleshooting

- **502 Bad Gateway from sms.a1techflow.com** — OLS can't reach `127.0.0.1:8181`.
  Confirm the Docker stack is up with `docker compose -p a1-sms ps`.
- **OLS shows the CyberPanel welcome page** — the vhost is serving its
  default docroot, your rewrite/proxy rules didn't take effect. Recheck
  step 2/3 above and restart OLS.
- **Mixed-content warnings in the browser** — make sure `APP_URL` in
  `/opt/a1-sms-gateway/.env` is `https://sms.a1techflow.com` (with https),
  then `docker compose -p a1-sms restart api web`.
