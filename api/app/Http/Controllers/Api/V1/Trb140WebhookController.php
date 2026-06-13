<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sms\Jobs\IngestIncomingSmsJob;
use App\Http\Controllers\Controller;
use App\Models\Gateway;
use Illuminate\Http\Request;

/**
 * Optional push-based ingest for TRB140s that have been configured to call
 * back into the platform when an SMS arrives (RutOS "External hook" feature).
 * Signature verification happens via the `webhook.signed` middleware.
 */
class Trb140WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->validate([
            'gateway_id' => 'required|integer',
            'id'         => 'required|string',
            'from'       => 'required|string',
            'to'         => 'nullable|string',
            'text'       => 'required|string',
            'date'       => 'nullable|string',
        ]);

        $gateway = Gateway::findOrFail($data['gateway_id']);

        IngestIncomingSmsJob::dispatch(
            $gateway->id,
            $data['id'],
            $data['from'],
            $data['to'] ?? '',
            $data['text'],
            $data['date'] ?? now()->toIso8601String(),
            ['source' => 'push'],
        )->onQueue('sms.inbound');

        return response()->json(['accepted' => true], 202);
    }
}
