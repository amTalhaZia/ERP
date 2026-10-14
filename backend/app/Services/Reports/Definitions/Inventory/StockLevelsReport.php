<?php

namespace App\Services\Reports\Definitions\Inventory;

use App\Services\Reports\Definitions\QueryReportDefinition;
use App\Services\Reports\ReportContext;
use App\Support\Money\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Total on-hand / reserved per active product against its reorder level.
 */
class StockLevelsReport extends QueryReportDefinition
{
    public function key(): string
    {
        return 'inventory.stock_levels';
    }

    public function module(): string
    {
        return 'inventory';
    }

    public function name(): string
    {
        return 'Stock levels';
    }

    public function description(): string
    {
        return 'On-hand and reserved quantity per product compared with its reorder level.';
    }

    public function permissions(): array
    {
        return ['inventory.view', 'products.view'];
    }

    public function params(): array
    {
        return [self::selectParam('stock', 'Stock', ['all' => 'All products', 'low' => 'Low stock only'])];
    }

    public function defaults(ReportContext $context): array
    {
        return ['stock' => 'all'];
    }

    public function columns(array $params): array
    {
        return [
            self::col('sku', 'SKU'),
            self::col('name', 'Product'),
            self::col('category', 'Category'),
            self::col('on_hand', 'On hand', 'number'),
            self::col('reserved', 'Reserved', 'number'),
            self::col('reorder_level', 'Reorder level', 'number'),
            self::col('status', 'Status'),
        ];
    }

    protected function query(ReportContext $context, array $params): Builder
    {
        $levels = DB::table('stock_levels')
            ->where('company_id', $context->companyId())
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_on_hand) as on_hand, SUM(quantity_reserved) as reserved');

        return DB::table('products as p')
            ->leftJoinSub($levels, 'lv', 'lv.product_id', '=', 'p.id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.category_id')
            ->where('p.company_id', $context->companyId())
            ->whereNull('p.deleted_at')
            ->where('p.is_active', true)
            ->where('p.type', 'goods')
            ->when(($params['stock'] ?? 'all') === 'low', fn (Builder $q) => $q
                ->where('p.reorder_level', '>', 0)
                ->whereRaw('COALESCE(lv.on_hand, 0) <= p.reorder_level'))
            ->orderBy('p.name')->orderBy('p.id')
            ->select([
                'p.id', 'p.sku', 'p.name', 'pc.name as category', 'p.reorder_level',
                DB::raw('COALESCE(lv.on_hand, 0) as on_hand'), DB::raw('COALESCE(lv.reserved, 0) as reserved'),
            ]);
    }

    protected function map(object $row): array
    {
        $onHand = Money::normalize((string) $row->on_hand);
        $reorder = Money::normalize((string) $row->reorder_level);
        $low = Money::compare($reorder, '0') > 0 && Money::compare($onHand, $reorder) <= 0;

        return [
            'sku' => $row->sku,
            'name' => $row->name,
            'category' => $row->category,
            'on_hand' => $onHand,
            'reserved' => Money::normalize((string) $row->reserved),
            'reorder_level' => $reorder,
            'status' => $low ? 'low' : 'ok',
        ];
    }
}
