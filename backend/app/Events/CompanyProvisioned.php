<?php

namespace App\Events;

use App\Models\Company;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once after a company, its head office and roles exist. Modules listen
 * (auto-discovered `App\Listeners\*`) to seed their per-company defaults:
 * chart of accounts, financial periods, leave types, units of measure, etc.
 * Listeners run synchronously inside the provisioning transaction.
 */
class CompanyProvisioned
{
    use Dispatchable;

    public function __construct(public readonly Company $company) {}
}
