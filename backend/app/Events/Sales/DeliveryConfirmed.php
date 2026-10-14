<?php

namespace App\Events\Sales;

use App\Models\Sales\Delivery;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a delivery is marked Delivered and the order's delivered
 * quantities have been incremented (same transaction, after commit is NOT
 * awaited — listeners run synchronously inside it). The delivery is loaded
 * with `lines.salesOrderLine` and `salesOrder`, so Inventory can record the
 * stock issue (product_id, quantity, warehouse_id on the order).
 */
class DeliveryConfirmed
{
    use Dispatchable;

    public function __construct(public readonly Delivery $delivery) {}
}
