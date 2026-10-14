<?php

namespace App\Services\Reports\Definitions\Finance;

use App\Services\Finance\ReportService;
use App\Services\Reports\Definitions\ReportDefinition;
use App\Services\Reports\ReportContext;

class BalanceSheetReport extends ReportDefinition
{
    public function __construct(private readonly ReportService $finance) {}

    public function key(): string
    {
        return 'finance.balance_sheet';
    }

    public function module(): string
    {
        return 'finance';
    }

    public function name(): string
    {
        return 'Balance sheet';
    }

    public function description(): string
    {
        return 'Assets, liabilities and equity (including retained and current-year earnings) as of a date.';
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
            self::col('section', 'Section'),
            self::col('code', 'Code'),
            self::col('name', 'Account'),
            self::col('amount', 'Amount', 'money'),
        ];
    }

    protected function compute(ReportContext $context, array $params): array
    {
        $report = $this->finance->balanceSheet($context->company, $params['as_of'], $params['branch_id'] ?? null);

        $rows = [];
        foreach (['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $label) {
            foreach ($report[$key]['groups'] as $group) {
                foreach ($group['accounts'] as $account) {
                    $rows[] = ['section' => $label, 'code' => $account['code'], 'name' => $account['name'], 'amount' => $account['amount']];
                }
            }

            if ($key === 'equity') {
                $rows[] = ['section' => $label, 'code' => null, 'name' => 'Retained earnings (prior years)', 'amount' => $report['equity']['retained_earnings_prior_years']];
                $rows[] = ['section' => $label, 'code' => null, 'name' => 'Current year earnings', 'amount' => $report['equity']['current_year_earnings']];
                $rows[] = ['section' => $label, 'code' => null, 'name' => 'Total equity', 'amount' => $report['equity']['total_equity']];
            } else {
                $rows[] = ['section' => $label, 'code' => null, 'name' => 'Total '.strtolower($label), 'amount' => $report[$key]['total']];
            }
        }

        return [
            'rows' => $rows,
            'totals' => ['name' => 'Total liabilities and equity', 'amount' => $report['total_liabilities_and_equity']],
        ];
    }
}
