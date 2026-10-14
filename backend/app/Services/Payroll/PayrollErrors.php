<?php

namespace App\Services\Payroll;

use App\Exceptions\Finance\FinanceException;
use App\Models\Payroll\PayrollRun;

/**
 * Payroll business-state refusals, rendered with the standard `{ message, code }`
 * envelope (FinanceException renders itself).
 */
final class PayrollErrors
{
    public static function invalidState(PayrollRun $run, string $action): FinanceException
    {
        return new FinanceException(
            "Payroll run {$run->number} is {$run->status->value} and cannot be {$action}.",
            'payroll.invalid_state',
        );
    }

    public static function duplicateRun(): FinanceException
    {
        return new FinanceException(
            'A payroll run already exists for this period and scope. Cancel it before creating another.',
            'payroll.duplicate_run',
        );
    }

    public static function selfApproval(): FinanceException
    {
        return new FinanceException('You cannot approve a payroll run you created.', 'payroll.self_approval', 403);
    }

    public static function emptyRun(PayrollRun $run): FinanceException
    {
        return new FinanceException("Payroll run {$run->number} has no payslips.", 'payroll.empty_run');
    }

    public static function negativeNet(PayrollRun $run): FinanceException
    {
        return new FinanceException(
            "Payroll run {$run->number} has payslips with a negative net pay; adjust the deductions and recalculate.",
            'payroll.negative_net',
        );
    }
}
