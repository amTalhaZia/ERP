<?php

namespace App\Actions\Payroll;

use App\Enums\Finance\JournalSourceType;
use App\Enums\Payroll\PayComponentKind;
use App\Enums\Payroll\PayrollRunStatus;
use App\Models\HR\Employee;
use App\Models\Payroll\PayrollRun;
use App\Models\Payroll\PayslipLine;
use App\Models\User;
use App\Notifications\Payroll\PayslipAvailable;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Services\Finance\LedgerPoster;
use App\Services\Finance\Receivables\JournalLinesBuilder;
use App\Services\Payroll\PayrollErrors;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Approved → Finalized. Posts ONE summarised journal entry (source_type
 * payroll_run, dated the period end) from the payslip lines:
 *
 *   Dr earning GL accounts (default 6000 Salaries)          Σ earnings (basic + components)
 *   Dr employer-contribution GL accounts (default 6010)      Σ employer contributions
 *     Cr 2100 Tax Payable                                    Σ tax withheld (TAX lines)
 *     Cr deduction GL accounts (default 2300 Accrued Liab.)  Σ other deductions
 *     Cr 2300 Accrued Liabilities                            Σ employer contributions (owed to third parties)
 *     Cr 2200 Accrued Payroll                                Σ net pay (cleared by MarkPayrollPaidAction)
 *
 * Balances by construction: net = earnings − deductions − tax per payslip, all
 * from the same rounded lines. Payslips get their SLIP numbers here (ordered
 * by employee code) and the run becomes immutable. Employees with a login are
 * notified after commit (no amounts in the notification).
 */
class FinalizePayrollRunAction
{
    public function __construct(
        private readonly LedgerPoster $ledger,
        private readonly DocumentNumberer $numberer,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(PayrollRun $run, int $userId): PayrollRun
    {
        $finalized = DB::transaction(function () use ($run, $userId): PayrollRun {
            $locked = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($locked->status !== PayrollRunStatus::Approved) {
                throw PayrollErrors::invalidState($locked, 'finalized');
            }

            $entry = $this->ledger->post([
                'company_id' => (int) $locked->company_id,
                'entry_date' => $locked->period_end->toDateString(),
                'memo' => "Payroll {$locked->number} ({$locked->period_start->toDateString()} – {$locked->period_end->toDateString()})",
                'reference' => $locked->number,
                'source_type' => JournalSourceType::PayrollRun->value,
                'source_id' => $locked->id,
                'branch_id' => $locked->branch_id,
                'lines' => $this->journalLines($locked),
            ], $userId);

            foreach ($locked->payslips()->orderBy('employee_code')->orderBy('id')->get() as $payslip) {
                $payslip->update(['number' => $this->numberer->next('payslip'), 'status' => PayrollRunStatus::Finalized]);
            }

            $locked->update([
                'status' => PayrollRunStatus::Finalized,
                'finalized_by' => $userId,
                'finalized_at' => now(),
                'journal_entry_id' => $entry->id,
            ]);

            $this->audit->log('payroll.run_finalized', $locked, ['status' => PayrollRunStatus::Approved->value], [
                'status' => PayrollRunStatus::Finalized->value,
                'journal_entry_id' => $entry->id,
                'employee_count' => $locked->employee_count,
                'total_gross' => (string) $locked->total_gross,
                'total_net' => (string) $locked->total_net,
            ]);

            return $locked;
        });

        $this->notifyEmployees($finalized);

        return $finalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function journalLines(PayrollRun $run): array
    {
        $sums = PayslipLine::query()
            ->join('payslips', 'payslips.id', '=', 'payslip_lines.payslip_id')
            ->where('payslips.payroll_run_id', $run->id)
            ->groupBy('payslip_lines.kind', 'payslip_lines.gl_account_code')
            ->orderBy('payslip_lines.kind')
            ->orderBy('payslip_lines.gl_account_code')
            ->selectRaw('payslip_lines.kind AS kind, payslip_lines.gl_account_code AS code, SUM(payslip_lines.amount) AS total')
            ->toBase()
            ->get();

        $builder = new JournalLinesBuilder;
        $memo = "Payroll {$run->number}";
        $employer = Money::zero();

        foreach ($sums as $row) {
            $amount = Money::normalize((string) $row->total);

            match (PayComponentKind::from($row->kind)) {
                PayComponentKind::Earning => $builder->debit(['account_code' => $row->code], $amount, ['description' => "{$memo} earnings"]),
                PayComponentKind::Deduction => $builder->credit(['account_code' => $row->code], $amount, ['description' => "{$memo} deductions"]),
                PayComponentKind::EmployerContribution => $builder->debit(['account_code' => $row->code], $amount, ['description' => "{$memo} employer contributions"]),
            };

            if ($row->kind === PayComponentKind::EmployerContribution->value) {
                $employer = Money::add($employer, $amount);
            }
        }

        $builder->credit(['account_code' => ChartOfAccountsTemplate::ACCRUED_LIABILITIES], $employer, ['description' => "{$memo} employer contributions payable"]);
        $builder->credit(['account_code' => ChartOfAccountsTemplate::ACCRUED_PAYROLL], (string) $run->payslips()->sum('net'), ['description' => "{$memo} net pay"]);

        return $builder->lines();
    }

    private function notifyEmployees(PayrollRun $run): void
    {
        $payslips = $run->payslips()->get(['id', 'employee_id', 'number']);
        $userIds = Employee::query()->whereIn('id', $payslips->pluck('employee_id')->all() ?: [0])
            ->whereNotNull('user_id')->pluck('user_id', 'id');

        if ($userIds->isEmpty()) {
            return;
        }

        $users = User::query()->whereIn('id', $userIds->values()->all())->get()->keyBy('id');

        foreach ($payslips as $payslip) {
            $user = $users->get($userIds->get($payslip->employee_id));

            if ($user !== null) {
                Notification::send($user, new PayslipAvailable($payslip, $run));
            }
        }
    }
}
