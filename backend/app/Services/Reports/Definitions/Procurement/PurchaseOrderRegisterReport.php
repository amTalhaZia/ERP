<?php

namespace App\Services\Reports\Definitions\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Services\Reports\Definitions\QueryReportDefinition;
use App\Services\Reports\ReportContext;
use App\Support\Money\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderRegisterReport extends QueryReportDefinition
{
    public function key(): string
    {
        return 'procurement.po_register';
    }

    public function module(): string
    {
        return 'procurement';
    }

    public function name(): string
    {
        return 'Purchase orders register';
    }

    public function description(): string
    {
        return 'Every purchase order in a period with supplier, branch, status and totals.';
    }

    public function permissions(): array
    {
        return ['purchase_orders.view'];
    }

    public function params(): array
    {
        $statuses = [];
        foreach (PurchaseOrderStatus::cases() as $status) {
            $statuses[$status->value] = Str::headline($status->value);
        }

        return [
            self::dateParam('from', 'From'),
            self::dateParam('to', 'To'),
            self::selectParam('status', 'Status', $statuses),
            self::branchParam(),
        ];
    }

    public function defaults(ReportContext $context): array
    {
        return ['from' => $context->monthStart(), 'to' => $context->today()];
    }

    public function columns(array $params): array
    {
        return [
            self::col('number', 'Number'),
            self::col('order_date', 'Date', 'date'),
            self::col('supplier', 'Supplier'),
            self::col('branch', 'Branch'),
            self::col('status', 'Status'),
            self::col('currency', 'Currency'),
            self::col('subtotal', 'Subtotal', 'money'),
            self::col('tax_total', 'Tax', 'money'),
            self::col('total', 'Total', 'money'),
        ];
    }

    protected function query(ReportContext $context, array $params): Builder
    {
        return DB::table('purchase_orders as po')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('branches as b', 'b.id', '=', 'po.branch_id')
            ->where('po.company_id', $context->companyId())
            ->whereBetween('po.order_date', [$params['from'], $params['to']])
            ->when($params['status'] ?? null, fn (Builder $q, string $status) => $q->where('po.status', $status))
            ->when($params['branch_id'] ?? null, fn (Builder $q, int $branchId) => $q->where('po.branch_id', $branchId))
            ->orderBy('po.order_date')->orderBy('po.id')
            ->select(['po.id', 'po.number', 'po.order_date', 's.name as supplier', 'b.name as branch', 'po.status', 'po.currency', 'po.subtotal', 'po.tax_total', 'po.total']);
    }

    protected function map(object $row): array
    {
        return [
            'number' => $row->number,
            'order_date' => Carbon::parse($row->order_date)->toDateString(),
            'supplier' => $row->supplier,
            'branch' => $row->branch,
            'status' => $row->status,
            'currency' => $row->currency,
            'subtotal' => Money::normalize((string) $row->subtotal),
            'tax_total' => Money::normalize((string) $row->tax_total),
            'total' => Money::normalize((string) $row->total),
        ];
    }
}
