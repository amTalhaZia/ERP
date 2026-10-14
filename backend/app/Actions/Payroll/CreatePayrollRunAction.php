<?php

namespace App\Actions\Payroll;

use App\Enums\Payroll\PayrollRunStatus;
use App\Models\Payroll\PayrollRun;
use App\Services\Payroll\PayrollErrors;
use App\Services\Payroll\PayrollRunSynchronizer;
use App\Support\Audit\AuditLogger;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Draft run with one payslip per eligible employee (snapshot only,
 * amounts are filled by CalculatePayrollRunAction). Employees without a
 * salary in force are returned as `skipped`.
 */
class CreatePayrollRunAction
{
    public function __construct(
        private readonly PayrollRunSynchronizer $synchronizer,
        private readonly DocumentNumberer $numberer,
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array{name: string, period_start: string, period_end: string, payment_date: string, branch_id?: ?int, department_id?: ?int, notes?: ?string}  $data
     * @return array{run: PayrollRun, skipped: list<array<string, mixed>>}
     */
    public function handle(array $data, ?int $userId): array
    {
        return DB::transaction(function () use ($data, $userId): array {
            $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
            $departmentId = isset($data['department_id']) ? (int) $data['department_id'] : null;
            $scopeKey = PayrollRun::scopeKeyFor($data['period_start'], $data['period_end'], $branchId, $departmentId);

            if (PayrollRun::query()->where('scope_key', $scopeKey)->lockForUpdate()->exists()) {
                throw PayrollErrors::duplicateRun();
            }

            $run = PayrollRun::query()->create([
                'number' => $this->numberer->next('payroll_run'),
                'name' => $data['name'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'payment_date' => $data['payment_date'],
                'currency' => $this->tenant->company()?->currency ?: 'USD',
                'status' => PayrollRunStatus::Draft,
                'branch_id' => $branchId,
                'department_id' => $departmentId,
                'scope_key' => $scopeKey,
                'created_by' => $userId,
                'notes' => $data['notes'] ?? null,
            ]);

            $result = $this->synchronizer->sync($run);
            $run->update(['employee_count' => $result['eligible']->count()]);

            $this->audit->log('payroll.run_created', $run, null, [
                'number' => $run->number,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'branch_id' => $branchId,
                'department_id' => $departmentId,
            ], ['employee_count' => $result['eligible']->count(), 'skipped_count' => count($result['skipped'])]);

            return ['run' => $run, 'skipped' => $result['skipped']];
        });
    }
}
