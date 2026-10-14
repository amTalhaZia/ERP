<?php

namespace App\Actions\Payroll;

use App\Enums\Payroll\PayrollRunStatus;
use App\Models\Payroll\PayrollRun;
use App\Models\Payroll\PayslipLine;
use App\Models\Payroll\TaxTable;
use App\Services\Payroll\PayrollErrors;
use App\Services\Payroll\PayrollRunSynchronizer;
use App\Services\Payroll\PayslipCalculator;
use App\Services\Payroll\SalaryResolver;
use App\Services\Payroll\WorkedDaysCalculator;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * (Re)calculates every payslip of a Draft/Calculated run in one transaction.
 * Deterministic: payslip membership is re-synced, lines are rebuilt from the
 * salary/components/tax table in force, and run totals are sums of payslips.
 */
class CalculatePayrollRunAction
{
    public function __construct(
        private readonly PayrollRunSynchronizer $synchronizer,
        private readonly SalaryResolver $salaries,
        private readonly WorkedDaysCalculator $days,
        private readonly PayslipCalculator $calculator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{run: PayrollRun, skipped: list<array<string, mixed>>}
     */
    public function handle(PayrollRun $run, ?int $userId): array
    {
        return DB::transaction(function () use ($run): array {
            $locked = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if (! $locked->status->canCalculate()) {
                throw PayrollErrors::invalidState($locked, 'recalculated');
            }

            $locked->status = PayrollRunStatus::Calculated;
            $result = $this->synchronizer->sync($locked);

            $start = $locked->period_start->toDateString();
            $end = $locked->period_end->toDateString();
            $taxTable = TaxTable::effectiveOn($end);
            $leave = $this->days->unpaidLeaveFor($result['eligible']->keys()->all(), $start, $end);
            $payslips = $locked->payslips()->get();

            PayslipLine::query()->whereIn('payslip_id', $payslips->pluck('id')->all() ?: [0])->delete();

            $totals = ['gross' => [], 'tax' => [], 'total_deductions' => [], 'employer_contributions' => [], 'net' => []];
            $now = now();
            $lineRows = [];
            $warningCount = 0;

            foreach ($payslips as $payslip) {
                ['employee' => $employee, 'salary' => $salary] = $result['eligible']->get($payslip->employee_id);

                $days = $this->days->compute($employee, $start, $end, $leave->get($employee->id, collect()));
                $calc = $this->calculator->calculate(
                    (string) $salary->base_salary,
                    $days['working_days'],
                    $days['paid_days'],
                    $this->salaries->componentsFor($salary),
                    $taxTable,
                );

                $payslip->update([
                    'status' => PayrollRunStatus::Calculated,
                    'working_days' => $days['working_days'],
                    'unpaid_days' => $days['unpaid_days'],
                    'paid_days' => $days['paid_days'],
                    'earned_base' => $calc['earned_base'],
                    'gross' => $calc['gross'],
                    'taxable' => $calc['taxable'],
                    'tax' => $calc['tax'],
                    'total_deductions' => $calc['total_deductions'],
                    'employer_contributions' => $calc['employer_contributions'],
                    'net' => $calc['net'],
                    'warnings' => $calc['warnings'] === [] ? null : $calc['warnings'],
                ]);

                $warningCount += count($calc['warnings']);

                foreach (array_keys($totals) as $key) {
                    $totals[$key][] = $calc[$key];
                }

                foreach ($calc['lines'] as $line) {
                    $lineRows[] = $line + [
                        'company_id' => $locked->company_id,
                        'payslip_id' => $payslip->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($lineRows, 500) as $chunk) {
                PayslipLine::query()->insert($chunk);
            }

            $locked->update([
                'status' => PayrollRunStatus::Calculated,
                'total_gross' => Money::sum($totals['gross']),
                'total_tax' => Money::sum($totals['tax']),
                'total_deductions' => Money::sum($totals['total_deductions']),
                'total_employer_contributions' => Money::sum($totals['employer_contributions']),
                'total_net' => Money::sum($totals['net']),
                'employee_count' => $payslips->count(),
                'calculated_at' => $now,
            ]);

            $this->audit->log('payroll.run_calculated', $locked, null, ['status' => PayrollRunStatus::Calculated->value], [
                'employee_count' => $payslips->count(),
                'skipped_count' => count($result['skipped']),
                'warning_count' => $warningCount,
                'tax_table_id' => $taxTable?->id,
            ]);

            return ['run' => $locked, 'skipped' => $result['skipped']];
        });
    }
}
