<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Gateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SystemController extends Controller
{
    public function queue()
    {
        $sizes = [];
        foreach (['default', 'sms.outbound', 'sms.inbound', 'webhooks'] as $q) {
            try {
                $sizes[$q] = (int) Redis::connection()->llen("queues:{$q}");
            } catch (\Throwable) {
                $sizes[$q] = null;
            }
        }
        return response()->json([
            'redis' => $sizes,
            'failed' => DB::table('failed_jobs')->count(),
        ]);
    }

    public function devices()
    {
        return response()->json(Gateway::query()->select(
            'id', 'team_id', 'name', 'kind', 'status', 'health', 'last_seen_at',
        )->get());
    }

    public function audit()
    {
        return response()->json(AuditLog::query()->latest()->paginate(100));
    }
}
