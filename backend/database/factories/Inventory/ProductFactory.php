<?php

namespace Database\Factories\Inventory;

use App\Enums\Inventory\ProductType;
use App\Models\Company;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 1, 500);

        return [
            'company_id' => Company::factory(),
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'barcode' => fake()->ean13(),
            'name' => Str::title(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'type' => ProductType::Goods,
            'cost_price' => number_format($cost, 4, '.', ''),
            'sale_price' => number_format($cost * 1.4, 4, '.', ''),
            'currency' => 'USD',
            'tax_rate' => '0.0000',
            'reorder_level' => '0.0000',
            'reorder_quantity' => '0.0000',
            'track_batches' => false,
            'track_serials' => false,
            'is_active' => true,
        ];
    }

    public function service(): static
    {
        return $this->state(['type' => ProductType::Service]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function reorderAt(string $level, string $quantity = '10'): static
    {
        return $this->state(['reorder_level' => $level, 'reorder_quantity' => $quantity]);
    }

    public function serialTracked(): static
    {
        return $this->state(['track_serials' => true]);
    }
}
