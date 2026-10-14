<?php

namespace App\Actions\Inventory;

use App\Enums\Inventory\StockAdjustmentStatus;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockAdjustmentLine;
use App\Models\Inventory\StockLevel;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use Illuminate\Support\Facades\DB;

/**
 * Creates / updates DRAFT adjustments. Each line snapshots the current system
 * quantity (sum of the warehouse's levels for the product) so the reviewer
 * sees the expected difference; PostStockAdjustmentAction re-reads it under
 * lock when posting.
 */
class SaveStockAdjustmentAction
{
    public function __construct(private readonly DocumentNumberer $numberer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): StockAdjustment
    {
        return DB::transaction(function () use ($data): StockAdjustment {
            $adjustment = StockAdjustment::query()->create([
                'number' => $this->numberer->next('stock_adjustment'),
                'warehouse_id' => $data['warehouse_id'],
                'reason' => $data['reason'],
                'status' => StockAdjustmentStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->syncLines($adjustment, $data['lines']);

            return $adjustment;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(StockAdjustment $adjustment, array $data): StockAdjustment
    {
        abort_unless($adjustment->isDraft(), 409, 'Posted adjustments are immutable.');

        return DB::transaction(function () use ($adjustment, $data): StockAdjustment {
            $adjustment->fill(array_intersect_key($data, array_flip(['warehouse_id', 'reason', 'notes'])))->save();

            if (array_key_exists('lines', $data)) {
                $this->syncLines($adjustment, $data['lines']);
            }

            return $adjustment;
        });
    }

    /**
     * @param  array<int, array{product_id: int, counted_quantity: string|int|float, unit_cost?: string|int|float|null}>  $lines
     */
    private function syncLines(StockAdjustment $adjustment, array $lines): void
    {
        $adjustment->lines()->delete();

        foreach ($lines as $line) {
            $system = $this->systemQuantity((int) $line['product_id'], (int) $adjustment->warehouse_id);
            $counted = Money::normalize($line['counted_quantity']);

            StockAdjustmentLine::query()->create([
                'stock_adjustment_id' => $adjustment->id,
                'product_id' => $line['product_id'],
                'counted_quantity' => $counted,
                'system_quantity' => $system,
                'difference' => Money::sub($counted, $system),
                'unit_cost' => isset($line['unit_cost']) && $line['unit_cost'] !== '' ? Money::normalize($line['unit_cost']) : Money::zero(),
            ]);
        }
    }

    public static function systemQuantity(int $productId, int $warehouseId): string
    {
        return Money::normalize((string) StockLevel::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereNull('location_id')
            ->sum('quantity_on_hand'));
    }
}
