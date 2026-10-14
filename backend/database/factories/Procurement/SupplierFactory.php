<?php

namespace Database\Factories\Procurement;

use App\Enums\Procurement\SupplierType;
use App\Models\Company;
use App\Models\Procurement\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => 'SUP-'.Str::upper(Str::random(5)),
            'name' => fake()->company(),
            'type' => SupplierType::Company,
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'website' => 'https://'.fake()->domainName(),
            'tax_number' => fake()->numerify('TAX-########'),
            'currency' => 'USD',
            'payment_terms_days' => 30,
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'latitude' => fake()->latitude(25, 48),
            'longitude' => fake()->longitude(-122, -71),
            'rating' => fake()->numberBetween(1, 5),
            'lead_time_days' => fake()->numberBetween(2, 21),
            'categories' => ['general'],
            'is_active' => true,
            'is_approved' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(['is_approved' => true, 'approved_at' => now()]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function individual(): static
    {
        return $this->state(['type' => SupplierType::Individual, 'name' => fake()->name()]);
    }
}
