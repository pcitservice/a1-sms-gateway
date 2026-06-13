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
