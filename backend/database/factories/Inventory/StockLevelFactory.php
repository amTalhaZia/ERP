<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only — real stock levels are maintained by RecordStockMovementAction.
 *
 * @extends Factory<StockLevel>
 */
class StockLevelFactory extends Factory
{
    protected $model = StockLevel::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'company_id' => fn (array $attributes) => Product::withoutTenancy()->find($attributes['product_id'])?->company_id,
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()->create(['company_id' => $attributes['company_id']])->id,
            'location_id' => null,
            'quantity_on_hand' => '0.0000',
            'quantity_reserved' => '0.0000',
            'average_cost' => '0.0000',
        ];
    }
}
