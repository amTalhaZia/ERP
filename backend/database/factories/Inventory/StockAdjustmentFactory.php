<?php

namespace Database\Factories\Inventory;

use App\Enums\Inventory\StockAdjustmentReason;
use App\Enums\Inventory\StockAdjustmentStatus;
use App\Models\Company;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'ADJ-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()->create(['company_id' => $attributes['company_id']])->id,
            'reason' => StockAdjustmentReason::Count,
            'status' => StockAdjustmentStatus::Draft,
        ];
    }
}
