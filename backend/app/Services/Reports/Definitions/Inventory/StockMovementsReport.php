<?php

namespace App\Services\Reports\Definitions\Inventory;

use App\Enums\Inventory\StockMovementType;
use App\Services\Reports\Definitions\QueryReportDefinition;
use App\Services\Reports\ReportContext;
use App\Support\Money\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockMovementsReport extends QueryReportDefinition
{
    public function key(): string
    {
        return 'inventory.movements';
    }

    public function module(): string
    {
        return 'inventory';
    }

    public function name(): string
    {
        return 'Stock movements';
    }

    public function description(): string
    {
        return 'Every stock movement in a period with quantity, cost and reference.';
    }

    public function permissions(): array
    {
        return ['inventory.view'];
    }

    public function params(): array
    {
        $types = [];
        foreach (StockMovementType::cases() as $type) {
            $types[$type->value] = Str::headline($type->value);
        }

        return [
            self::dateParam('from', 'From'),
            self::dateParam('to', 'To'),
            self::selectParam('type', 'Movement type', $types),
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
            self::col('moved_at', 'Date', 'date'),
            self::col('sku', 'SKU'),
            self::col('product', 'Product'),
            self::col('warehouse', 'Warehouse'),
            self::col('type', 'Type'),
            self::col('quantity', 'Quantity', 'number'),
            self::col('unit_cost', 'Unit cost', 'money'),
            self::col('total_cost', 'Total cost', 'money'),
            self::col('balance_after', 'Balance after', 'number'),
            self::col('reference', 'Reference'),
        ];
    }

    protected function query(ReportContext $context, array $params): Builder
    {
        return DB::table('stock_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->join('warehouses as w', 'w.id', '=', 'm.warehouse_id')
            ->where('m.company_id', $context->companyId())
            ->whereBetween('m.moved_at', [Carbon::parse($params['from'])->startOfDay(), Carbon::parse($params['to'])->endOfDay()])
            ->when($params['type'] ?? null, fn (Builder $q, string $type) => $q->where('m.type', $type))
            ->when($params['branch_id'] ?? null, fn (Builder $q, int $branchId) => $q->where('w.branch_id', $branchId))
            ->orderBy('m.moved_at')->orderBy('m.id')
            ->select([
                'm.id', 'm.moved_at', 'p.sku', 'p.name as product', 'w.name as warehouse', 'm.type',
                'm.quantity', 'm.unit_cost', 'm.total_cost', 'm.balance_after', 'm.reference_type', 'm.reference_id',
            ]);
    }

    protected function map(object $row): array
    {
        return [
            'moved_at' => Carbon::parse($row->moved_at, 'UTC')->toJSON(),
            'sku' => $row->sku,
            'product' => $row->product,
            'warehouse' => $row->warehouse,
            'type' => $row->type,
            'quantity' => Money::normalize((string) $row->quantity),
            'unit_cost' => Money::normalize((string) $row->unit_cost),
            'total_cost' => Money::normalize((string) $row->total_cost),
            'balance_after' => Money::normalize((string) $row->balance_after),
            'reference' => $row->reference_type ? $row->reference_type.' #'.$row->reference_id : null,
        ];
    }
}
