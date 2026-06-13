<?php

namespace Database\Seeders;

use App\Models\Gateway;
use Illuminate\Database\Seeder;

class GatewaySeeder extends Seeder
{
    public function run(): void
    {
        if (! config('sms.enable_mock')) {
            return;
        }

        Gateway::updateOrCreate(
            ['name' => 'Platform Mock'],
            [
                'team_id'        => null,
                'kind'           => 'mock',
                'host'           => '127.0.0.1',
                'port'           => 0,
                'protocol'       => 'http',
                'status'         => 'online',
                'is_primary'     => true,
                'rate_per_minute'=> 60,
            ],
        );
    }
}
