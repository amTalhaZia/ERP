<?php

namespace App\Listeners\Integration;

use App\Enums\Inventory\StockMovementType;
use App\Events\Procurement\GoodsReceived;
use App\Models\Procurement\GoodsReceiptLine;
use App\Services\Inventory\DocumentStockRecorder;
use App\Support\Money\Money;

/**
 * Procurement → Inventory: a confirmed goods receipt receives the ACCEPTED
 * quantity (quantity_received − quantity_rejected) of each goods line into the
 * receipt's warehouse (falling back to the purchase order's), type `purchase`,
 * at the line's unit cost (weighted average), with its batch number / expiry.
 * Runs inside ConfirmGoodsReceiptAction's transaction.
 */
class RecordStockForGoodsReceipt
{
    public function __construct(private readonly DocumentStockRecorder $recorder) {}

    public function handle(GoodsReceived $event): void
    {
        $receipt = $event->goodsReceipt;
        $receipt->loadMissing(['lines', 'purchaseOrder']);

        $lines = $receipt->lines
            ->map(fn (GoodsReceiptLine $line): array => [
                'product_id' => $line->product_id,
                'quantity' => Money::sub((string) $line->quantity_received, (string) ($line->quantity_rejected ?? '0')),
                'unit_cost' => $line->unit_cost === null ? null : (string) $line->unit_cost,
                'batch_number' => $line->batch_number,
                'expires_at' => $line->expires_at?->toDateString(),
            ])
            ->all();

        $warehouseId = $receipt->warehouse_id ?? $receipt->purchaseOrder?->warehouse_id;

        $this->recorder->record(
            $receipt,
            (int) $receipt->company_id,
            $warehouseId === null ? null : (int) $warehouseId,
            StockMovementType::Purchase,
            $lines,
            "Goods receipt {$receipt->number}".($receipt->purchaseOrder ? " for PO {$receipt->purchaseOrder->number}" : ''),
        );
    }
}
