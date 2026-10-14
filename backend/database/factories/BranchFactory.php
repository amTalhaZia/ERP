<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->city().' Branch',
            'code' => Str::upper(Str::random(4)),
            'is_head_office' => false,
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'latitude' => fake()->latitude(25, 48),
            'longitude' => fake()->longitude(-122, -71),
            'is_active' => true,
        ];
    }

    public function headOffice(): static
    {
        return $this->state(['name' => 'Head Office', 'code' => 'HO', 'is_head_office' => true]);
    }
}
