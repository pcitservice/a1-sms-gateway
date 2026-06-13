<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * @OA\Info(title="A1 SMS Gateway API", version="1.0")
 * @OA\Server(url="https://sms.a1techflow.com/api/v1")
 */
class HealthController extends Controller
{
    /**
     * @OA\Get(
     *     path="/health",
     *     summary="Liveness probe",
     *     tags={"Health"},
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function index()
    {
        $db    = $this->probe(fn () => DB::connection()->getPdo() !== null);
        $redis = $this->probe(fn () => Redis::connection()->ping() ? true : false);

        $status = $db && $redis ? 'ok' : 'degraded';

        return response()->json([
            'status' => $status,
            'db'     => $db    ? 'ok' : 'fail',
            'redis'  => $redis ? 'ok' : 'fail',
            'time'   => now()->toIso8601String(),
            'version'=> config('app.version', '1.0.0'),
        ], $status === 'ok' ? 200 : 503);
    }

    private function probe(callable $fn): bool
    {
        try { return (bool) $fn(); } catch (\Throwable) { return false; }
    }
}
