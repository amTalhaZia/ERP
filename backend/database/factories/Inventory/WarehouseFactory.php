<?php

namespace Database\Factories\Inventory;

use App\Models\Company;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'branch_id' => null,
            'name' => fake()->city().' Warehouse',
            'code' => 'WH'.Str::upper(Str::random(4)),
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'US',
            'latitude' => fake()->latitude(25, 48),
            'longitude' => fake()->longitude(-122, -71),
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
