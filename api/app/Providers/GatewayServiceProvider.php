<?php

namespace App\Providers;

use App\Domain\Gateway\Contracts\SmsGateway;
use App\Domain\Gateway\Drivers\HuaweiDriver;
use App\Domain\Gateway\Drivers\MockDriver;
use App\Domain\Gateway\Drivers\Trb140Driver;
use App\Domain\Gateway\GatewayManager;
use App\Models\Gateway;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\ServiceProvider;

class GatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GatewayManager::class, function ($app) {
            $manager = new GatewayManager($app);

            $manager->extend('trb140', function (Gateway $row) use ($app) {
                return new Trb140Driver(
                    row: $row,
                    http: new HttpClient([
                        'base_uri' => sprintf('%s://%s:%d',
                            $row->protocol,
                            $row->host,
                            $row->port,
                        ),
                        'timeout'  => 15,
                        'verify'   => $row->protocol === 'https',
                    ]),
                    cache: $app['cache']->store(),
                    logger: $app['log']->channel(),
                );
            });

            $manager->extend('huawei', function (Gateway $row) use ($app) {
                return new HuaweiDriver($row, $app['log']->channel());
            });

            $manager->extend('mock', function (Gateway $row) use ($app) {
                return new MockDriver($row, $app['cache']->store(), $app['log']->channel());
            });

            return $manager;
        });

        // Default SmsGateway binding — the manager handles per-message routing
        // but injecting `SmsGateway` directly resolves to the team's primary.
        $this->app->bind(SmsGateway::class, function ($app) {
            return $app->make(GatewayManager::class)->primaryForCurrentTeam();
        });
    }

    public function boot(): void
    {
    }
}
