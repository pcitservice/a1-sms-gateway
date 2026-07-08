<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Gateway\GatewayManager;
use App\Domain\Gateway\MqttProvisioner;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Gateway;
use Illuminate\Http\Request;

class GatewayController extends Controller
{
    public function index() { return response()->json(Gateway::query()->latest()->paginate(50)); }

    public function store(Request $r, MqttProvisioner $mqtt)
    {
        $data = $r->validate([
            'team_id'   => 'nullable|integer',
            'name'      => 'required|string|max:80',
            'kind'      => 'required|string|in:trb140,trb140-mqtt,huawei,mock',
            'device_id' => 'nullable|string|max:64',
            'host'      => 'nullable|string|max:120',
            'port'      => 'nullable|integer',
            'protocol'  => 'nullable|in:http,https',
            'username'  => 'nullable|string|max:80',
            'password'  => 'nullable|string|max:255',
            'modem_id'  => 'nullable|string|max:32',
            'rate_per_minute' => 'nullable|integer|min:1|max:600',
            'is_primary'      => 'boolean',
        ]);

        // MQTT kind: mint mqtt creds + device_id in one place.
        if ($data['kind'] === 'trb140-mqtt') {
            [$deviceId, $user, $pass] = $mqtt->provision(new Gateway($data));
            $data['device_id']     = $data['device_id'] ?? $deviceId;
            $data['mqtt_username'] = $user;
            $data['mqtt_password'] = $pass;
            // Placeholder host — not used by the MQTT driver, but the DB column
            // is required by the schema.
            $data['host'] = $data['host'] ?? 'mqtt-agent';
        }

        $g = Gateway::create($data);
        return response()->json($g, 201);
    }

    public function agentConfig(Gateway $gateway, MqttProvisioner $mqtt)
    {
        if ($gateway->kind !== 'trb140-mqtt') {
            return response()->json(['title' => 'Agent config only applies to trb140-mqtt gateways', 'status' => 422], 422);
        }
        // Signed URL, 30-min expiry — customer pastes this into their SSH
        // session on the device; no bearer tokens to shell-escape.
        $configUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'agent.config', now()->addMinutes(30), ['gateway' => $gateway->id],
        );

        $installCmd = sprintf(
            'curl -fsSL %s/agent/install-agent.sh | A1SMS_CONFIG_URL=%s sh',
            rtrim(config('app.url'), '/'),
            escapeshellarg($configUrl),
        );

        return response()->json([
            'gateway_id' => $gateway->id,
            'device_id'  => $gateway->device_id,
            'config_url' => $configUrl,
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
            'ssh_command' => $installCmd,
            'bundle'     => $mqtt->agentConfig($gateway),
        ]);
    }

    public function show(Gateway $gateway) { return response()->json($gateway); }

    public function update(Request $r, Gateway $gateway)
    {
        $gateway->update($r->all());
        return response()->json($gateway);
    }

    public function destroy(Gateway $gateway, GatewayManager $manager)
    {
        $manager->forgetCached($gateway->id);
        $gateway->delete();
        return response()->noContent();
    }

    public function reboot(Request $r, Gateway $gateway, GatewayManager $manager)
    {
        $manager->driver($gateway)->reboot();
        AuditLog::create([
            'user_id' => $r->user()->id, 'action' => 'gateway.rebooted',
            'subject_type' => Gateway::class, 'subject_id' => $gateway->id,
            'occurred_at' => now(), 'ip_address' => $r->ip(), 'payload' => [],
        ]);
        return response()->noContent();
    }

    public function reassign(Request $r, Gateway $gateway)
    {
        $data = $r->validate(['team_id' => 'nullable|integer']);
        $gateway->update(['team_id' => $data['team_id']]);
        return response()->json($gateway);
    }

    public function health(Gateway $gateway, GatewayManager $manager)
    {
        return response()->json($manager->driver($gateway)->health()->toArray());
    }
}
