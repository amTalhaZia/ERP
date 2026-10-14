<?php

namespace App\Services\Reports\Definitions\Sales;

use App\Services\Reports\Definitions\ReportDefinition;
use App\Services\Reports\ReportContext;
use App\Services\Sales\SalesReports;
use Illuminate\Support\Carbon;

/**
 * Quotation / order volumes and conversion via SalesReports::performance()
 * (Eloquent tenant scope — the export job sets the tenant context).
 */
class SalesPerformanceReport extends ReportDefinition
{
    public function __construct(private readonly SalesReports $sales) {}

    public function key(): string
    {
        return 'sales.performance';
    }

    public function module(): string
    {
        return 'sales';
    }

    public function name(): string
    {
        return 'Sales performance';
    }

    public function description(): string
    {
        return 'Quotations, approved orders and conversion rate grouped by month, customer or sales rep.';
    }

    public function permissions(): array
    {
        return ['sales_orders.view', 'quotations.view'];
    }

    public function params(): array
    {
        return [
            self::dateParam('from', 'From'),
            self::dateParam('to', 'To'),
            self::selectParam('group_by', 'Group by', ['month' => 'Month', 'customer' => 'Customer', 'rep' => 'Sales rep']),
        ];
    }

    public function defaults(ReportContext $context): array
    {
        return [
            'from' => Carbon::parse($context->today())->startOfYear()->toDateString(),
            'to' => $context->today(),
            'group_by' => 'month',
        ];
    }

    public function columns(array $params): array
    {
        return [
            self::col('group', match ($params['group_by'] ?? 'month') {
                'customer' => 'Customer',
                'rep' => 'Sales rep',
                default => 'Month',
            }),
            self::col('quotations', 'Quotations', 'number'),
            self::col('quotations_total', 'Quoted value', 'money'),
            self::col('converted', 'Converted', 'number'),
            self::col('orders', 'Orders', 'number'),
            self::col('orders_total', 'Order value', 'money'),
            self::col('conversion_rate', 'Conversion %', 'number'),
        ];
    }

    protected function compute(ReportContext $context, array $params): array
    {
        $groupBy = $params['group_by'];
        $report = $this->sales->performance(Carbon::parse($params['from']), Carbon::parse($params['to']), $groupBy);

        $rows = array_map(function (array $row) use ($groupBy): array {
            $label = $row[$groupBy] ?? null;

            return [
                'group' => is_array($label) ? ($label['name'] ?? null) : ($label ?? 'Unassigned'),
                'quotations' => $row['quotations']['count'],
                'quotations_total' => $row['quotations']['total'],
                'converted' => $row['quotations']['converted'],
                'orders' => $row['orders']['count'],
                'orders_total' => $row['orders']['total'],
                'conversion_rate' => $row['conversion_rate'],
            ];
        }, $report['rows']);

        $totals = $report['totals'];

        return [
            'rows' => $rows,
            'totals' => [
                'group' => 'Total',
                'quotations' => $totals['quotations']['count'],
                'quotations_total' => $totals['quotations']['total'],
                'converted' => $totals['quotations']['converted'],
                'orders' => $totals['orders']['count'],
                'orders_total' => $totals['orders']['total'],
                'conversion_rate' => $totals['conversion_rate'],
            ],
        ];
    }
}
