<?php

namespace Database\Factories\Inventory;

use App\Enums\Inventory\StockMovementType;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only — real movements are written by RecordStockMovementAction.
 *
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'company_id' => fn (array $attributes) => Product::withoutTenancy()->find($attributes['product_id'])?->company_id,
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()->create(['company_id' => $attributes['company_id']])->id,
            'type' => StockMovementType::Receipt,
            'quantity' => '10.0000',
            'unit_cost' => '5.0000',
            'total_cost' => '50.0000',
            'balance_after' => '10.0000',
            'moved_at' => now(),
        ];
    }
}
