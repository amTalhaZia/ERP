<?php

namespace Database\Factories\Inventory;

use App\Models\Company;
use App\Models\Inventory\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'parent_id' => null,
            'name' => fake()->randomElement(['Electronics', 'Hardware', 'Consumables', 'Office Supplies', 'Spare Parts']),
            'code' => 'CAT-'.Str::upper(Str::random(5)),
            'is_active' => true,
        ];
    }
}
