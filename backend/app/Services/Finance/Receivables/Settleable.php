<?php

namespace App\Services\Finance\Receivables;

use App\Enums\Finance\PaymentPartnerType;

/**
 * A posted sub-ledger document that payments can be allocated against
 * (invoice, bill, expense claim). Implementations keep `amount_paid`,
 * `balance_due` and `status` consistent; callers hold a row lock.
 */
interface Settleable
{
    /** `payment_allocations.allocatable_type` value. */
    public function allocatableType(): string;

    public function partnerType(): PaymentPartnerType;

    public function partnerId(): int;

    /** Posted and not fully settled / voided. */
    public function acceptsPayments(): bool;

    public function outstanding(): string;

    /** Add $amount to amount_paid, reduce balance_due, derive status. Does not save. */
    public function applyPayment(string $amount): void;

    /** Undo applyPayment() (payment voided). Does not save. */
    public function revertPayment(string $amount): void;
}
