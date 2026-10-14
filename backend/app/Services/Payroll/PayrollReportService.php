<?php

namespace App\Services\Payroll;

use App\Enums\Payroll\PayrollRunStatus;
use App\Models\Payroll\PayrollRun;
use App\Models\Payroll\Payslip;
use App\Models\Payroll\PayslipLine;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * SQL-aggregated payroll reports over released runs (Finalized / Paid) whose
 * period ends within [from, to]. Branch-restricted users only see runs scoped
 * to their branches.
 */
class PayrollReportService
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(string $from, string $to): array
    {
        $runs = $this->releasedRuns($from, $to)
            ->orderBy('period_end')->orderBy('id')
            ->get(['id', 'number', 'name', 'period_start', 'period_end', 'status', 'currency', 'employee_count',
                'total_gross', 'total_tax', 'total_deductions', 'total_employer_contributions', 'total_net']);

        $runIds = $runs->pluck('id')->all() ?: [0];
        $payslips = fn () => Payslip::query()->whereIn('payroll_run_id', $runIds);

        $departments = $payslips()
            ->groupBy('department_id', 'department_name')
            ->orderBy('department_name')
            ->selectRaw('department_id, department_name, COUNT(*) AS payslips, COUNT(DISTINCT employee_id) AS employees,
                SUM(gross) AS gross, SUM(tax) AS tax, SUM(total_deductions) AS deductions,
                SUM(employer_contributions) AS employer_contributions, SUM(net) AS net')
            ->toBase()->get()
            ->map(fn (object $r) => [
                'department_id' => $r->department_id === null ? null : (int) $r->department_id,
                'department' => $r->department_name ?? 'Unassigned',
                'payslips' => (int) $r->payslips,
                'employees' => (int) $r->employees,
                'gross' => Money::normalize((string) $r->gross),
                'tax' => Money::normalize((string) $r->tax),
                'deductions' => Money::normalize((string) $r->deductions),
                'employer_contributions' => Money::normalize((string) $r->employer_contributions),
                'net' => Money::normalize((string) $r->net),
            ])->values();

        $totals = $payslips()
            ->selectRaw('COUNT(*) AS payslips, COUNT(DISTINCT employee_id) AS headcount, SUM(gross) AS gross, SUM(tax) AS tax,
                SUM(total_deductions) AS deductions, SUM(employer_contributions) AS employer_contributions, SUM(net) AS net')
            ->toBase()->first();

        $count = (int) ($totals->payslips ?? 0);
        $net = Money::normalize((string) ($totals->net ?? '0'));

        return [
            'from' => $from,
            'to' => $to,
            'runs' => $runs->map(fn (PayrollRun $run) => [
                'id' => $run->id,
                'number' => $run->number,
                'name' => $run->name,
                'period_start' => $run->period_start->toDateString(),
                'period_end' => $run->period_end->toDateString(),
                'status' => $run->status->value,
                'currency' => $run->currency,
                'employee_count' => $run->employee_count,
                'gross' => (string) $run->total_gross,
                'tax' => (string) $run->total_tax,
                'deductions' => (string) $run->total_deductions,
                'employer_contributions' => (string) $run->total_employer_contributions,
                'net' => (string) $run->total_net,
            ])->values(),
            'departments' => $departments,
            'totals' => [
                'runs' => $runs->count(),
                'payslips' => $count,
                'headcount_paid' => (int) ($totals->headcount ?? 0),
                'gross' => Money::normalize((string) ($totals->gross ?? '0')),
                'tax' => Money::normalize((string) ($totals->tax ?? '0')),
                'deductions' => Money::normalize((string) ($totals->deductions ?? '0')),
                'employer_contributions' => Money::normalize((string) ($totals->employer_contributions ?? '0')),
                'net' => $net,
                'average_net' => $count === 0 ? Money::zero() : Money::normalize(Money::round(Money::div($net, (string) $count), 2)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function components(PayrollRun $run): array
    {
        $rows = PayslipLine::query()
            ->join('payslips', 'payslips.id', '=', 'payslip_lines.payslip_id')
            ->where('payslips.payroll_run_id', $run->id)
            ->groupBy('payslip_lines.code', 'payslip_lines.name', 'payslip_lines.kind')
            ->orderByRaw("CASE payslip_lines.kind WHEN 'earning' THEN 1 WHEN 'deduction' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE payslip_lines.code WHEN 'BASE' THEN 0 WHEN 'TAX' THEN 2 ELSE 1 END")
            ->orderBy('payslip_lines.code')
            ->selectRaw('payslip_lines.code AS code, payslip_lines.name AS name, payslip_lines.kind AS kind,
                COUNT(DISTINCT payslip_lines.payslip_id) AS payslips, SUM(payslip_lines.amount) AS total')
            ->toBase()->get()
            ->map(fn (object $r) => [
                'code' => $r->code,
                'name' => $r->name,
                'kind' => $r->kind,
                'payslips' => (int) $r->payslips,
                'total' => Money::normalize((string) $r->total),
            ])->values();

        return [
            'run' => ['id' => $run->id, 'number' => $run->number, 'status' => $run->status->value, 'currency' => $run->currency],
            'components' => $rows,
        ];
    }

    /**
     * @return Builder<PayrollRun>
     */
    private function releasedRuns(string $from, string $to): Builder
    {
        $allowed = $this->tenant->allowedBranchIds();

        return PayrollRun::query()
            ->whereIn('status', PayrollRunStatus::releasedValues())
            ->whereBetween('period_end', [$from, $to])
            ->when($allowed !== null, fn (Builder $q) => $q->whereIn('branch_id', $allowed));
    }
}
