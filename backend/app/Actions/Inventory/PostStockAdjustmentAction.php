<?php

namespace App\Actions\Inventory;

use App\Enums\Inventory\StockAdjustmentStatus;
use App\Enums\Inventory\StockMovementType;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockAdjustmentLine;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Draft → posted. For every line the system quantity is re-read from the
 * (warehouse-level, no location) stock level at post time, so the resulting
 * on-hand equals the counted quantity exactly; the signed difference becomes
 * an adjustment_in / adjustment_out movement. Posted adjustments are immutable.
 */
class PostStockAdjustmentAction
{
    public function __construct(
        private readonly RecordStockMovementAction $movements,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(StockAdjustment $adjustment): StockAdjustment
    {
        abort_unless($adjustment->isDraft(), 409, 'This adjustment has already been posted.');

        return DB::transaction(function () use ($adjustment): StockAdjustment {
            $adjustment = StockAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            abort_unless($adjustment->isDraft(), 409, 'This adjustment has already been posted.');

            $lines = $adjustment->lines()->get();
            abort_if($lines->isEmpty(), 409, 'An adjustment needs at least one line before it can be posted.');

            $now = now();
            $reason = "Adjustment {$adjustment->number} ({$adjustment->reason->value})";
            $posted = 0;

            /** @var StockAdjustmentLine $line */
            foreach ($lines as $line) {
                $system = SaveStockAdjustmentAction::systemQuantity((int) $line->product_id, (int) $adjustment->warehouse_id);
                $difference = Money::sub(Money::normalize($line->counted_quantity), $system);

                $line->forceFill(['system_quantity' => $system, 'difference' => $difference]);

                if (Money::isZero($difference)) {
                    $line->save();

                    continue;
                }

                $movement = $this->movements->handle([
                    'product_id' => $line->product_id,
                    'warehouse_id' => $adjustment->warehouse_id,
                    'type' => Money::isNegative($difference) ? StockMovementType::AdjustmentOut : StockMovementType::AdjustmentIn,
                    'quantity' => Money::abs($difference),
                    'unit_cost' => Money::isZero(Money::normalize($line->unit_cost)) ? null : $line->unit_cost,
                    'reference_type' => $adjustment->getMorphClass(),
                    'reference_id' => $adjustment->id,
                    'reason' => $reason,
                    'moved_at' => $now,
                ]);

                $line->forceFill(['unit_cost' => $movement->unit_cost])->save();
                $posted++;
            }

            $adjustment->forceFill([
                'status' => StockAdjustmentStatus::Posted,
                'posted_at' => $now,
                'posted_by' => auth()->id(),
            ])->save();

            $this->audit->log('inventory.adjustment_posted', $adjustment, context: [
                'reason' => $adjustment->reason->value,
                'lines' => $lines->count(),
                'movements' => $posted,
            ]);

            return $adjustment;
        });
    }
}
