#!/usr/bin/env python3
"""
A1 SMS Gateway — TRB140 device agent.

Runs on the Teltonika TRB140 (RutOS / OpenWrt-based). Connects outbound to
the platform's MQTT broker, waits for SMS jobs, uses `gsmctl -S -s` to send
via the internal LTE modem, and reports back status + heartbeat.

Install location: /opt/a1sms/gateway_agent.py
Config location:  /opt/a1sms/config.json  (downloaded from the platform)
Log:              /var/log/a1sms-agent.log

Requires: python3, python3-paho-mqtt on RutOS.
Install once: opkg update && opkg install python3 python3-paho-mqtt
"""
from __future__ import annotations

import json
import logging
import os
import platform
import signal
import subprocess
import sys
import time
from datetime import datetime, timezone
from logging.handlers import RotatingFileHandler
from typing import Optional

import paho.mqtt.client as mqtt

AGENT_VERSION = "1.0.0-rutos"
CONFIG_PATH   = os.environ.get("A1SMS_CONFIG", "/opt/a1sms/config.json")
LOG_PATH      = os.environ.get("A1SMS_LOG", "/var/log/a1sms-agent.log")


def _configure_logging() -> None:
    root = logging.getLogger()
    root.setLevel(logging.INFO)
    fmt = logging.Formatter("%(asctime)s %(levelname)s %(name)s: %(message)s")
    fh = RotatingFileHandler(LOG_PATH, maxBytes=1_048_576, backupCount=3)
    fh.setFormatter(fmt)
    root.addHandler(fh)
    sh = logging.StreamHandler(sys.stdout)
    sh.setFormatter(fmt)
    root.addHandler(sh)


log = logging.getLogger("a1sms")


def load_config() -> dict:
    with open(CONFIG_PATH, "r") as f:
        return json.load(f)


def gsmctl(*args: str) -> tuple[str, int]:
    """Run gsmctl with the given args. Returns (stdout, rc)."""
    try:
        r = subprocess.run(
            ["gsmctl", *args],
            capture_output=True, text=True, timeout=15,
        )
        return (r.stdout.strip() + ("\n" + r.stderr.strip() if r.stderr else "")), r.returncode
    except Exception as exc:  # noqa: BLE001
        return f"gsmctl error: {exc}", 1


def send_sms(to_number: str, message: str) -> dict:
    """
    Send via gsmctl -S -s "<number> <message>".
    Returns { status: "success"|"failed", details: "..." }.
    """
    payload = f"{to_number} {message}"
    stdout, rc = gsmctl("-S", "-s", payload)
    if rc == 0 and ("OK" in stdout.upper() or "SENT" in stdout.upper() or stdout == ""):
        return {"status": "success", "details": stdout}
    return {"status": "failed", "error": stdout or f"rc={rc}"}


def collect_health() -> dict:
    """Grab a snapshot of modem/SIM/signal info for the heartbeat."""
    rssi_out, _   = gsmctl("-q")               # signal RSSI dBm
    op_out, _     = gsmctl("-o")               # operator
    imei_out, _   = gsmctl("-i")               # IMEI
    sim_out, _    = gsmctl("-J")               # SIM state
    try:
        uptime = int(float(open("/proc/uptime").read().split()[0]))
    except Exception:
        uptime = None
    return {
        "version":         AGENT_VERSION,
        "connection_state": "connected",
        "signal_rssi":     _try_int(rssi_out),
        "operator":        (op_out or None),
        "imei":            (imei_out or None),
        "sim_status":      (sim_out or None),
        "uptime_seconds":  uptime,
        "reported_at":     datetime.now(timezone.utc).isoformat(),
    }


def _try_int(s: str) -> int | None:
    try:
        return int(s.strip().splitlines()[0])
    except Exception:
        return None


