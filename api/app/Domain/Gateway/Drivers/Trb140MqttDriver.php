<?php

namespace App\Domain\Gateway\Drivers;

use App\Domain\Gateway\Contracts\SmsGateway;
use App\Domain\Gateway\DTO\GatewayHealth;
use App\Domain\Gateway\DTO\IncomingMessage;
use App\Domain\Gateway\DTO\MessageStatus;
use App\Domain\Gateway\DTO\OutgoingMessage;
use App\Domain\Gateway\DTO\SendResult;
use App\Domain\Gateway\Exceptions\GatewayException;
use App\Models\Gateway;
use Illuminate\Contracts\Cache\Repository as Cache;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * TRB140 driver for CGNAT-fronted / internet-deployed devices.
 *
 * The physical device runs `gateway_agent.py` (see deploy/trb140/), which
 * opens a persistent outbound MQTT connection to the platform's broker and
 * subscribes to its own topic. This driver publishes send-jobs to that topic
 * and returns immediately; the agent posts back status on `sms/status/#`
 * which the `a1:mqtt:listen` command consumes to update the message row.
 *
 * Topic layout, per device:
 *   sms/outbound/{device_id}     ← platform → device (publish)
 *   sms/status/{device_id}       ← device → platform (subscribe elsewhere)
 *   sms/inbound/{device_id}      ← device → platform (subscribe elsewhere)
 *   sms/heartbeat/{device_id}    ← device → platform (subscribe elsewhere)
 */
class Trb140MqttDriver implements SmsGateway
{
    private const CONNECT_TIMEOUT = 5;
    private const QOS_AT_LEAST_ONCE = 1;

    public function __construct(
        protected Gateway         $row,
        protected Cache           $cache,
        protected LoggerInterface $logger,
    ) {}

    public function id(): int      { return $this->row->id; }
    public function kind(): string { return 'trb140-mqtt'; }

    public function send(OutgoingMessage $message): SendResult
    {
        if (! $this->row->device_id) {
            return SendResult::failure('unprovisioned', 'Gateway has no device_id — agent not registered.');
        }
        try {
            $client = $this->publisher();
            $topic  = "sms/outbound/{$this->row->device_id}";
            $client->publish($topic, json_encode([
                'sms_id'  => $message->id,
                'to'      => $message->to,
                'message' => $message->body,
                'from'    => $message->from,
                'modem'   => $message->modemId ?? $this->row->modem_id ?? '2-1',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), self::QOS_AT_LEAST_ONCE);
            $client->disconnect();

            // We treat MQTT-accepted as "sent" — the listener command will
            // upgrade this row's status when the agent posts to sms/status.
            return SendResult::success(
                providerId: 'mqtt-'.$message->id,
                segments:   $this->countSegments($message->body),
                raw:        ['topic' => $topic],
            );
        } catch (Throwable $e) {
            $this->logger->error('trb140-mqtt.send failed', [
                'gateway_id' => $this->row->id,
                'error'      => $e->getMessage(),
            ]);
            // Transport failure — SendSmsJob will retry with exponential backoff.
            return SendResult::failure('transport', $e->getMessage());
        }
    }

    public function pollIncoming(): iterable
    {
        // Incoming SMS come in via HTTP webhook (RutOS "Forward Received SMS
        // to HTTP" pointed at /api/v1/webhooks/trb140) OR via the listener
        // command subscribing to `sms/inbound/#`. Either way, this driver
        // has no polling role.
        return;
        yield; // Make PHP happy about the return-type-hint on an iterable.
    }

    public function status(string $providerId): MessageStatus
    {
        // The listener command writes the last-known status of a message id
        // into cache under `mqtt:msg:{providerId}`.
        $state = $this->cache->get("mqtt:msg:{$providerId}", 'sent');
        return new MessageStatus($providerId, $state);
    }

    public function health(): GatewayHealth
    {
        // Agent heartbeat lands in cache from the listener command; look it up.
        $key    = "mqtt:hb:{$this->row->device_id}";
        $hb     = $this->cache->get($key);
        $reachable = is_array($hb)
            && isset($hb['received_at'])
            && (time() - (int) $hb['received_at']) < 90;

        return new GatewayHealth(
            reachable:       $reachable,
            connectionState: $reachable ? 'connected' : 'disconnected',
            signalRssi:      isset($hb['signal_rssi']) ? (int) $hb['signal_rssi'] : null,
            signalRsrp:      isset($hb['signal_rsrp']) ? (int) $hb['signal_rsrp'] : null,
            operator:        $hb['operator']    ?? null,
            lteBand:         $hb['lte_band']    ?? null,
            simStatus:       $hb['sim_status']  ?? null,
            imei:            $hb['imei']        ?? null,
            uptimeSeconds:   isset($hb['uptime_seconds']) ? (int) $hb['uptime_seconds'] : null,
            raw:             $hb ?: [],
        );
    }

    public function reboot(): void
    {
        // Publish a control message; the agent listens on sms/control/# and
        // shells out to `reboot` if it sees a "reboot" command.
        if (! $this->row->device_id) {
            throw new GatewayException('Gateway has no device_id.');
        }
        try {
            $client = $this->publisher();
            $client->publish(
                "sms/control/{$this->row->device_id}",
                json_encode(['action' => 'reboot']),
                self::QOS_AT_LEAST_ONCE,
            );
            $client->disconnect();
        } catch (Throwable $e) {
            throw new GatewayException('MQTT reboot publish failed: '.$e->getMessage());
        }
    }

    public function configure(array $config): void
    {
        if (! $this->row->device_id) {
            throw new GatewayException('Gateway has no device_id.');
        }
        try {
            $client = $this->publisher();
            $client->publish(
                "sms/control/{$this->row->device_id}",
                json_encode(['action' => 'configure', 'config' => $config]),
                self::QOS_AT_LEAST_ONCE,
            );
            $client->disconnect();
        } catch (Throwable $e) {
            throw new GatewayException('MQTT configure publish failed: '.$e->getMessage());
        }
    }

    // ------------------------------------------------------------ internals --

    private function publisher(): MqttClient
    {
        $host = config('sms.mqtt.host');
        $port = (int) config('sms.mqtt.port');
        $user = config('sms.mqtt.admin_user');
        $pass = config('sms.mqtt.admin_password');

        $client   = new MqttClient($host, $port, 'a1sms-publisher-'.bin2hex(random_bytes(4)));
        $settings = (new ConnectionSettings)
            ->setUsername($user)
            ->setPassword($pass)
            ->setConnectTimeout(self::CONNECT_TIMEOUT)
            ->setKeepAliveInterval(30);
        $client->connect($settings, true);
        return $client;
    }

    private function countSegments(string $body): int
    {
        $isUnicode = preg_match('//u', $body) && (mb_strlen($body) !== strlen($body));
        $singleCap = $isUnicode ? 70 : 160;
        $multiCap  = $isUnicode ? 67 : 153;
        $len = mb_strlen($body);
        return $len <= $singleCap ? 1 : (int) ceil($len / $multiCap);
    }
}
