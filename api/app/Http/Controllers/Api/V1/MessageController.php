<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\SmsMessage;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $q = SmsMessage::query();
        if ($request->filled('status'))    $q->where('status', $request->string('status'));
        if ($request->filled('direction')) $q->where('direction', $request->string('direction'));
        if ($request->filled('q'))         $q->where('to', 'like', '%'.$request->string('q').'%');

        return response()->json(
            $q->latest()->paginate($request->integer('per_page', 25))
        );
    }

    public function show(string $id)
    {
        return response()->json(SmsMessage::findOrFail($id));
    }

    public function events(string $id)
    {
        $message = SmsMessage::findOrFail($id);
        return response()->json($message->events()->get());
    }

    public function outbox(Request $request)
    {
        return response()->json(
            SmsMessage::query()
                ->where('direction', 'outbound')
                ->whereIn('status', ['scheduled', 'queued', 'sending'])
                ->orderByRaw("CASE status WHEN 'scheduled' THEN 1 WHEN 'queued' THEN 2 ELSE 3 END")
                ->orderBy('send_at')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function cancel(string $id)
    {
        $msg = SmsMessage::findOrFail($id);
        if (! in_array($msg->status, ['scheduled', 'queued'], true)) {
            return response()->json([
                'title'  => 'Cannot cancel',
                'status' => 409,
                'detail' => "Message is already in status \"{$msg->status}\".",
            ], 409);
        }
        // Refund the credit that dispatch() debited. Trial doesn't refund.
        if ((int) $msg->cost_ore > 0 && $msg->team) {
            $msg->team->increment('sms_credits', 1);
        }
        $msg->forceFill([
            'status'        => 'cancelled',
            'failed_at'     => now(),
            'error_code'    => 'user_cancelled',
            'error_message' => 'Cancelled by user before dispatch.',
        ])->save();
        return response()->json($msg);
    }

    public function linkClicks(string $id)
    {
        $message = SmsMessage::findOrFail($id);
        $links = \App\Models\ShortLink::query()
            ->where('message_id', $message->id)
            ->withCount('clicks')
            ->orderBy('id')
            ->get(['id', 'code', 'target_url', 'click_count', 'last_clicked_at']);
        return response()->json([
            'total_clicks' => $links->sum('click_count'),
            'links'        => $links,
        ]);
    }

    public function threads(Request $request)
    {
        // Group inbound msgs by `from` and last message timestamp.
        $rows = SmsMessage::query()
            ->where('direction', 'inbound')
            ->selectRaw('"from" as msisdn, MAX(received_at) as last_at, COUNT(*) as count')
            ->groupBy('from')
            ->orderByDesc('last_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($rows);
    }

    public function thread(Request $request, string $contact)
    {
        $msgs = SmsMessage::query()
            ->where(function ($q) use ($contact) {
                $q->where('from', $contact)->orWhere('to', $contact);
            })
            ->orderBy('created_at')
            ->paginate($request->integer('per_page', 50));
        return response()->json($msgs);
    }
}
