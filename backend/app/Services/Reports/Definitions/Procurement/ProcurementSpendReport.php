<?php

namespace App\Services\Reports\Definitions\Procurement;

use App\Services\Procurement\ProcurementReportService;
use App\Services\Reports\Definitions\ReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Support\Carbon;

class ProcurementSpendReport extends ReportDefinition
{
    public function __construct(private readonly ProcurementReportService $procurement) {}

    public function key(): string
    {
        return 'procurement.spend';
    }

    public function module(): string
    {
        return 'procurement';
    }

    public function name(): string
    {
        return 'Procurement spend';
    }

    public function description(): string
    {
        return 'Committed purchase-order spend grouped by supplier, month or department.';
    }

    public function permissions(): array
    {
        return ['purchase_orders.view'];
    }

    public function params(): array
    {
        return [
            self::dateParam('from', 'From'),
            self::dateParam('to', 'To'),
            self::selectParam('group_by', 'Group by', ['supplier' => 'Supplier', 'month' => 'Month', 'department' => 'Department']),
        ];
    }

    public function defaults(ReportContext $context): array
    {
        return [
            'from' => Carbon::parse($context->today())->startOfYear()->toDateString(),
            'to' => $context->today(),
            'group_by' => 'supplier',
        ];
    }

    public function columns(array $params): array
    {
        return [
            self::col('label', match ($params['group_by'] ?? 'supplier') {
                'month' => 'Month',
                'department' => 'Department',
                default => 'Supplier',
            }),
            self::col('order_count', 'Orders', 'number'),
            self::col('total', 'Total', 'money'),
        ];
    }

    protected function compute(ReportContext $context, array $params): array
    {
        $report = $this->procurement->spend($context->companyId(), $params['from'], $params['to'], $params['group_by']);

        return [
            'rows' => array_map(fn (array $row) => [
                'label' => $row['label'],
                'order_count' => $row['order_count'],
                'total' => $row['total'],
            ], $report['rows']),
            'totals' => ['label' => 'Total', 'order_count' => $report['order_count'], 'total' => $report['total']],
        ];
    }
}
