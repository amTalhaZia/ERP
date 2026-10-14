<?php

namespace App\Services\Reports\Definitions\Finance;

use App\Services\Finance\ReportService;
use App\Services\Reports\Definitions\ReportDefinition;
use App\Services\Reports\ReportContext;

class ProfitAndLossReport extends ReportDefinition
{
    public function __construct(private readonly ReportService $finance) {}

    public function key(): string
    {
        return 'finance.profit_and_loss';
    }

    public function module(): string
    {
        return 'finance';
    }

    public function name(): string
    {
        return 'Profit and loss';
    }

    public function description(): string
    {
        return 'Revenue, cost of sales and expenses per account for a period, with gross and net profit.';
    }

    public function permissions(): array
    {
        return ['reports.financial.view'];
    }

    public function params(): array
    {
        return [self::dateParam('from', 'From'), self::dateParam('to', 'To'), self::branchParam()];
    }

    public function defaults(ReportContext $context): array
    {
        return ['from' => $context->monthStart(), 'to' => $context->today()];
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
        $report = $this->finance->profitAndLoss($context->company, $params['from'], $params['to'], $params['branch_id'] ?? null);

        $rows = [];
        foreach (['revenue' => 'Revenue', 'cost_of_sales' => 'Cost of sales', 'expenses' => 'Expenses'] as $key => $label) {
            foreach ($report[$key]['groups'] as $group) {
                foreach ($group['accounts'] as $account) {
                    $rows[] = ['section' => $label, 'code' => $account['code'], 'name' => $account['name'], 'amount' => $account['amount']];
                }
            }
            $rows[] = ['section' => $label, 'code' => null, 'name' => 'Total '.strtolower($label), 'amount' => $report[$key]['total']];
        }

        $rows[] = ['section' => 'Summary', 'code' => null, 'name' => 'Gross profit', 'amount' => $report['gross_profit']];

        return [
            'rows' => $rows,
            'totals' => ['name' => 'Net profit', 'amount' => $report['net_profit']],
        ];
    }
}
