<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Gateway\GatewayManager;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Gateway;
use Illuminate\Http\Request;

class GatewayController extends Controller
{
    public function index() { return response()->json(Gateway::query()->latest()->paginate(50)); }

    public function store(Request $r)
    {
        $data = $r->validate([
            'team_id'   => 'nullable|integer',
            'name'      => 'required|string|max:80',
            'kind'      => 'required|string|in:trb140,huawei,mock',
            'host'      => 'nullable|string|max:120',
            'port'      => 'nullable|integer',
            'protocol'  => 'nullable|in:http,https',
            'username'  => 'nullable|string|max:80',
            'password'  => 'nullable|string|max:255',
            'modem_id'  => 'nullable|string|max:32',
            'rate_per_minute' => 'nullable|integer|min:1|max:600',
            'is_primary'      => 'boolean',
        ]);
        $g = Gateway::create($data);
        return response()->json($g, 201);
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
