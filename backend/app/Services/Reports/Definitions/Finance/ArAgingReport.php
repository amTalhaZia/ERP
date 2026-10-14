<?php

namespace App\Services\Reports\Definitions\Finance;

use App\Enums\Finance\PaymentPartnerType;

class ArAgingReport extends AgingReport
{
    public function key(): string
    {
        return 'finance.ar_aging';
    }

    public function name(): string
    {
        return 'Accounts receivable aging';
    }

    public function description(): string
    {
        return 'Outstanding customer invoices bucketed by days past due.';
    }

    public function permissions(): array
    {
        return ['reports.financial.view', 'invoices.view'];
    }

    protected function partnerType(): PaymentPartnerType
    {
        return PaymentPartnerType::Customer;
    }
}
