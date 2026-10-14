<?php

namespace App\Actions\Inventory;

use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\StockTransferStatus;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StockTransferLine;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * in_transit → received: records a transfer_in movement per line at the
 * destination warehouse at the cost snapshotted when shipped, so value is
 * conserved across warehouses.
 */
class ReceiveStockTransferAction
{
    public function __construct(
        private readonly RecordStockMovementAction $movements,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(StockTransfer $transfer): StockTransfer
    {
        abort_unless($transfer->status === StockTransferStatus::InTransit, 409, 'Only in-transit transfers can be received.');

        return DB::transaction(function () use ($transfer): StockTransfer {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            abort_unless($transfer->status === StockTransferStatus::InTransit, 409, 'Only in-transit transfers can be received.');

            $now = now();
            $lines = $transfer->lines()->get();

            /** @var StockTransferLine $line */
            foreach ($lines as $line) {
                $this->movements->handle([
                    'product_id' => $line->product_id,
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'type' => StockMovementType::TransferIn,
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unit_cost,
                    'reference_type' => $transfer->getMorphClass(),
                    'reference_id' => $transfer->id,
                    'reason' => "Transfer {$transfer->number} received",
                    'moved_at' => $now,
                ]);
            }

            $transfer->forceFill([
                'status' => StockTransferStatus::Received,
                'received_at' => $now,
                'received_by' => auth()->id(),
            ])->save();

            $this->audit->log('inventory.transfer_received', $transfer, context: ['lines' => $lines->count()]);

            return $transfer;
        });
    }
}
