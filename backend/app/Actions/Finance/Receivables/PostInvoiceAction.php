<?php

namespace App\Actions\Finance\Receivables;

use App\Enums\Finance\InvoiceStatus;
use App\Enums\Finance\JournalSourceType;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\Invoice;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Services\Finance\LedgerPoster;
use App\Services\Finance\Receivables\JournalLinesBuilder;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Approves (issues) an invoice and posts it to the general ledger:
 *
 *   Dr 1100 Accounts Receivable (partner customer)   total
 *     Cr revenue account of each line (default 4000)  Σ line_subtotal per account
 *     Cr tax rate's account (default 2100)            Σ tax_amount per tax account
 */
class PostInvoiceAction
{
    public function __construct(
        private readonly LedgerPoster $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Invoice $invoice, ?int $userId = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $userId): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $locked->status->isEditable()) {
                throw FinanceException::conflict("Invoice {$locked->number} is already {$locked->status->value}.");
            }

            $locked->load('lines.taxRate');

            if ($locked->lines->isEmpty() || Money::compare((string) $locked->total, '0') <= 0) {
                throw FinanceException::conflict('An invoice must have lines and a positive total to be approved.');
            }

            $partner = ['partner_type' => 'customer', 'partner_id' => (int) $locked->customer_id];
            $builder = (new JournalLinesBuilder)
                ->debit(['account_code' => ChartOfAccountsTemplate::ACCOUNTS_RECEIVABLE], (string) $locked->total, $partner + ['description' => "Invoice {$locked->number}"]);

            foreach ($locked->lines as $line) {
                $builder->credit(['account_id' => (int) $line->account_id], (string) $line->line_subtotal);

                if (! Money::isZero((string) $line->tax_amount)) {
                    $taxAccount = $line->taxRate?->account_id
                        ? ['account_id' => (int) $line->taxRate->account_id]
                        : ['account_code' => ChartOfAccountsTemplate::TAX_PAYABLE];
                    $builder->credit($taxAccount, (string) $line->tax_amount, ['tax_rate_id' => $line->tax_rate_id]);
                }
            }

            $entry = $this->ledger->post([
                'company_id' => (int) $locked->company_id,
                'entry_date' => $locked->invoice_date->toDateString(),
                'memo' => "Invoice {$locked->number}",
                'reference' => $locked->number,
                'source_type' => JournalSourceType::Invoice->value,
                'source_id' => $locked->id,
                'branch_id' => $locked->branch_id,
                'lines' => $builder->lines(),
            ], $userId);

            $before = ['status' => $locked->status->value];

            $locked->update([
                'status' => InvoiceStatus::Approved,
                'approved_by' => $userId,
                'approved_at' => now(),
                'journal_entry_id' => $entry->id,
                'amount_paid' => '0',
                'balance_due' => (string) $locked->total,
            ]);

            $this->audit->log('invoices.approved', $locked, $before, [
                'status' => InvoiceStatus::Approved->value,
                'journal_entry_id' => $entry->id,
                'total' => (string) $locked->total,
            ]);

            return $locked;
        });
    }
}
