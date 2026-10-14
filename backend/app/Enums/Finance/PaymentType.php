<?php

namespace App\Enums\Finance;

/** Receipt = money in (from a customer); Payment = money out (to a supplier or employee). */
enum PaymentType: string
{
    case Receipt = 'receipt';
    case Payment = 'payment';

    /** DocumentNumberer type: RCPT-… for receipts, PAY-… for payments. */
    public function numberType(): string
    {
        return $this->value;
    }
}
