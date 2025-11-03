<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'whatsapp' => $this->faker->optional()->e164PhoneNumber(),
            'whatsapp_verified' => false,
            'email' => $this->faker->optional()->safeEmail(),
            'email_verified' => false,
        ];
    }
}
