<?php

namespace App\Listeners\Integration;

use App\Enums\Inventory\StockMovementType;
use App\Events\Procurement\PurchaseReturnConfirmed;
use App\Models\Procurement\PurchaseReturnLine;
use App\Services\Inventory\DocumentStockRecorder;

/**
 * Procurement → Inventory: a confirmed purchase return issues the returned
 * goods (type `return_out`) from the related goods receipt's warehouse, or the
 * purchase order's warehouse when the return is not tied to a receipt. Runs
 * inside ConfirmPurchaseReturnAction's transaction, so insufficient stock
 * (409) rolls the confirmation back.
 */
class RecordStockForPurchaseReturn
{
    public function __construct(private readonly DocumentStockRecorder $recorder) {}

    public function handle(PurchaseReturnConfirmed $event): void
    {
        $return = $event->purchaseReturn;
        $return->loadMissing(['lines', 'goodsReceipt', 'purchaseOrder']);

        $lines = $return->lines
            ->map(fn (PurchaseReturnLine $line): array => [
                'product_id' => $line->product_id,
                'quantity' => (string) $line->quantity,
            ])
            ->all();

        $warehouseId = $return->goodsReceipt?->warehouse_id ?? $return->purchaseOrder?->warehouse_id;

        $this->recorder->record(
            $return,
            (int) $return->company_id,
            $warehouseId === null ? null : (int) $warehouseId,
            StockMovementType::ReturnOut,
            $lines,
            "Purchase return {$return->number}",
        );
    }
}
