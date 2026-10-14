<?php

namespace App\Providers;

use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequisition;
use App\Models\Procurement\PurchaseReturn;
use App\Models\Procurement\Supplier;
use App\Policies\Procurement\GoodsReceiptPolicy;
use App\Policies\Procurement\PurchaseOrderPolicy;
use App\Policies\Procurement\PurchaseRequisitionPolicy;
use App\Policies\Procurement\PurchaseReturnPolicy;
use App\Policies\Procurement\SupplierPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Procurement module wiring. Policies live under App\Policies\Procurement so
 * they are outside Laravel's auto-discovery path and must be mapped here.
 */
class ProcurementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(PurchaseRequisition::class, PurchaseRequisitionPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
        Gate::policy(PurchaseReturn::class, PurchaseReturnPolicy::class);
    }
}
