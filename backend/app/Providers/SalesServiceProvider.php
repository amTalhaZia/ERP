<?php

namespace App\Providers;

use App\Models\Sales\Customer;
use App\Models\Sales\Delivery;
use App\Models\Sales\Lead;
use App\Models\Sales\Opportunity;
use App\Models\Sales\PipelineStage;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use App\Policies\Sales\CustomerPolicy;
use App\Policies\Sales\DeliveryPolicy;
use App\Policies\Sales\LeadPolicy;
use App\Policies\Sales\OpportunityPolicy;
use App\Policies\Sales\PipelineStagePolicy;
use App\Policies\Sales\QuotationPolicy;
use App\Policies\Sales\SalesOrderPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Sales & CRM module wiring. Policies would also be auto-discovered
 * (App\Models\Sales\X → App\Policies\Sales\XPolicy); registering them here
 * keeps the mapping explicit. Listeners are auto-discovered from app/Listeners.
 */
class SalesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(Opportunity::class, OpportunityPolicy::class);
        Gate::policy(PipelineStage::class, PipelineStagePolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);
        Gate::policy(Delivery::class, DeliveryPolicy::class);

        Gate::define('sales.reports.view', fn (User $user): bool => $user->hasPermission('reports.view'));
        Gate::define('sales.pipeline.view', fn (User $user): bool => $user->hasAnyPermission(['leads.view', 'reports.view']));
    }
}
