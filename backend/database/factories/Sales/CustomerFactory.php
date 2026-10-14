<?php

namespace Database\Factories\Sales;

use App\Enums\Sales\CustomerType;
use App\Models\Company;
use App\Models\Sales\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => 'C'.Str::upper(Str::random(8)),
            'name' => fake()->company(),
            'type' => CustomerType::Company,
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'currency' => 'USD',
            'payment_terms_days' => 30,
            'billing_address_line1' => fake()->streetAddress(),
            'billing_city' => fake()->city(),
            'billing_country' => 'US',
            'shipping_address_line1' => fake()->streetAddress(),
            'shipping_city' => fake()->city(),
            'shipping_country' => 'US',
            'is_active' => true,
        ];
    }

    public function individual(): static
    {
        return $this->state(['type' => CustomerType::Individual, 'name' => fake()->name()]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function located(): static
    {
        return $this->state(['latitude' => fake()->latitude(25, 48), 'longitude' => fake()->longitude(-122, -71)]);
    }
}
