<?php

namespace App\Services\Reports\Definitions\Finance;

use App\Enums\Finance\PaymentPartnerType;
use App\Services\Finance\Receivables\ReceivablesReportService;
use App\Services\Reports\Definitions\ReportDefinition;
use App\Services\Reports\ReportContext;

/**
 * AR (customers / invoices) or AP (suppliers / bills) aging as of a date,
 * straight from ReceivablesReportService::aging().
 */
abstract class AgingReport extends ReportDefinition
{
    public function __construct(private readonly ReceivablesReportService $receivables) {}

    abstract protected function partnerType(): PaymentPartnerType;

    public function module(): string
    {
        return 'finance';
    }

    public function params(): array
    {
        return [self::dateParam('as_of', 'As of')];
    }

    public function defaults(ReportContext $context): array
    {
        return ['as_of' => $context->today()];
    }

    public function columns(array $params): array
    {
        return [
            self::col('partner', $this->partnerType() === PaymentPartnerType::Customer ? 'Customer' : 'Supplier'),
            self::col('documents', 'Documents', 'number'),
            self::col('current', 'Current', 'money'),
            self::col('days_1_30', '1–30 days', 'money'),
            self::col('days_31_60', '31–60 days', 'money'),
            self::col('days_61_90', '61–90 days', 'money'),
            self::col('over_90', 'Over 90 days', 'money'),
            self::col('total', 'Total', 'money'),
        ];
    }

    protected function compute(ReportContext $context, array $params): array
    {
        $report = $this->receivables->aging($context->companyId(), $this->partnerType(), $params['as_of']);
        $keys = ['documents', 'current', 'days_1_30', 'days_31_60', 'days_61_90', 'over_90', 'total'];

        return [
            'rows' => array_map(
                fn (array $row) => ['partner' => $row['partner_name']] + array_intersect_key($row, array_flip($keys)),
                $report['rows'],
            ),
            'totals' => ['partner' => 'Total'] + array_intersect_key($report['totals'], array_flip($keys)),
        ];
    }
}
