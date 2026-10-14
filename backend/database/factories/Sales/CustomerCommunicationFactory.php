<?php

namespace Database\Factories\Sales;

use App\Enums\Sales\CommunicationType;
use App\Models\Sales\Customer;
use App\Models\Sales\CustomerCommunication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCommunication>
 */
class CustomerCommunicationFactory extends Factory
{
    protected $model = CustomerCommunication::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'company_id' => fn (array $attributes) => Customer::withoutTenancy()->find($attributes['customer_id'])?->company_id,
            'type' => fake()->randomElement(CommunicationType::cases()),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'happened_at' => now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }
}
