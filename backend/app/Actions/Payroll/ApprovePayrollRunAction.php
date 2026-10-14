<?php

namespace App\Actions\Payroll;

use App\Enums\Payroll\PayrollRunStatus;
use App\Models\Payroll\PayrollRun;
use App\Services\Payroll\PayrollErrors;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Calculated → Approved. Segregation of duties: the approver must not be the
 * user who created the run (403 payroll.self_approval).
 */
class ApprovePayrollRunAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(PayrollRun $run, int $userId): PayrollRun
    {
        return DB::transaction(function () use ($run, $userId): PayrollRun {
            $locked = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($locked->status !== PayrollRunStatus::Calculated) {
                throw PayrollErrors::invalidState($locked, 'approved');
            }

            if ((int) $locked->created_by === $userId) {
                throw PayrollErrors::selfApproval();
            }

            if ($locked->employee_count === 0) {
                throw PayrollErrors::emptyRun($locked);
            }

            if ($locked->payslips()->where('net', '<', 0)->exists() || Money::isNegative((string) $locked->total_net)) {
                throw PayrollErrors::negativeNet($locked);
            }

            $locked->update(['status' => PayrollRunStatus::Approved, 'approved_by' => $userId, 'approved_at' => now()]);
            $locked->payslips()->update(['status' => PayrollRunStatus::Approved->value]);

            $this->audit->log('payroll.run_approved', $locked, ['status' => PayrollRunStatus::Calculated->value], [
                'status' => PayrollRunStatus::Approved->value,
                'approved_by' => $userId,
            ]);

            return $locked;
        });
    }
}
