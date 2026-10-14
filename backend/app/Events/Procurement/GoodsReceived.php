<?php

namespace App\Events\Procurement;

use App\Models\Procurement\GoodsReceipt;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A goods receipt was confirmed. The receipt is loaded with `lines` and
 * `purchaseOrder.supplier`; each line carries product_id, warehouse (on the
 * header), quantity_received and unit_cost so Inventory can record the stock
 * receipt and AP can draft the supplier bill. Dispatched inside the confirming
 * transaction.
 */
class GoodsReceived
{
    use Dispatchable;

    public function __construct(public readonly GoodsReceipt $goodsReceipt) {}
}
