# BYOD onboarding — Customer brings their own TRB140

This is the flow for **customers who buy their own Teltonika TRB140** (or
compatible RutOS device), plug in a SIM, and wire it into the platform.
No LAN reachability required — the device connects **outbound** to the
platform's MQTT broker, so it works behind carrier CGNAT, hotel WiFi,
mobile hotspots, whatever.

## What the customer needs

1. A Teltonika TRB140 (or TRB145/RUT240/RUT200) running RutOS 7.06 or newer.
2. An active SIM inserted, PIN entered if required, cellular data connected.
3. SSH access to the device (default: `root@192.168.1.1` on its LAN).

## What the operator (you) does — 3 minutes

1. **Admin → Gateways → Add Gateway.** Fill in:
   - **Kind:** `trb140-mqtt`
   - **Name:** e.g. `Acme-Copenhagen-01`
   - **Team:** the customer's workspace, or leave blank for platform pool
   - **Device ID:** leave blank (auto-generated) or paste the TRB140 serial
   - **Modem ID:** `2-1` (single-modem) — check `Status → Network → Mobile` on the device
   - **Rate per minute:** 6 (safe default for consumer SIMs)
2. Click **Save**. The row appears with status **offline**.
3. Click the row → **"Show agent config"**. You get:
   - A **one-line install command** to send the customer
   - The command contains a **30-minute signed URL** to the config bundle
   - After 30 min the URL expires; regenerate if needed
4. Send that command to the customer over your usual channel (email, ticket,
   Slack). Nothing sensitive to shell-escape — it's a URL, that's it.

Example command:

```sh
curl -fsSL https://sms.a1techflow.com/agent/install-agent.sh \
  | A1SMS_CONFIG_URL='https://sms.a1techflow.com/api/v1/agent/config/17?expires=...&signature=...' sh
```

## What the customer does — 5 minutes

1. **SSH into the TRB140:**
   ```sh
   ssh root@192.168.1.1
   ```
2. Paste and run the one-line command from above.
3. The installer prints progress:
   ```
   [a1sms] installing to /opt/a1sms
   [a1sms] ensuring python3 + paho-mqtt
   [a1sms] fetching agent script
   [a1sms] fetching device config
   [a1sms] installing init.d service
   [a1sms] ✓ agent is running. Watch: tail -f /var/log/a1sms-agent.log
   ```

That's it. The agent starts, opens a persistent MQTT connection to
`sms.a1techflow.com:8883`, and starts sending heartbeats.

Within 60 seconds you'll see the row flip to **online** in the admin panel,
with signal strength + operator + SIM status populated.

## Optional: incoming SMS via HTTP webhook

Outgoing SMS work over MQTT out of the box. To receive incoming SMS at
the platform, either:

**A.** Let the agent poll the SIM inbox (default — polls `gsmctl -L` every 5 s
and publishes to `sms/inbound/<device_id>`). Simple, ~5 s latency.

**B.** Configure RutOS to POST to the platform HTTP webhook (near-instant):
   1. RutOS UI → **Services → SMS Utilities → SMS Management → Forwarding to HTTP**
   2. Enable, method `POST`, URL:
      ```
      https://sms.a1techflow.com/api/v1/webhooks/trb140
      ```
   3. Payload (JSON):
      ```
      { "gateway_id": <your gateway id>, "id": "%receivedSmsMsgId%",
        "from": "%sender%", "text": "%text%", "date": "%date%" }
      ```

Both work; you can enable both if you want redundancy — the ingest job
deduplicates by (gateway_id, provider_id).

## Troubleshooting

| Symptom | Fix |
|---|---|
| `paho-mqtt install may have failed` in installer output | On some RutOS builds, `opkg install python3-paho-mqtt` isn't in the base repo. Enter the device, install it manually: `opkg update && opkg install python3-paho-mqtt`. Re-run installer. |
| Row stays **offline** for >2 min | `ssh root@<device>` → `logread | grep a1sms` and `tail -50 /var/log/a1sms-agent.log`. Common causes: SIM PIN not entered, no cellular data, firewall blocking outbound 8883. |
| SMS "queued" forever | Worker or listener died. On the VPS: `docker compose -p a1-sms logs worker mqtt_listener --tail 50`. |
| Duplicate incoming SMS | RutOS forwarder + agent polling both firing. Pick one. |
| Device reboots, doesn't come back | Check init.d unit is enabled: `/etc/init.d/a1sms-agent enabled ; echo $?` should print `0`. |

## Uninstalling

On the device:

```sh
/etc/init.d/a1sms-agent stop
/etc/init.d/a1sms-agent disable
rm -rf /opt/a1sms /etc/init.d/a1sms-agent /var/log/a1sms-agent.log
```

Then in the admin panel, delete the Gateway row (or reassign to another
team if the device is being resold).
