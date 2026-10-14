<?php

namespace App\Services\Reports\Definitions\Sales;

use App\Enums\Sales\SalesOrderStatus;
use App\Services\Reports\Definitions\QueryReportDefinition;
use App\Services\Reports\ReportContext;
use App\Support\Money\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesOrdersRegisterReport extends QueryReportDefinition
{
    public function key(): string
    {
        return 'sales.orders_register';
    }

    public function module(): string
    {
        return 'sales';
    }

    public function name(): string
    {
        return 'Sales orders register';
    }

    public function description(): string
    {
        return 'Every sales order in a period with customer, status and totals.';
    }

    public function permissions(): array
    {
        return ['sales_orders.view'];
    }

    public function params(): array
    {
        $statuses = [];
        foreach (SalesOrderStatus::cases() as $status) {
            $statuses[$status->value] = Str::headline($status->value);
        }

        return [
            self::dateParam('from', 'From'),
            self::dateParam('to', 'To'),
            self::selectParam('status', 'Status', $statuses),
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
            self::col('customer', 'Customer'),
            self::col('status', 'Status'),
            self::col('currency', 'Currency'),
            self::col('subtotal', 'Subtotal', 'money'),
            self::col('tax_total', 'Tax', 'money'),
            self::col('total', 'Total', 'money'),
        ];
    }

    protected function query(ReportContext $context, array $params): Builder
    {
        return DB::table('sales_orders as so')
            ->leftJoin('customers as c', 'c.id', '=', 'so.customer_id')
            ->where('so.company_id', $context->companyId())
            ->whereNull('so.deleted_at')
            ->whereBetween('so.order_date', [$params['from'], $params['to']])
            ->when($params['status'] ?? null, fn (Builder $q, string $status) => $q->where('so.status', $status))
            ->orderBy('so.order_date')->orderBy('so.id')
            ->select(['so.id', 'so.number', 'so.order_date', 'c.name as customer', 'so.status', 'so.currency', 'so.subtotal', 'so.tax_total', 'so.total']);
    }

    protected function map(object $row): array
    {
        return [
            'number' => $row->number,
            'order_date' => Carbon::parse($row->order_date)->toDateString(),
            'customer' => $row->customer,
            'status' => $row->status,
            'currency' => $row->currency,
            'subtotal' => Money::normalize((string) $row->subtotal),
            'tax_total' => Money::normalize((string) $row->tax_total),
            'total' => Money::normalize((string) $row->total),
        ];
    }
}
