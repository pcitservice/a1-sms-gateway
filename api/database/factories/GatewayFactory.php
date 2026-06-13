<?php

namespace Database\Factories;

use App\Models\Gateway;
use Illuminate\Database\Eloquent\Factories\Factory;

class GatewayFactory extends Factory
{
    protected $model = Gateway::class;

    public function definition(): array
    {
        return [
            'team_id'  => null,
            'name'     => $this->faker->word().' gw',
            'kind'     => 'mock',
            'host'     => '127.0.0.1',
            'port'     => 80,
            'protocol' => 'http',
            'status'   => 'online',
            'is_primary' => true,
            'rate_per_minute' => 60,
        ];
    }
}
