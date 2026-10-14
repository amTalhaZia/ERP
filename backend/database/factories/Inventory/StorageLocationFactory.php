<?php

namespace Database\Factories\Inventory;

use App\Enums\Inventory\StorageLocationType;
use App\Models\Inventory\StorageLocation;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StorageLocation>
 */
class StorageLocationFactory extends Factory
{
    protected $model = StorageLocation::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'company_id' => fn (array $attributes) => Warehouse::withoutTenancy()->find($attributes['warehouse_id'])?->company_id,
            'code' => 'BIN-'.Str::upper(Str::random(4)),
            'name' => 'Bin '.fake()->numerify('##'),
            'type' => StorageLocationType::Bin,
            'is_active' => true,
        ];
    }
}
