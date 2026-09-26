<?php

namespace App\Console\Commands;

use App\Domain\Sms\Jobs\SendSmsJob;
use App\Models\SmsMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * Flushes scheduled SMS whose send_at has arrived. Wired to run every minute
 * from app/Console/Kernel.php (or bootstrap/app.php on Laravel 11+).
 *
 * Idempotent: uses a status flip inside a transaction so a second worker
 * picking up the same row observes it as already 'queued' and skips.
 */
class FlushScheduledSms extends Command
{
    protected $signature   = 'sms:flush-scheduled {--limit=500}';
    protected $description = 'Enqueue scheduled SMS whose send_at has arrived.';

    public function handle(): int
    {
        $limit    = (int) $this->option('limit');
        $flushed  = 0;

        SmsMessage::query()
            ->withoutGlobalScopes()
            ->where('status', 'scheduled')
            ->whereNotNull('send_at')
            ->where('send_at', '<=', now())
            ->orderBy('send_at')
            ->limit($limit)
            ->chunkById(100, function ($rows) use (&$flushed) {
                foreach ($rows as $msg) {
                    // Atomic status flip — only one worker wins.
                    $claimed = DB::table('sms_messages')
                        ->where('id', $msg->id)
                        ->where('status', 'scheduled')
                        ->update(['status' => 'queued', 'queued_at' => now(), 'updated_at' => now()]);
                    if (! $claimed) continue;

                    Bus::dispatch((new SendSmsJob($msg->id))->onQueue('sms.outbound'));
                    $flushed++;
                }
            });

        if ($flushed) $this->info("Flushed {$flushed} scheduled SMS.");
        return self::SUCCESS;
    }
}
