<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\ProcurementServiceProvider::class,
    App\Providers\HRServiceProvider::class,
    App\Providers\SalesServiceProvider::class,
    App\Providers\FinanceServiceProvider::class,
    App\Providers\InventoryServiceProvider::class,
    App\Providers\ReceivablesServiceProvider::class,
    App\Providers\ProjectsServiceProvider::class,
    App\Providers\InsightsServiceProvider::class,
    App\Providers\PayrollServiceProvider::class,
];
