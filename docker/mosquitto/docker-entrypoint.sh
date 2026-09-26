#!/bin/sh
# Bootstrap the admin user on first boot if the passwd file is empty or missing.
set -e

PASSWD=/mosquitto/config/passwd
ADMIN_USER="${MQTT_ADMIN_USER:-a1sms-broker}"
ADMIN_PASS="${MQTT_ADMIN_PASSWORD:-change-me}"

if [ ! -s "$PASSWD" ]; then
    echo "[a1-sms-mosquitto] Seeding passwd file with admin user '$ADMIN_USER'"
    touch "$PASSWD"
    chmod 600 "$PASSWD"
    mosquitto_passwd -b "$PASSWD" "$ADMIN_USER" "$ADMIN_PASS"
fi

# mosquitto drops to uid 1883 after start — file must be readable by that uid.
chown 1883:1883 "$PASSWD" 2>/dev/null || true
chown 1883:1883 /mosquitto/config/aclfile 2>/dev/null || true

exec "$@"
