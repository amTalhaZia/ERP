<?php

namespace App\Actions\Finance\Receivables;

use App\Enums\Finance\PaymentStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Services\Finance\LedgerPoster;
use App\Services\Finance\Receivables\Settleable;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Voids a posted payment: reverses its journal entry (dated today) and rolls
 * every allocation back onto its document (amount_paid / balance_due /
 * status). Allocation rows are kept as history.
 */
class VoidPaymentAction
{
    public function __construct(
        private readonly LedgerPoster $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Payment $payment, string $reason, ?int $userId = null): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $userId): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $locked->isPosted()) {
                throw FinanceException::conflict("Payment {$locked->number} is already void.");
            }

            foreach ($locked->allocations()->get() as $allocation) {
                $class = PaymentAllocation::TYPES[$allocation->allocatable_type];

                /** @var Model&Settleable $document */
                $document = $class::query()->lockForUpdate()->findOrFail($allocation->allocatable_id);
                $document->revertPayment((string) $allocation->amount);
                $document->save();
            }

            $reversalId = null;

            if ($locked->journal_entry_id !== null) {
                $entry = JournalEntry::query()->findOrFail($locked->journal_entry_id);
                $reversalId = $this->ledger->reverse($entry, now()->toDateString(), "Void {$locked->number}: {$reason}", $userId)->id;
            }

            $locked->update([
                'status' => PaymentStatus::Void,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            $this->audit->log('payments.voided', $locked, ['status' => PaymentStatus::Posted->value], [
                'status' => PaymentStatus::Void->value,
                'reason' => $reason,
                'reversal_entry_id' => $reversalId,
            ]);

            return $locked;
        });
    }
}
