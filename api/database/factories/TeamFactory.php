<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        return [
            'name'            => $name,
            'slug'            => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'timezone'        => 'Europe/Copenhagen',
            'trial_sms_used'  => 0,
            'trial_sms_limit' => 50,
        ];
    }
}
