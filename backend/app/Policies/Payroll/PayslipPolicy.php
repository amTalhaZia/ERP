<?php

namespace App\Policies\Payroll;

use App\Models\Payroll\Payslip;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;
use App\Policies\HR\Concerns\ResolvesOwnEmployee;
use App\Support\Tenancy\TenantContext;

/**
 * Payslips: payroll.view sees all (within branch restriction); an employee
 * with payslips.view.own sees only their own, and only once released
 * (Finalized / Paid).
 */
class PayslipPolicy
{
    use ChecksPermissions, ResolvesOwnEmployee;

    public function view(User $user, Payslip $payslip): bool
    {
        if (! $this->sameTenant($payslip)) {
            return false;
        }

        if ($this->can($user, 'payroll.view') && $this->branchVisible($payslip)) {
            return true;
        }

        return $this->viewOwn($user, $payslip);
    }

    public function viewOwn(User $user, Payslip $payslip): bool
    {
        return $this->sameTenant($payslip)
            && $this->can($user, 'payslips.view.own')
            && $payslip->isReleased()
            && $this->ownsEmployee($user, (int) $payslip->employee_id);
    }

    public function viewMine(User $user): bool
    {
        return $this->can($user, 'payslips.view.own');
    }

    private function branchVisible(Payslip $payslip): bool
    {
        $allowed = app(TenantContext::class)->allowedBranchIds();

        return $allowed === null || ($payslip->branch_id !== null && in_array((int) $payslip->branch_id, $allowed, true));
    }
}
