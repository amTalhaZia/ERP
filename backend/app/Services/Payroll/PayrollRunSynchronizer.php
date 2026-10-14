<?php

namespace App\Services\Payroll;

use App\Enums\HR\EmployeeStatus;
use App\Enums\Payroll\PayrollRunStatus;
use App\Models\HR\Employee;
use App\Models\Payroll\EmployeeSalary;
use App\Models\Payroll\PayrollRun;
use App\Models\Payroll\Payslip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Keeps a Draft/Calculated run's payslips in sync with the employees in scope.
 *
 * In scope: employees of the run's branch/department (when set), hired on or
 * before the period end and not terminated before the period start. An
 * employee is skipped (and reported) when they have no salary in force for
 * the period, the salary currency differs from the run currency (no FX), or
 * they are already on another non-cancelled run with an overlapping period.
 */
class PayrollRunSynchronizer
{
    public const SKIP_NO_SALARY = 'no_salary';

    public const SKIP_CURRENCY = 'currency_mismatch';

    public const SKIP_OTHER_RUN = 'already_in_run';

    public function __construct(private readonly SalaryResolver $salaries) {}

    /**
     * @return array{eligible: Collection<int, array{employee: Employee, salary: EmployeeSalary}>, skipped: list<array{employee_id: int, employee_code: string, name: string, reason: string}>}
     */
    public function sync(PayrollRun $run): array
    {
        $start = $run->period_start->toDateString();
        $end = $run->period_end->toDateString();

        $employees = Employee::query()
            ->with(['department:id,name', 'designation:id,title'])
            ->when($run->branch_id, fn (Builder $q, mixed $id) => $q->where('branch_id', $id))
            ->when($run->department_id, fn (Builder $q, mixed $id) => $q->where('department_id', $id))
            ->where(fn (Builder $q) => $q->whereNull('hired_at')->orWhere('hired_at', '<=', $end))
            ->where(fn (Builder $q) => $q->where('status', '!=', EmployeeStatus::Terminated->value)
                ->orWhere('terminated_at', '>=', $start))
            ->orderBy('employee_code')
            ->get();

        $ids = $employees->pluck('id')->all();
        $salaries = $this->salaries->salariesFor($ids, $start, $end);

        $onOtherRuns = $ids === [] ? collect() : Payslip::query()
            ->whereIn('employee_id', $ids)
            ->whereHas('run', fn (Builder $q) => $q->whereKeyNot($run->id)
                ->where('status', '!=', PayrollRunStatus::Cancelled->value)
                ->where('period_start', '<=', $end)
                ->where('period_end', '>=', $start))
            ->pluck('employee_id')
            ->flip();

        $eligible = collect();
        $skipped = [];

        foreach ($employees as $employee) {
            $salary = $salaries->get($employee->id);
            $reason = match (true) {
                $onOtherRuns->has($employee->id) => self::SKIP_OTHER_RUN,
                $salary === null => self::SKIP_NO_SALARY,
                $salary->currency !== $run->currency => self::SKIP_CURRENCY,
                default => null,
            };

            if ($reason !== null) {
                $skipped[] = [
                    'employee_id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'name' => $employee->fullName(),
                    'reason' => $reason,
                ];

                continue;
            }

            $eligible->put($employee->id, ['employee' => $employee, 'salary' => $salary]);
        }

        // Drop payslips of employees no longer eligible (lines cascade).
        $run->payslips()->whereNotIn('employee_id', $eligible->keys()->all() ?: [0])->delete();

        $existing = $run->payslips()->get()->keyBy('employee_id');

        foreach ($eligible as $employeeId => ['employee' => $employee, 'salary' => $salary]) {
            $snapshot = $this->snapshot($employee, $salary) + ['status' => $run->status];
            $payslip = $existing->get($employeeId);

            if ($payslip === null) {
                $run->payslips()->create($snapshot + [
                    'company_id' => $run->company_id,
                    'employee_id' => $employeeId,
                    'currency' => $run->currency,
                ]);
            } else {
                $payslip->update($snapshot);
            }
        }

        return ['eligible' => $eligible, 'skipped' => $skipped];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Employee $employee, EmployeeSalary $salary): array
    {
        $accountNumber = (string) ($employee->bank_account_number ?? '');

        return [
            'employee_salary_id' => $salary->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => mb_substr($employee->fullName(), 0, 200),
            'department_id' => $employee->department_id,
            'department_name' => $employee->department?->name,
            'designation_title' => $employee->designation?->title,
            'branch_id' => $employee->branch_id,
            'bank_name' => $employee->bank_name,
            'bank_account_last4' => $accountNumber === '' ? null : substr($accountNumber, -4),
            'base_salary' => (string) $salary->base_salary,
        ];
    }
}
