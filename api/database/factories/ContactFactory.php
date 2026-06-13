<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'msisdn'        => '+4'.$this->faker->numberBetween(5, 9).$this->faker->numerify('#######'),
            'first_name'    => $this->faker->firstName(),
            'last_name'     => $this->faker->lastName(),
            'email'         => $this->faker->safeEmail(),
            'opt_in_status' => 'opted_in',
            'attributes'    => [],
        ];
    }
}
