<?php

namespace App\Services\Reports\Definitions\Finance;

use App\Services\Finance\ReportService;
use App\Services\Reports\Definitions\ReportDefinition;
use App\Services\Reports\ReportContext;

class TrialBalanceReport extends ReportDefinition
{
    public function __construct(private readonly ReportService $finance) {}

    public function key(): string
    {
        return 'finance.trial_balance';
    }

    public function module(): string
    {
        return 'finance';
    }

    public function name(): string
    {
        return 'Trial balance';
    }

    public function description(): string
    {
        return 'Debit and credit totals per account from posted journals as of a date.';
    }

    public function permissions(): array
    {
        return ['reports.financial.view'];
    }

    public function params(): array
    {
        return [self::dateParam('as_of', 'As of'), self::branchParam()];
    }

    public function defaults(ReportContext $context): array
    {
        return ['as_of' => $context->today()];
    }

    public function columns(array $params): array
    {
        return [
            self::col('code', 'Code'),
            self::col('name', 'Account'),
            self::col('type', 'Type'),
            self::col('debit_total', 'Debit', 'money'),
            self::col('credit_total', 'Credit', 'money'),
            self::col('balance', 'Balance', 'money'),
        ];
    }

    protected function compute(ReportContext $context, array $params): array
    {
        $report = $this->finance->trialBalance($context->company, $params['as_of'], $params['branch_id'] ?? null);

        return [
            'rows' => array_map(fn (array $row) => [
                'code' => $row['code'],
                'name' => $row['name'],
                'type' => $row['type'],
                'debit_total' => $row['debit_total'],
                'credit_total' => $row['credit_total'],
                'balance' => $row['balance'],
            ], $report['rows']),
            'totals' => [
                'name' => 'Total',
                'debit_total' => $report['totals']['debit_total'],
                'credit_total' => $report['totals']['credit_total'],
            ],
        ];
    }
}
