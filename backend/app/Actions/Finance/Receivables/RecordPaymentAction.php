<?php

namespace App\Actions\Finance\Receivables;

use App\Enums\Finance\JournalSourceType;
use App\Enums\Finance\PaymentPartnerType;
use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Services\Finance\LedgerPoster;
use App\Services\Finance\Receivables\JournalLinesBuilder;
use App\Services\Finance\Receivables\Settleable;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records and posts a receipt (customer) or payment (supplier / employee),
 * fully allocated to open documents of that partner.
 *
 * Rules: allocations must sum exactly to the amount (unallocated customer
 * credit is not supported — 422); each allocation ≤ the document's balance
 * due; documents must belong to the same tenant and partner, be posted and
 * open, and share the payment currency. Documents are row-locked.
 *
 * Posting:
 *   receipt  (customer): Dr bank GL account        / Cr 1100 AR (partner customer)
 *   payment  (supplier): Dr 2000 AP (partner sup.)  / Cr bank GL account
 *   payment  (employee): Dr 2300 Accrued Liab. (partner employee) / Cr bank GL account
 */
class RecordPaymentAction
{
    public function __construct(
        private readonly LedgerPoster $ledger,
        private readonly DocumentNumberer $numberer,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{partner_type: string, partner_id: int, payment_date: string, method: string, bank_account_id: int, amount: string|int|float, currency?: ?string, reference?: ?string, notes?: ?string, branch_id?: ?int, allocations: list<array{type: string, id: int, amount: string|int|float}>}  $data
     */
    public function handle(array $data, ?int $userId = null): Payment
    {
        $partnerType = PaymentPartnerType::from($data['partner_type']);
        $type = $partnerType->paymentType();
        $amount = Money::normalize((string) $data['amount']);
        $allocations = $data['allocations'];
        ksort($allocations);
        $allocations = array_values($allocations);

        $allocated = Money::sum(array_map(fn (array $a) => Money::normalize((string) $a['amount']), $allocations));

        if (! Money::equals($allocated, $amount)) {
            throw ValidationException::withMessages([
                'allocations' => "Allocations ({$allocated}) must equal the payment amount ({$amount}); unallocated amounts are not supported.",
            ]);
        }

        return DB::transaction(function () use ($data, $partnerType, $type, $amount, $allocations, $userId): Payment {
            $bank = BankAccount::query()->findOrFail($data['bank_account_id']);
            $currency = strtoupper($data['currency'] ?? $bank->currency);

            /** @var list<array{0: Model&Settleable, 1: string}> $documents */
            $documents = [];

            foreach ($allocations as $index => $allocation) {
                $documents[] = [$this->lockDocument($allocation, $index, $partnerType, (int) $data['partner_id'], $currency), Money::normalize((string) $allocation['amount'])];
            }

            $payment = Payment::query()->create([
                'number' => $this->numberer->next($type->value),
                'type' => $type,
                'partner_type' => $partnerType,
                'partner_id' => (int) $data['partner_id'],
                'payment_date' => $data['payment_date'],
                'method' => $data['method'],
                'bank_account_id' => $bank->id,
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'status' => PaymentStatus::Posted,
                'created_by' => $userId,
            ]);

            foreach ($documents as [$document, $share]) {
                $document->applyPayment($share);
                $document->save();

                $payment->allocations()->create([
                    'allocatable_type' => $document->allocatableType(),
                    'allocatable_id' => $document->getKey(),
                    'amount' => $share,
                ]);
            }

            $entry = $this->ledger->post([
                'company_id' => (int) $payment->company_id,
                'entry_date' => $payment->payment_date->toDateString(),
                'memo' => ucfirst($type->value)." {$payment->number}",
                'reference' => $payment->reference ?? $payment->number,
                'source_type' => JournalSourceType::Payment->value,
                'source_id' => $payment->id,
                'branch_id' => $payment->branch_id,
                'lines' => $this->journalLines($partnerType, (int) $payment->partner_id, (int) $bank->account_id, $amount, $payment->number),
            ], $userId);

            $payment->update(['journal_entry_id' => $entry->id]);

            $this->audit->log("payments.{$type->value}_recorded", $payment, null, [
                'amount' => $amount,
                'partner_type' => $partnerType->value,
                'partner_id' => (int) $payment->partner_id,
                'allocations' => array_map(fn (array $pair) => [$pair[0]->allocatableType(), $pair[0]->getKey(), $pair[1]], $documents),
                'journal_entry_id' => $entry->id,
            ]);

            return $payment;
        });
    }

    /**
     * @param  array{type: string, id: int, amount: string|int|float}  $allocation
     * @return Model&Settleable
     */
    private function lockDocument(array $allocation, int $index, PaymentPartnerType $partnerType, int $partnerId, string $currency): Model
    {
        $class = PaymentAllocation::TYPES[$allocation['type']] ?? null;

        if ($class === null || $allocation['type'] !== $partnerType->allocatableType()) {
            throw ValidationException::withMessages([
                "allocations.{$index}.type" => "A {$partnerType->value} payment can only settle {$partnerType->allocatableType()} documents.",
            ]);
        }

        /** @var (Model&Settleable)|null $document */
        $document = $class::query()->lockForUpdate()->find($allocation['id']);

        if ($document === null || $document->partnerId() !== $partnerId) {
            throw ValidationException::withMessages(["allocations.{$index}.id" => 'The selected document is invalid for this partner.']);
        }

        if (! $document->acceptsPayments()) {
            throw ValidationException::withMessages(["allocations.{$index}.id" => 'The selected document is not open for payment.']);
        }

        if (strtoupper((string) $document->getAttribute('currency')) !== $currency) {
            throw ValidationException::withMessages(["allocations.{$index}.id" => 'The document currency differs from the payment currency.']);
        }

        if (Money::compare(Money::normalize((string) $allocation['amount']), $document->outstanding()) > 0) {
            throw ValidationException::withMessages([
                "allocations.{$index}.amount" => "The allocation exceeds the document's balance due ({$document->outstanding()}).",
            ]);
        }

        return $document;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function journalLines(PaymentPartnerType $partnerType, int $partnerId, int $bankAccountId, string $amount, string $number): array
    {
        $partner = ['partner_type' => $partnerType->value, 'partner_id' => $partnerId, 'description' => $number];
        $bank = ['account_id' => $bankAccountId];

        $builder = new JournalLinesBuilder;

        return match ($partnerType) {
            PaymentPartnerType::Customer => $builder
                ->debit($bank, $amount)
                ->credit(['account_code' => ChartOfAccountsTemplate::ACCOUNTS_RECEIVABLE], $amount, $partner)
                ->lines(),
            PaymentPartnerType::Supplier => $builder
                ->debit(['account_code' => ChartOfAccountsTemplate::ACCOUNTS_PAYABLE], $amount, $partner)
                ->credit($bank, $amount)
                ->lines(),
            PaymentPartnerType::Employee => $builder
                ->debit(['account_code' => ChartOfAccountsTemplate::ACCRUED_LIABILITIES], $amount, $partner)
                ->credit($bank, $amount)
                ->lines(),
        };
    }
}
