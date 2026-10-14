<?php

namespace App\Events\Procurement;

use App\Models\Procurement\PurchaseOrder;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A purchase order was approved and is now a commitment to the supplier.
 * Loaded with `lines` and `supplier`. Dispatched inside the approving
 * transaction (e.g. for budget commitments or supplier notifications).
 */
class PurchaseOrderApproved
{
    use Dispatchable;

    public function __construct(public readonly PurchaseOrder $purchaseOrder) {}
}
