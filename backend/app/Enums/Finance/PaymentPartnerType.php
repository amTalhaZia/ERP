<?php

namespace App\Enums\Finance;

/**
 * Counterparty of a receipt/payment. Each partner type settles exactly one
 * kind of document: customer → invoice, supplier → bill, employee (user id)
 * → expense_claim.
 */
enum PaymentPartnerType: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
    case Employee = 'employee';

    public function allocatableType(): string
    {
        return match ($this) {
            self::Customer => 'invoice',
            self::Supplier => 'bill',
            self::Employee => 'expense_claim',
        };
    }

    public function paymentType(): PaymentType
    {
        return $this === self::Customer ? PaymentType::Receipt : PaymentType::Payment;
    }
}
