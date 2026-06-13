<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function index() { return response()->json(Webhook::latest()->get()); }

    public function store(Request $r)
    {
        $data = $r->validate([
            'url'    => 'required|url|max:500',
            'events' => 'required|array',
            'events.*' => 'string|in:*,message.queued,message.sent,message.delivered,message.failed,message.received,gateway.online,gateway.offline,balance.low',
        ]);
        $secret = 'whsec_'.Str::random(40);
        $hook = Webhook::create([
            'team_id' => app('current_team')->id,
            'url'     => $data['url'],
            'events'  => $data['events'],
            'secret'  => $secret,
            'is_active' => true,
        ]);

        return response()->json(array_merge($hook->toArray(), ['secret' => $secret]), 201);
    }

    public function destroy(int $id)
    {
        Webhook::findOrFail($id)->delete();
        return response()->noContent();
    }
}