class Agent:
    def __init__(self, cfg: dict) -> None:
        self.cfg      = cfg
        self.device_id = cfg["device"]["id"]
        self.topics    = cfg["topics"]
        self.hb_seconds = int(cfg.get("heartbeat_interval_seconds", 30))

        client_id = f"a1sms-{self.device_id}-{int(time.time())}"
        self.client = mqtt.Client(client_id=client_id, clean_session=True)
        self.client.username_pw_set(cfg["broker"]["username"], cfg["broker"]["password"])
        if cfg["broker"].get("tls", True):
            self.client.tls_set()
        self.client.on_connect  = self._on_connect
        self.client.on_message  = self._on_message
        self.client.on_disconnect = self._on_disconnect

        self._last_hb = 0.0
        self._stopping = False
        # Coexistence knob: when False, this agent does NOT poll the modem
        # inbox. Use this when another process (e.g. an existing a1repairflow
        # /root/sms-bridge/poll.sh cron) already forwards inbound SMS and
        # deletes them — otherwise the two would race.
        self.poll_inbound_enabled = bool(cfg.get("poll_inbound", True))

    # ------------------------------------------------------ MQTT hooks --

    def _on_connect(self, client, userdata, flags, rc):
        if rc != 0:
            log.error("MQTT connect failed rc=%s", rc)
            return
        log.info("connected to broker; subscribing to %s and %s",
                 self.topics["outbound"], self.topics["control"])
        client.subscribe(self.topics["outbound"], qos=1)
        client.subscribe(self.topics["control"],  qos=1)
        # Send a heartbeat immediately so the platform marks us online.
        self._publish_heartbeat()

    def _on_disconnect(self, client, userdata, rc):
        if not self._stopping:
            log.warning("MQTT disconnected rc=%s; paho will reconnect", rc)

    def _on_message(self, client, userdata, msg):
        try:
            body = json.loads(msg.payload.decode("utf-8"))
        except Exception as exc:  # noqa: BLE001
            log.warning("bad JSON on %s: %s", msg.topic, exc)
            return

        if msg.topic == self.topics["outbound"]:
            self._handle_outbound(body)
        elif msg.topic == self.topics["control"]:
            self._handle_control(body)

    # ------------------------------------------------------ handlers --

    def _handle_outbound(self, body: dict) -> None:
        sms_id = body.get("sms_id")
        to_num = body.get("to", "")
        text   = body.get("message", "")

        log.info("sending sms_id=%s to=%s len=%d", sms_id, to_num, len(text))
        result = send_sms(to_num, text)

        response = {
            "sms_id":    sms_id,
            "device_id": self.device_id,
            "status":    result["status"],
            "at":        datetime.now(timezone.utc).isoformat(),
        }
        if "error" in result: response["error"] = result["error"]
        if "details" in result: response["details"] = result["details"]

        self.client.publish(self.topics["status"], json.dumps(response), qos=1)

    def _handle_control(self, body: dict) -> None:
        action = body.get("action")
        log.info("control: %s", action)
        if action == "reboot":
            self.client.disconnect()
            time.sleep(1)
            subprocess.Popen(["reboot"])
        elif action == "configure":
            # Placeholder for on-device config changes (APN, PIN, etc.).
            log.info("configure payload=%s", body.get("config"))

    # ------------------------------------------------------ inbound SMS --

    def _poll_inbound(self) -> None:
        """
        Read new SMS from the SIM and publish. Uses `gsmctl -L` (list all
        SMS), then `gsmctl -D <id>` (delete) after publishing so we don't
        re-emit.
        """
        stdout, rc = gsmctl("-L")
        if rc != 0 or not stdout: return
        for entry in _parse_sms_list(stdout):
            self.client.publish(self.topics["inbound"], json.dumps({
                "id":          entry.get("index"),
                "from":        entry.get("sender"),
                "message":     entry.get("text", ""),
                "received_at": entry.get("date")
                                or datetime.now(timezone.utc).isoformat(),
            }), qos=1)
            if entry.get("index"):
                gsmctl("-D", str(entry["index"]))

    # ------------------------------------------------------ heartbeat --

    def _publish_heartbeat(self) -> None:
        try:
            info = collect_health()
        except Exception as exc:  # noqa: BLE001
            log.warning("heartbeat collect failed: %s", exc)
            info = {"version": AGENT_VERSION}
        self.client.publish(self.topics["heartbeat"], json.dumps(info), qos=1)
        self._last_hb = time.time()

    # ------------------------------------------------------ main loop --

    def run(self) -> None:
        self.client.connect(self.cfg["broker"]["host"], int(self.cfg["broker"]["port"]), keepalive=60)
        self.client.loop_start()

        def _shutdown(signum, frame):
            log.info("signal %s; shutting down", signum)
            self._stopping = True
            self.client.loop_stop()
            self.client.disconnect()
            sys.exit(0)
        signal.signal(signal.SIGTERM, _shutdown)
        signal.signal(signal.SIGINT,  _shutdown)

        while True:
            try:
                if time.time() - self._last_hb >= self.hb_seconds:
                    self._publish_heartbeat()
                if self.poll_inbound_enabled:
                    self._poll_inbound()
            except Exception as exc:  # noqa: BLE001
                log.exception("loop iteration failed: %s", exc)
            time.sleep(5)


def _parse_sms_list(raw: str) -> list[dict]:
    """
    Parse `gsmctl -L` output. Format varies by RutOS version; this handles
    the common blank-line-separated block format:

        Index: 1
        Sender: +4512345678
        Date: 2026-06-15 09:12:03
        Text: Hello world
    """
    entries = []
    cur: dict = {}
    for line in raw.splitlines():
        line = line.rstrip()
        if not line:
            if cur:
                entries.append(cur)
                cur = {}
            continue
        if ":" not in line:
            continue
        k, v = line.split(":", 1)
        k = k.strip().lower()
        v = v.strip()
        if k == "index":     cur["index"] = v
        elif k == "sender":  cur["sender"] = v
        elif k == "date":    cur["date"] = v
        elif k == "text":    cur["text"] = v
    if cur: entries.append(cur)
    return entries


def main() -> None:
    _configure_logging()
    log.info("A1 SMS Gateway agent %s on %s", AGENT_VERSION, platform.platform())
    cfg = load_config()
    Agent(cfg).run()


if __name__ == "__main__":
    main()
