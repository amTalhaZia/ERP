<?php

namespace App\Events\Sales;

use App\Models\Sales\SalesOrder;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a sales order moves Draft → Confirmed. The order is loaded with
 * its `lines` (product_id, quantity, uom snapshots) and `customer`. Integration
 * listeners (Inventory reservation, AR invoicing) hook here.
 */
class SalesOrderConfirmed
{
    use Dispatchable;

    public function __construct(public readonly SalesOrder $salesOrder) {}
}
