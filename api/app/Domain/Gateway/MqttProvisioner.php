<?php

namespace App\Domain\Gateway;

use App\Models\Gateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Prepares a Gateway row for use with the MQTT driver.
 *
 * v1 uses the shared broker admin credential across all devices; topic
 * isolation is by convention through the per-device `device_id` (each
 * agent only publishes to `sms/{status,inbound,heartbeat}/{device_id}`
 * and subscribes to `sms/{outbound,control}/{device_id}`).
 *
 * When we need stronger multi-tenant isolation (large fleet or per-team
 * BYOD), swap this for Mosquitto's Dynamic Security Plugin. The public
 * API of this class won't change.
 */
class MqttProvisioner
{
    public function provision(Gateway $gateway): array
    {
        $deviceId = $gateway->device_id ?: 'dev-'.Str::lower(Str::random(12));

        $username = (string) config('sms.mqtt.admin_user');
        $password = (string) config('sms.mqtt.admin_password');

        Log::channel()->info('MQTT device provisioned', [
            'gateway_id' => $gateway->id,
            'device_id'  => $deviceId,
        ]);

        return [$deviceId, $username, $password];
    }

    /**
     * The blob the agent script writes into its config on the device.
     */
    public function agentConfig(Gateway $gateway): array
    {
        [$deviceId, $username, $password] = $this->provision($gateway->fresh() ?? $gateway);

        return [
            'schema_version' => 1,
            'broker' => [
                'host'     => (string) config('sms.mqtt.public_host'),
                'port'     => (int)    config('sms.mqtt.public_port'),
                'tls'      => true,
                'username' => $username,
                'password' => $password,
            ],
            'device' => [
                'id'        => $deviceId,
                'name'      => $gateway->name,
                'modem_id'  => $gateway->modem_id ?? '2-1',
            ],
            'topics' => [
                'outbound'  => "sms/outbound/{$deviceId}",
                'control'   => "sms/control/{$deviceId}",
                'status'    => "sms/status/{$deviceId}",
                'inbound'   => "sms/inbound/{$deviceId}",
                'heartbeat' => "sms/heartbeat/{$deviceId}",
            ],
            'heartbeat_interval_seconds' => 30,
            'issued_at' => now()->toIso8601String(),
        ];
    }
}
