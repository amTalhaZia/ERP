<?php

namespace App\Actions\Finance\Receivables;

use App\Enums\Finance\BillStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\Bill;
use App\Models\Finance\JournalEntry;
use App\Services\Finance\LedgerPoster;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Voids a bill. A posted bill is reversed in the GL (reversing entry
 * dated today); a bill with payments allocated is refused (409) — void the
 * payments first.
 */
class VoidBillAction
{
    public function __construct(
        private readonly LedgerPoster $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Bill $bill, string $reason, ?int $userId = null): Bill
    {
        return DB::transaction(function () use ($bill, $reason, $userId): Bill {
            $locked = Bill::query()->lockForUpdate()->findOrFail($bill->id);

            if ($locked->status === BillStatus::Void) {
                throw FinanceException::conflict("Bill {$locked->number} is already void.");
            }

            if (Money::compare((string) $locked->amount_paid, '0') > 0
                || in_array($locked->status, [BillStatus::PartiallyPaid, BillStatus::Paid], true)) {
                throw FinanceException::conflict("Bill {$locked->number} has payments allocated; void those payments first.", 'finance.has_payments');
            }

            $reversalId = null;

            if ($locked->journal_entry_id !== null) {
                $entry = JournalEntry::query()->findOrFail($locked->journal_entry_id);
                $reversalId = $this->ledger->reverse($entry, now()->toDateString(), "Void bill {$locked->number}: {$reason}", $userId)->id;
            }

            $before = ['status' => $locked->status->value];

            $locked->update([
                'status' => BillStatus::Void,
                'voided_at' => now(),
                'void_reason' => $reason,
                'balance_due' => '0',
            ]);

            $this->audit->log('bills.voided', $locked, $before, [
                'status' => BillStatus::Void->value,
                'reason' => $reason,
                'reversal_entry_id' => $reversalId,
            ]);

            return $locked;
        });
    }
}
