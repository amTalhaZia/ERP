<?php

namespace App\Services\Reports\Definitions\Inventory;

use App\Services\Reports\Definitions\QueryReportDefinition;
use App\Services\Reports\ReportContext;
use App\Support\Money\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * On-hand quantity × weighted average cost per product and warehouse (same
 * basis as the inventory valuation endpoint).
 */
class InventoryValuationReport extends QueryReportDefinition
{
    public function key(): string
    {
        return 'inventory.valuation';
    }

    public function module(): string
    {
        return 'inventory';
    }

    public function name(): string
    {
        return 'Inventory valuation';
    }

    public function description(): string
    {
        return 'Current stock value per product and warehouse at weighted average cost.';
    }

    public function permissions(): array
    {
        return ['inventory.view', 'products.view'];
    }

    public function params(): array
    {
        return [self::branchParam()];
    }

    public function columns(array $params): array
    {
        return [
            self::col('sku', 'SKU'),
            self::col('product', 'Product'),
            self::col('category', 'Category'),
            self::col('warehouse', 'Warehouse'),
            self::col('quantity_on_hand', 'On hand', 'number'),
            self::col('average_cost', 'Average cost', 'money'),
            self::col('stock_value', 'Value', 'money'),
        ];
    }

    protected function query(ReportContext $context, array $params): Builder
    {
        return $this->base($context, $params)
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.category_id')
            ->orderBy('p.name')->orderBy('w.name')->orderBy('sl.id')
            ->select([
                'sl.id', 'p.sku', 'p.name as product', 'pc.name as category', 'w.name as warehouse',
                'sl.quantity_on_hand', 'sl.average_cost',
                DB::raw('sl.quantity_on_hand * sl.average_cost as stock_value'),
            ]);
    }

    public function totals(ReportContext $context, array $params): ?array
    {
        $row = $this->base($context, $params)
            ->selectRaw('COALESCE(SUM(sl.quantity_on_hand), 0) as qty, COALESCE(SUM(sl.quantity_on_hand * sl.average_cost), 0) as value')
            ->first();

        return [
            'sku' => 'Total',
            'quantity_on_hand' => Money::normalize((string) ($row->qty ?? '0')),
            'stock_value' => Money::normalize((string) ($row->value ?? '0')),
        ];
    }

    protected function map(object $row): array
    {
        return [
            'sku' => $row->sku,
            'product' => $row->product,
            'category' => $row->category,
            'warehouse' => $row->warehouse,
            'quantity_on_hand' => Money::normalize((string) $row->quantity_on_hand),
            'average_cost' => Money::normalize((string) $row->average_cost),
            'stock_value' => Money::normalize((string) $row->stock_value),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function base(ReportContext $context, array $params): Builder
    {
        return DB::table('stock_levels as sl')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->join('warehouses as w', 'w.id', '=', 'sl.warehouse_id')
            ->where('sl.company_id', $context->companyId())
            ->where('sl.quantity_on_hand', '!=', 0)
            ->when($params['branch_id'] ?? null, fn (Builder $q, int $branchId) => $q->where('w.branch_id', $branchId));
    }
}
