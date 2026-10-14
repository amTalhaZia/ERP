<?php

namespace App\Actions\Finance\Receivables;

use App\Enums\Finance\InvoiceStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\Invoice;
use App\Models\Finance\JournalEntry;
use App\Services\Finance\LedgerPoster;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Voids an invoice. A posted invoice is reversed in the GL (reversing entry
 * dated today); an invoice with payments allocated is refused (409) — void the
 * payments first.
 */
class VoidInvoiceAction
{
    public function __construct(
        private readonly LedgerPoster $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Invoice $invoice, string $reason, ?int $userId = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $userId): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->status === InvoiceStatus::Void) {
                throw FinanceException::conflict("Invoice {$locked->number} is already void.");
            }

            if (Money::compare((string) $locked->amount_paid, '0') > 0
                || in_array($locked->status, [InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid], true)) {
                throw FinanceException::conflict("Invoice {$locked->number} has payments allocated; void those payments first.", 'finance.has_payments');
            }

            $reversalId = null;

            if ($locked->journal_entry_id !== null) {
                $entry = JournalEntry::query()->findOrFail($locked->journal_entry_id);
                $reversalId = $this->ledger->reverse($entry, now()->toDateString(), "Void invoice {$locked->number}: {$reason}", $userId)->id;
            }

            $before = ['status' => $locked->status->value];

            $locked->update([
                'status' => InvoiceStatus::Void,
                'voided_at' => now(),
                'void_reason' => $reason,
                'balance_due' => '0',
            ]);

            $this->audit->log('invoices.voided', $locked, $before, [
                'status' => InvoiceStatus::Void->value,
                'reason' => $reason,
                'reversal_entry_id' => $reversalId,
            ]);

            return $locked;
        });
    }
}
