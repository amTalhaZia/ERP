<?php

namespace App\Services\Reports\Definitions\Finance;

use App\Enums\Finance\PaymentPartnerType;

class ApAgingReport extends AgingReport
{
    public function key(): string
    {
        return 'finance.ap_aging';
    }

    public function name(): string
    {
        return 'Accounts payable aging';
    }

    public function description(): string
    {
        return 'Outstanding supplier bills bucketed by days past due.';
    }

    public function permissions(): array
    {
        return ['reports.financial.view', 'bills.view'];
    }

    protected function partnerType(): PaymentPartnerType
    {
        return PaymentPartnerType::Supplier;
    }
}
