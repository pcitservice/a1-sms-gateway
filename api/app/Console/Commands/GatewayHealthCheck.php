<?php

namespace App\Console\Commands;

use App\Domain\Gateway\GatewayManager;
use App\Domain\Sms\Events\GatewayOffline;
use App\Domain\Sms\Events\GatewayOnline;
use App\Models\Gateway;
use Illuminate\Console\Command;

class GatewayHealthCheck extends Command
{
    protected $signature   = 'a1:gateway:watchdog';
    protected $description = 'Probe every gateway, update status, fire offline/online events.';

    public function handle(GatewayManager $manager): int
    {
        $rows = Gateway::query()->whereNull('deleted_at')->get();

        foreach ($rows as $row) {
            try {
                $driver = $manager->driver($row);
                $health = $driver->health();
                $newStatus = $health->reachable ? 'online' : 'offline';

                $row->forceFill([
                    'health'       => $health->toArray(),
                    'last_seen_at' => $health->reachable ? now() : $row->last_seen_at,
                    'status'       => $newStatus,
                ])->save();

                if ($newStatus !== $row->getOriginal('status')) {
                    if ($newStatus === 'offline') {
                        event(new GatewayOffline($row));
                    } else {
                        event(new GatewayOnline($row));
                    }
                }
            } catch (\Throwable $e) {
                $this->warn("Gateway #{$row->id} health probe failed: {$e->getMessage()}");
            }
        }
        return self::SUCCESS;
    }
}
