<?php

namespace Database\Factories\Inventory;

use App\Models\Company;
use App\Models\Inventory\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    protected $model = UnitOfMeasure::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->randomElement(['Piece', 'Kilogram', 'Litre', 'Box']),
            'symbol' => Str::lower(Str::random(4)),
            'precision' => 0,
            'is_active' => true,
        ];
    }
}
