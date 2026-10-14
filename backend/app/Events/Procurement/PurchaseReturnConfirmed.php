<?php

namespace App\Events\Procurement;

use App\Models\Procurement\PurchaseReturn;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A purchase return was confirmed. Loaded with `lines` and
 * `purchaseOrder.supplier`; Inventory should issue the returned quantities
 * (warehouse from the related goods receipt / purchase order) and AP may raise
 * a debit note. Dispatched inside the confirming transaction.
 */
class PurchaseReturnConfirmed
{
    use Dispatchable;

    public function __construct(public readonly PurchaseReturn $purchaseReturn) {}
}
