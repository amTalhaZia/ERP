<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockAdjustmentLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustmentLine>
 */
class StockAdjustmentLineFactory extends Factory
{
    protected $model = StockAdjustmentLine::class;

    public function definition(): array
    {
        return [
            'stock_adjustment_id' => StockAdjustment::factory(),
            'company_id' => fn (array $attributes) => StockAdjustment::withoutTenancy()->find($attributes['stock_adjustment_id'])?->company_id,
            'product_id' => fn (array $attributes) => Product::factory()->create(['company_id' => $attributes['company_id']])->id,
            'counted_quantity' => '0.0000',
            'system_quantity' => '0.0000',
            'difference' => '0.0000',
            'unit_cost' => '0.0000',
        ];
    }
}
