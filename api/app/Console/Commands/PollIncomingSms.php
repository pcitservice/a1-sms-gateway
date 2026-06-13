<?php

namespace App\Console\Commands;

use App\Domain\Gateway\GatewayManager;
use App\Domain\Sms\Jobs\IngestIncomingSmsJob;
use App\Models\Gateway;
use Illuminate\Console\Command;

class PollIncomingSms extends Command
{
    protected $signature   = 'a1:gateway:poll';
    protected $description = 'Poll every active gateway for new inbound SMS and enqueue ingest jobs.';

    public function handle(GatewayManager $manager): int
    {
        $gateways = Gateway::query()->where('status', '!=', 'offline')->get();
        $this->info("Polling {$gateways->count()} gateways");

        foreach ($gateways as $row) {
            try {
                $driver = $manager->driver($row);
                foreach ($driver->pollIncoming() as $incoming) {
                    IngestIncomingSmsJob::dispatch(
                        $row->id,
                        $incoming->providerId,
                        $incoming->from,
                        $incoming->to,
                        $incoming->body,
                        $incoming->receivedAt->format(DATE_ATOM),
                        $incoming->metadata,
                    )->onQueue('sms.inbound');
                }
            } catch (\Throwable $e) {
                $this->warn("Gateway #{$row->id} poll failed: {$e->getMessage()}");
            }
        }
        return self::SUCCESS;
    }
}
