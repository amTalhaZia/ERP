<?php

namespace App\Actions\Finance\Receivables;

use App\Enums\Finance\BillStatus;
use App\Enums\Finance\JournalSourceType;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\Bill;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Services\Finance\LedgerPoster;
use App\Services\Finance\Receivables\JournalLinesBuilder;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Approves a supplier bill and posts it to the general ledger:
 *
 *   Dr expense / inventory account of each line      Σ line_subtotal per account
 *   Dr tax rate's account (default 2100, input tax)  Σ tax_amount per tax account
 *     Cr 2000 Accounts Payable (partner supplier)    total
 */
class PostBillAction
{
    public function __construct(
        private readonly LedgerPoster $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Bill $bill, ?int $userId = null): Bill
    {
        return DB::transaction(function () use ($bill, $userId): Bill {
            $locked = Bill::query()->lockForUpdate()->findOrFail($bill->id);

            if (! $locked->status->isEditable()) {
                throw FinanceException::conflict("Bill {$locked->number} is already {$locked->status->value}.");
            }

            $locked->load('lines.taxRate');

            if ($locked->lines->isEmpty() || Money::compare((string) $locked->total, '0') <= 0) {
                throw FinanceException::conflict('A bill must have lines and a positive total to be approved.');
            }

            $builder = new JournalLinesBuilder;

            foreach ($locked->lines as $line) {
                $builder->debit(['account_id' => (int) $line->account_id], (string) $line->line_subtotal);

                if (! Money::isZero((string) $line->tax_amount)) {
                    $taxAccount = $line->taxRate?->account_id
                        ? ['account_id' => (int) $line->taxRate->account_id]
                        : ['account_code' => ChartOfAccountsTemplate::TAX_PAYABLE];
                    $builder->debit($taxAccount, (string) $line->tax_amount, ['tax_rate_id' => $line->tax_rate_id]);
                }
            }

            $builder->credit(
                ['account_code' => ChartOfAccountsTemplate::ACCOUNTS_PAYABLE],
                (string) $locked->total,
                ['partner_type' => 'supplier', 'partner_id' => (int) $locked->supplier_id, 'description' => "Bill {$locked->number} ({$locked->supplier_reference})"],
            );

            $entry = $this->ledger->post([
                'company_id' => (int) $locked->company_id,
                'entry_date' => $locked->bill_date->toDateString(),
                'memo' => "Supplier bill {$locked->number}",
                'reference' => $locked->supplier_reference,
                'source_type' => JournalSourceType::Bill->value,
                'source_id' => $locked->id,
                'branch_id' => $locked->branch_id,
                'lines' => $builder->lines(),
            ], $userId);

            $before = ['status' => $locked->status->value];

            $locked->update([
                'status' => BillStatus::Approved,
                'approved_by' => $userId,
                'approved_at' => now(),
                'journal_entry_id' => $entry->id,
                'amount_paid' => '0',
                'balance_due' => (string) $locked->total,
            ]);

            $this->audit->log('bills.approved', $locked, $before, [
                'status' => BillStatus::Approved->value,
                'journal_entry_id' => $entry->id,
                'total' => (string) $locked->total,
            ]);

            return $locked;
        });
    }
}
