<?php

namespace App\Listeners\Sales;

use App\Events\CompanyProvisioned;
use App\Models\Sales\PipelineStage;

/**
 * Seeds the default sales pipeline for a freshly provisioned company.
 * Runs synchronously inside the provisioning transaction and is idempotent.
 */
class SeedSalesDefaults
{
    public function handle(CompanyProvisioned $event): void
    {
        $companyId = $event->company->id;

        if (PipelineStage::forCompany($companyId)->exists()) {
            return;
        }

        foreach (PipelineStage::defaults() as $stage) {
            PipelineStage::withoutTenancy()->create(['company_id' => $companyId] + $stage);
        }
    }
}
