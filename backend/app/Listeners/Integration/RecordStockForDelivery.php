<?php

namespace App\Listeners\Integration;

use App\Enums\Inventory\StockMovementType;
use App\Events\Sales\DeliveryConfirmed;
use App\Models\Sales\DeliveryLine;
use App\Services\Inventory\DocumentStockRecorder;

/**
 * Sales → Inventory: a confirmed delivery issues stock (type `sale`) from the
 * sales order's warehouse, referencing the delivery. Runs synchronously inside
 * ConfirmDeliveryAction's transaction, so insufficient stock (409) rolls the
 * whole confirmation back.
 *
 * Free-text lines and service products are skipped. A sales order without a
 * warehouse_id moves no stock (a warning is logged): set a warehouse on the
 * order for stock to be issued on delivery.
 */
class RecordStockForDelivery
{
    public function __construct(private readonly DocumentStockRecorder $recorder) {}

    public function handle(DeliveryConfirmed $event): void
    {
        $delivery = $event->delivery;
        $delivery->loadMissing(['salesOrder', 'lines.salesOrderLine']);
        $order = $delivery->salesOrder;

        $lines = $delivery->lines
            ->map(fn (DeliveryLine $line): array => [
                'product_id' => $line->salesOrderLine?->product_id,
                'quantity' => (string) $line->quantity,
            ])
            ->all();

        $this->recorder->record(
            $delivery,
            (int) $delivery->company_id,
            $order?->warehouse_id === null ? null : (int) $order->warehouse_id,
            StockMovementType::Sale,
            $lines,
            "Delivery {$delivery->number}".($order ? " for sales order {$order->number}" : ''),
        );
    }
}
