<?php

namespace App\Policies\Payroll;

use App\Models\Payroll\PayrollRun;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;
use App\Support\Tenancy\TenantContext;

/**
 * Runs are read with payroll.view and processed with payroll.process.
 * Branch-restricted users only see runs scoped to one of their branches
 * (a company-wide run contains every branch's salaries).
 */
class PayrollRunPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'payroll.view');
    }

    public function view(User $user, PayrollRun $run): bool
    {
        return $this->canOn($user, 'payroll.view', $run) && $this->runBranchAccessible($run);
    }

    public function export(User $user, PayrollRun $run): bool
    {
        return $this->view($user, $run);
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'payroll.process');
    }

    public function process(User $user, PayrollRun $run): bool
    {
        return $this->canOn($user, 'payroll.process', $run) && $this->runBranchAccessible($run);
    }

    public function delete(User $user, PayrollRun $run): bool
    {
        return $this->process($user, $run);
    }

    private function runBranchAccessible(PayrollRun $run): bool
    {
        $allowed = app(TenantContext::class)->allowedBranchIds();

        return $allowed === null || ($run->branch_id !== null && in_array((int) $run->branch_id, $allowed, true));
    }
}
