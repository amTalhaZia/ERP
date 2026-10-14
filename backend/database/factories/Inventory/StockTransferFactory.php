<?php

namespace Database\Factories\Inventory;

use App\Enums\Inventory\StockTransferStatus;
use App\Models\Company;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'TRF-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'from_warehouse_id' => fn (array $attributes) => Warehouse::factory()->create(['company_id' => $attributes['company_id']])->id,
            'to_warehouse_id' => fn (array $attributes) => Warehouse::factory()->create(['company_id' => $attributes['company_id']])->id,
            'status' => StockTransferStatus::Draft,
            'notes' => null,
        ];
    }
}
