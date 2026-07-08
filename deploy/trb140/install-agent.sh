#!/bin/sh
# A1 SMS Gateway — TRB140 agent installer.
#
# Run on the TRB140 (RutOS shell), one-line paste from the admin panel:
#
#   curl -fsSL https://sms.a1techflow.com/agent/install-agent.sh \
#     | A1SMS_CONFIG_URL='<signed url>' sh
#
# What it does:
#   1. Installs python3 + paho-mqtt via opkg / pip
#   2. Fetches the agent script + init.d unit from the platform
#   3. Fetches the device's config.json from the signed URL
#   4. Registers /etc/init.d/a1sms-agent and starts it
#
# Idempotent — safe to re-run.

set -e

BASE="${A1SMS_BASE:-https://sms.a1techflow.com}"
CONFIG_URL="${A1SMS_CONFIG_URL:?A1SMS_CONFIG_URL required — grab it from the admin panel}"
DIR=/opt/a1sms

echo "[a1sms] installing to $DIR"
mkdir -p "$DIR"
mkdir -p /var/log
touch /var/log/a1sms-agent.log

echo "[a1sms] ensuring python3 + paho-mqtt are available"
opkg update >/dev/null || true
opkg install python3 python3-light python3-pip ca-certificates >/dev/null 2>&1 || true
if ! python3 -c 'import paho.mqtt.client' 2>/dev/null; then
    python3 -m pip install --break-system-packages paho-mqtt >/dev/null 2>&1 \
      || opkg install python3-paho-mqtt >/dev/null 2>&1 \
      || { echo "[a1sms] ERROR: could not install paho-mqtt"; exit 1; }
fi

echo "[a1sms] fetching agent script"
curl -fsSL "$BASE/agent/gateway_agent.py" -o "$DIR/gateway_agent.py"
chmod +x "$DIR/gateway_agent.py"

echo "[a1sms] fetching device config"
curl -fsSL "$CONFIG_URL" -o "$DIR/config.json"

if [ ! -s "$DIR/config.json" ] || ! head -c 1 "$DIR/config.json" | grep -q '{'; then
    echo "[a1sms] ERROR: config fetch produced non-JSON — the signed URL may have expired"
    echo "        Re-run from the admin panel to get a fresh URL."
    exit 1
fi
chmod 600 "$DIR/config.json"

echo "[a1sms] installing init.d service"
curl -fsSL "$BASE/agent/a1sms-agent.init" -o /etc/init.d/a1sms-agent
chmod +x /etc/init.d/a1sms-agent
/etc/init.d/a1sms-agent enable
/etc/init.d/a1sms-agent restart

sleep 2
if pgrep -f gateway_agent.py >/dev/null; then
    echo "[a1sms] ✓ agent is running. Watch: tail -f /var/log/a1sms-agent.log"
else
    echo "[a1sms] ! agent didn't start. Check /var/log/a1sms-agent.log"
    exit 1
fi
