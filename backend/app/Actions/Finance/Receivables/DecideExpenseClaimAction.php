<?php

namespace App\Actions\Finance\Receivables;

use App\Enums\Finance\ExpenseClaimStatus;
use App\Enums\Finance\JournalSourceType;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\ExpenseClaim;
use App\Notifications\Finance\ExpenseClaimDecided;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Services\Finance\LedgerPoster;
use App\Services\Finance\Receivables\JournalLinesBuilder;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Approve or reject a Submitted expense claim, then notify the submitter.
 *
 * Approval posts:
 *   Dr each line's expense account                 Σ amount (net) per account
 *   Dr tax rate's account (default 2100)           Σ tax_amount
 *     Cr 2300 Accrued Liabilities (partner employee = submitting user)  total
 */
class DecideExpenseClaimAction
{
    public function __construct(
        private readonly LedgerPoster $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function approve(ExpenseClaim $claim, int $approverId): ExpenseClaim
    {
        $decided = DB::transaction(function () use ($claim, $approverId): ExpenseClaim {
            $locked = $this->lockSubmitted($claim);
            $locked->load('lines.taxRate');

            $builder = new JournalLinesBuilder;

            foreach ($locked->lines as $line) {
                $builder->debit(['account_id' => (int) $line->account_id], (string) $line->amount);

                if (! Money::isZero((string) $line->tax_amount)) {
                    $taxAccount = $line->taxRate?->account_id
                        ? ['account_id' => (int) $line->taxRate->account_id]
                        : ['account_code' => ChartOfAccountsTemplate::TAX_PAYABLE];
                    $builder->debit($taxAccount, (string) $line->tax_amount, ['tax_rate_id' => $line->tax_rate_id]);
                }
            }

            $builder->credit(
                ['account_code' => ChartOfAccountsTemplate::ACCRUED_LIABILITIES],
                (string) $locked->total,
                ['partner_type' => 'employee', 'partner_id' => (int) $locked->user_id, 'description' => "Expense claim {$locked->number}"],
            );

            $entry = $this->ledger->post([
                'company_id' => (int) $locked->company_id,
                'entry_date' => $locked->claim_date->toDateString(),
                'memo' => "Expense claim {$locked->number}",
                'reference' => $locked->number,
                'source_type' => JournalSourceType::ExpenseClaim->value,
                'source_id' => $locked->id,
                'branch_id' => $locked->branch_id,
                'lines' => $builder->lines(),
            ], $approverId);

            $locked->update([
                'status' => ExpenseClaimStatus::Approved,
                'approver_id' => $approverId,
                'approved_at' => now(),
                'journal_entry_id' => $entry->id,
                'amount_paid' => '0',
                'balance_due' => (string) $locked->total,
            ]);

            $this->audit->log('expense_claims.approved', $locked, ['status' => ExpenseClaimStatus::Submitted->value], [
                'status' => ExpenseClaimStatus::Approved->value,
                'journal_entry_id' => $entry->id,
                'total' => (string) $locked->total,
            ]);

            return $locked;
        });

        $decided->load('user')->user?->notify(new ExpenseClaimDecided($decided));

        return $decided;
    }

    public function reject(ExpenseClaim $claim, int $approverId, string $reason): ExpenseClaim
    {
        $decided = DB::transaction(function () use ($claim, $approverId, $reason): ExpenseClaim {
            $locked = $this->lockSubmitted($claim);

            $locked->update([
                'status' => ExpenseClaimStatus::Rejected,
                'approver_id' => $approverId,
                'rejection_reason' => $reason,
            ]);

            $this->audit->log('expense_claims.rejected', $locked, ['status' => ExpenseClaimStatus::Submitted->value], [
                'status' => ExpenseClaimStatus::Rejected->value,
                'reason' => $reason,
            ]);

            return $locked;
        });

        $decided->load('user')->user?->notify(new ExpenseClaimDecided($decided));

        return $decided;
    }

    private function lockSubmitted(ExpenseClaim $claim): ExpenseClaim
    {
        $locked = ExpenseClaim::query()->lockForUpdate()->findOrFail($claim->id);

        if ($locked->status !== ExpenseClaimStatus::Submitted) {
            throw FinanceException::conflict("Only submitted expense claims can be decided; {$locked->number} is {$locked->status->value}.");
        }

        return $locked;
    }
}
