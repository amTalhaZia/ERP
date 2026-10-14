<?php

namespace App\Actions\Payroll;

use App\Enums\Payroll\PayrollRunStatus;
use App\Models\Payroll\PayrollRun;
use App\Services\Payroll\PayrollErrors;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a run that has not been finalized. The scope key is released so a
 * new run can be created for the same period and scope; payslips are kept
 * (status cancelled) for the audit trail.
 */
class CancelPayrollRunAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(PayrollRun $run, int $userId, ?string $reason = null): PayrollRun
    {
        return DB::transaction(function () use ($run, $userId, $reason): PayrollRun {
            $locked = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if (! $locked->status->canCancel()) {
                throw PayrollErrors::invalidState($locked, 'cancelled');
            }

            $before = $locked->status->value;

            $locked->update([
                'status' => PayrollRunStatus::Cancelled,
                'scope_key' => null,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
            ]);
            $locked->payslips()->update(['status' => PayrollRunStatus::Cancelled->value]);

            $this->audit->log('payroll.run_cancelled', $locked, ['status' => $before], [
                'status' => PayrollRunStatus::Cancelled->value,
            ], array_filter(['reason' => $reason]));

            return $locked;
        });
    }
}
