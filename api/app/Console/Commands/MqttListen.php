<?php

namespace App\Console\Commands;

use App\Domain\Sms\Events\MessageDelivered;
use App\Domain\Sms\Events\MessageFailed;
use App\Domain\Sms\Events\MessageReceived;
use App\Domain\Sms\Services\SmsBilling;
use App\Models\Gateway;
use App\Models\SmsMessage;
use App\Models\SmsMessageEvent;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Illuminate\Support\Str;

/**
 * Long-running MQTT consumer for TRB140 agents.
 *
 * Subscribes to three topic patterns:
 *
 *   sms/status/#     — {device_id}/{sms_id}: delivery status from the agent
 *   sms/inbound/#    — {device_id}: an SMS the SIM received
 *   sms/heartbeat/#  — {device_id}: keepalive with signal / SIM / operator
 *
 * Runs as its own compose service (see docker-compose.yml `mqtt_listener`).
 * On exception, exits non-zero — Docker restarts it. Also self-times-out
 * after an hour so long-lived TCP connections get cycled.
 */
class MqttListen extends Command
{
    protected $signature   = 'a1:mqtt:listen {--max-runtime=3600}';
    protected $description = 'Subscribe to the MQTT broker and consume TRB140 agent messages.';

    public function handle(SmsBilling $billing): int
    {
        $host   = (string) config('sms.mqtt.host');
        $port   = (int)    config('sms.mqtt.port');
        $user   = (string) config('sms.mqtt.admin_user');
        $pass   = (string) config('sms.mqtt.admin_password');
        $maxRt  = (int)    $this->option('max-runtime');

        $client   = new MqttClient($host, $port, 'a1sms-listener-'.Str::random(6));
        $settings = (new ConnectionSettings)
            ->setUsername($user)
            ->setPassword($pass)
            ->setConnectTimeout(5)
            ->setKeepAliveInterval(30)
            ->setLastWillTopic('a1sms/listener/status')
            ->setLastWillMessage('offline')
            ->setLastWillQualityOfService(1);

        $client->connect($settings, true);
        $this->info("Connected to {$host}:{$port}");

        $client->subscribe('sms/status/#',    fn ($t, $m) => $this->onStatus($t, $m),    1);
        $client->subscribe('sms/inbound/#',   fn ($t, $m) => $this->onInbound($t, $m),   1);
        $client->subscribe('sms/heartbeat/#', fn ($t, $m) => $this->onHeartbeat($t, $m), 1);

        $started = time();
        $client->registerLoopEventHandler(function (MqttClient $c, float $elapsed) use ($started, $maxRt) {
            if ((time() - $started) > $maxRt) {
                $this->info("max-runtime reached, cycling connection");
                $c->interrupt();
            }
        });

        $client->loop(true);
        $client->disconnect();
        return self::SUCCESS;
    }

    // -------------------------------------------------------- handlers --

    private function onStatus(string $topic, string $payload): void
    {
        // topic: sms/status/{device_id}
        $body = $this->decode($payload);
        if (! $body || empty($body['sms_id'])) return;

        $msg = SmsMessage::query()->withoutGlobalScopes()->find($body['sms_id']);
        if (! $msg) return;

        $newStatus = $body['status'] ?? 'unknown';
        $mapped = match ($newStatus) {
            'success', 'delivered' => 'delivered',
            'failed', 'error'      => 'failed',
            'sent', 'accepted'     => 'sent',
            default                => 'unknown',
        };

        $updates = ['status' => $mapped];
        if ($mapped === 'delivered') $updates['delivered_at'] = now();
        if ($mapped === 'failed') {
            $updates['failed_at']     = now();
            $updates['error_code']    = $body['error_code']   ?? 'device_error';
            $updates['error_message'] = $body['error']         ?? null;
        }
        $msg->forceFill($updates)->save();

        SmsMessageEvent::create([
            'message_id'  => $msg->id,
            'type'        => $mapped,
            'payload'     => $body,
            'occurred_at' => now(),
        ]);

        Cache::put("mqtt:msg:{$msg->provider_id}", $mapped, 3600);

        if ($msg->team) {
            app(SmsBilling::class)->recordDelivery($msg->team, $mapped === 'delivered');
        }

        if ($mapped === 'delivered') event(new MessageDelivered($msg->fresh()));
        if ($mapped === 'failed')    event(new MessageFailed($msg->fresh()));
    }

    private function onInbound(string $topic, string $payload): void
    {
        // topic: sms/inbound/{device_id}
        $body = $this->decode($payload);
        $deviceId = explode('/', $topic)[2] ?? null;
        if (! $body || ! $deviceId) return;

        $gateway = Gateway::query()->where('device_id', $deviceId)->first();
        if (! $gateway) {
            $this->warn("Unknown device_id in inbound: {$deviceId}");
            return;
        }

        // Idempotency: skip if we've already ingested this device's provider ID.
        $providerId = (string) ($body['id'] ?? 'mqtt-'.bin2hex(random_bytes(8)));
        $existing = SmsMessage::query()->withoutGlobalScopes()
            ->where('gateway_id', $gateway->id)->where('provider_id', $providerId)->first();
        if ($existing) return;

        $sms = SmsMessage::create([
            'id'          => (string) Str::ulid(),
            'team_id'     => $gateway->team_id,
            'gateway_id'  => $gateway->id,
            'direction'   => 'inbound',
            'from'        => (string) ($body['from'] ?? ''),
            'to'          => (string) ($body['to']   ?? ''),
            'body'        => (string) ($body['message'] ?? $body['text'] ?? ''),
            'status'      => 'received',
            'provider_id' => $providerId,
            'metadata'    => ['source' => 'mqtt', 'raw' => $body],
            'received_at' => isset($body['received_at'])
                ? new DateTimeImmutable((string) $body['received_at'])
                : now(),
        ]);

        if ($gateway->team) app(SmsBilling::class)->recordIncoming($gateway->team);
        event(new MessageReceived($sms));
    }

    private function onHeartbeat(string $topic, string $payload): void
    {
        // topic: sms/heartbeat/{device_id}
        $body = $this->decode($payload) ?? [];
        $deviceId = explode('/', $topic)[2] ?? null;
        if (! $deviceId) return;

        $body['received_at'] = time();
        Cache::put("mqtt:hb:{$deviceId}", $body, (int) config('sms.mqtt.heartbeat_ttl') * 2);

        Gateway::query()->where('device_id', $deviceId)->update([
            'agent_last_seen_at' => now(),
            'agent_version'      => (string) ($body['version'] ?? null),
            'status'             => 'online',
            'health'             => [
                'reachable'        => true,
                'connection_state' => $body['connection_state'] ?? 'connected',
                'signal_rssi'      => $body['signal_rssi'] ?? null,
                'signal_rsrp'      => $body['signal_rsrp'] ?? null,
                'operator'         => $body['operator']    ?? null,
                'sim_status'       => $body['sim_status']  ?? null,
                'imei'             => $body['imei']        ?? null,
                'uptime_seconds'   => $body['uptime_seconds'] ?? null,
            ],
            'last_seen_at'       => now(),
        ]);
    }

    private function decode(string $payload): ?array
    {
        try {
            $r = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
            return is_array($r) ? $r : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
