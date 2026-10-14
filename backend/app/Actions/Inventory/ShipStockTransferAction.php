<?php

namespace App\Actions\Inventory;

use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\StockTransferStatus;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StockTransferLine;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Draft → in_transit: records a transfer_out movement per line at the source
 * warehouse and snapshots the unit cost (source average) on the line so the
 * receiving side books the same value.
 */
class ShipStockTransferAction
{
    public function __construct(
        private readonly RecordStockMovementAction $movements,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(StockTransfer $transfer): StockTransfer
    {
        abort_unless($transfer->isDraft(), 409, 'Only draft transfers can be shipped.');

        return DB::transaction(function () use ($transfer): StockTransfer {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            abort_unless($transfer->isDraft(), 409, 'Only draft transfers can be shipped.');

            $lines = $transfer->lines()->get();
            abort_if($lines->isEmpty(), 409, 'A transfer needs at least one line before it can be shipped.');

            $now = now();

            /** @var StockTransferLine $line */
            foreach ($lines as $line) {
                $movement = $this->movements->handle([
                    'product_id' => $line->product_id,
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'type' => StockMovementType::TransferOut,
                    'quantity' => $line->quantity,
                    'reference_type' => $transfer->getMorphClass(),
                    'reference_id' => $transfer->id,
                    'reason' => "Transfer {$transfer->number} shipped",
                    'moved_at' => $now,
                ]);

                $line->forceFill(['unit_cost' => $movement->unit_cost])->save();
            }

            $transfer->forceFill([
                'status' => StockTransferStatus::InTransit,
                'shipped_at' => $now,
                'shipped_by' => auth()->id(),
            ])->save();

            $this->audit->log('inventory.transfer_shipped', $transfer, context: ['lines' => $lines->count()]);

            return $transfer;
        });
    }
}
