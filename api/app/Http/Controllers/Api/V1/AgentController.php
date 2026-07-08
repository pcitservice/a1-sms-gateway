<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Gateway\MqttProvisioner;
use App\Http\Controllers\Controller;
use App\Models\Gateway;
use Illuminate\Http\Request;

/**
 * Endpoints called by the RutOS-side install script + agent.
 * Auth model: a per-gateway signed URL, so the customer only pastes the URL
 * into the SSH session — no bearer tokens to shell-escape.
 */
class AgentController extends Controller
{
    public function config(Request $request, int $gateway, MqttProvisioner $mqtt)
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'Invalid or expired signed URL.');
        }
        $row = Gateway::query()->findOrFail($gateway);
        if ($row->kind !== 'trb140-mqtt') {
            abort(422, 'Agent config only applies to trb140-mqtt gateways.');
        }
        return response()->json($mqtt->agentConfig($row));
    }
}
