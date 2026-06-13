<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use App\Models\UsageRecord;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function usage(Request $request)
    {
        $from = $request->date('from') ?? now()->subDays(30);
        $to   = $request->date('to')   ?? now();
        $rows = UsageRecord::query()
            ->whereBetween('period', [$from->toDateString(), $to->toDateString()])
            ->orderBy('period')
            ->get();

        return response()->json([
            'from'    => $from->toDateString(),
            'to'      => $to->toDateString(),
            'totals'  => [
                'sent'      => $rows->sum('messages_sent'),
                'delivered' => $rows->sum('messages_delivered'),
                'failed'    => $rows->sum('messages_failed'),
                'received'  => $rows->sum('messages_received'),
                'segments'  => $rows->sum('segments_billed'),
            ],
            'series'  => $rows,
        ]);
    }

    public function delivery(Request $request)
    {
        $rows = SmsMessage::query()
            ->selectRaw('gateway_id, status, COUNT(*) as count')
            ->where('direction', 'outbound')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('gateway_id', 'status')
            ->get();
        return response()->json($rows);
    }
}
