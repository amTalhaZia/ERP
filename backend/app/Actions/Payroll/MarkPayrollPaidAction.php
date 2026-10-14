<?php

namespace App\Actions\Payroll;

use App\Enums\Finance\JournalSourceType;
use App\Enums\Payroll\PayrollRunStatus;
use App\Models\Finance\BankAccount;
use App\Models\Payroll\PayrollRun;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Services\Finance\LedgerPoster;
use App\Services\Payroll\PayrollErrors;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Finalized → Paid. Clears the net-pay liability from the bank:
 *
 *   Dr 2200 Accrued Payroll          total net
 *     Cr bank account's GL account   total net
 *
 * Employer contributions and withheld tax stay on 2300 / 2100 until remitted
 * through the normal AP/GL processes.
 */
class MarkPayrollPaidAction
{
    public function __construct(
        private readonly LedgerPoster $ledger,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{bank_account_id: int, paid_at: string}  $data
     */
    public function handle(PayrollRun $run, array $data, int $userId): PayrollRun
    {
        return DB::transaction(function () use ($run, $data, $userId): PayrollRun {
            $locked = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($locked->status !== PayrollRunStatus::Finalized) {
                throw PayrollErrors::invalidState($locked, 'marked as paid');
            }

            $bank = BankAccount::query()->findOrFail($data['bank_account_id']);
            $paidAt = CarbonImmutable::parse($data['paid_at']);
            $net = (string) $locked->total_net;

            $entry = $this->ledger->post([
                'company_id' => (int) $locked->company_id,
                'entry_date' => $paidAt->toDateString(),
                'memo' => "Payroll {$locked->number} payment",
                'reference' => $locked->number,
                'source_type' => JournalSourceType::PayrollRun->value,
                'source_id' => $locked->id,
                'branch_id' => $locked->branch_id,
                'lines' => [
                    ['account_code' => ChartOfAccountsTemplate::ACCRUED_PAYROLL, 'debit' => $net, 'credit' => '0', 'description' => "Payroll {$locked->number} net pay"],
                    ['account_id' => (int) $bank->account_id, 'debit' => '0', 'credit' => $net, 'description' => "Payroll {$locked->number} payment"],
                ],
            ], $userId);

            $locked->update([
                'status' => PayrollRunStatus::Paid,
                'paid_at' => $paidAt,
                'paid_by' => $userId,
                'bank_account_id' => $bank->id,
                'payment_journal_entry_id' => $entry->id,
            ]);
            $locked->payslips()->update(['status' => PayrollRunStatus::Paid->value]);

            $this->audit->log('payroll.run_paid', $locked, ['status' => PayrollRunStatus::Finalized->value], [
                'status' => PayrollRunStatus::Paid->value,
                'bank_account_id' => $bank->id,
                'payment_journal_entry_id' => $entry->id,
                'total_net' => $net,
            ]);

            return $locked;
        });
    }
}
