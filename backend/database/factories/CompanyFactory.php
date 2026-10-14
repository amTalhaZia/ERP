<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'legal_name' => $name.' '.fake()->companySuffix(),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'code' => Str::upper(Str::random(6)),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'website' => 'https://'.fake()->domainName(),
            'tax_number' => fake()->numerify('TAX-########'),
            'registration_number' => fake()->numerify('REG-######'),
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'locale' => 'en',
            'fiscal_year_start_month' => 1,
            'is_active' => true,
            'is_demo' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function demo(): static
    {
        return $this->state(['is_demo' => true]);
    }
}
