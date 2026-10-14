<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Who runs a report, for which tenant. Definitions constrain every query to
 * `companyId()` explicitly, so a report is tenant-safe even when executed from
 * a queue worker.
 */
final class ReportContext
{
    /**
     * @param  list<int>|null  $branchIds  the user's branch restriction (null = all)
     */
    public function __construct(
        public readonly Company $company,
        public readonly User $user,
        public readonly ?array $branchIds = null,
    ) {}

    public function companyId(): int
    {
        return (int) $this->company->id;
    }

    public function currency(): string
    {
        return (string) $this->company->currency;
    }

    public function today(): string
    {
        return Carbon::now($this->company->timezone ?: 'UTC')->toDateString();
    }

    public function monthStart(): string
    {
        return Carbon::now($this->company->timezone ?: 'UTC')->startOfMonth()->toDateString();
    }
}
