<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Read-only aggregates over stock_levels / stock_movements. Values are
 * on_hand × average_cost (weighted average valuation; FIFO not implemented).
 */
class InventoryReportController extends ApiController
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function valuation(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', StockLevel::class);

        $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'group_by' => ['nullable', Rule::in(['category'])],
        ]);

        $base = StockLevel::query()->visibleBranches()->where('stock_levels.quantity_on_hand', '!=', 0);

        if ($warehouseId = $request->integer('warehouse_id')) {
            $base->where('stock_levels.warehouse_id', $warehouseId);
        }

        $warehouses = (clone $base)
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->selectRaw('stock_levels.warehouse_id, warehouses.name AS warehouse_name, warehouses.code AS warehouse_code')
            ->selectRaw('COUNT(DISTINCT stock_levels.product_id) AS item_count')
            ->selectRaw('SUM(stock_levels.quantity_on_hand) AS quantity_on_hand')
            ->selectRaw('SUM(stock_levels.quantity_on_hand * stock_levels.average_cost) AS stock_value')
            ->groupBy('stock_levels.warehouse_id', 'warehouses.name', 'warehouses.code')
            ->orderBy('warehouses.name')
            ->get()
            ->map(fn ($row) => [
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse_name' => $row->warehouse_name,
                'warehouse_code' => $row->warehouse_code,
                'item_count' => (int) $row->item_count,
                'quantity_on_hand' => Money::normalize((string) $row->quantity_on_hand),
                'stock_value' => Money::normalize((string) $row->stock_value),
            ])->values();

        $total = (clone $base)
            ->selectRaw('COUNT(DISTINCT stock_levels.product_id) AS item_count')
            ->selectRaw('COALESCE(SUM(stock_levels.quantity_on_hand), 0) AS quantity_on_hand')
            ->selectRaw('COALESCE(SUM(stock_levels.quantity_on_hand * stock_levels.average_cost), 0) AS stock_value')
            ->first();

        $data = [
            'currency' => $this->tenant->company()?->currency,
            'as_of' => now()->toJSON(),
            'warehouses' => $warehouses,
            'total' => [
                'item_count' => (int) ($total->item_count ?? 0),
                'quantity_on_hand' => Money::normalize((string) ($total->quantity_on_hand ?? 0)),
                'stock_value' => Money::normalize((string) ($total->stock_value ?? 0)),
            ],
        ];

        if ($request->input('group_by') === 'category') {
            $data['categories'] = (clone $base)
                ->join('products', 'products.id', '=', 'stock_levels.product_id')
                ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
                ->selectRaw('products.category_id, product_categories.name AS category_name')
                ->selectRaw('COUNT(DISTINCT stock_levels.product_id) AS item_count')
                ->selectRaw('SUM(stock_levels.quantity_on_hand) AS quantity_on_hand')
                ->selectRaw('SUM(stock_levels.quantity_on_hand * stock_levels.average_cost) AS stock_value')
                ->groupBy('products.category_id', 'product_categories.name')
                ->orderBy('product_categories.name')
                ->get()
                ->map(fn ($row) => [
                    'category_id' => $row->category_id === null ? null : (int) $row->category_id,
                    'category_name' => $row->category_name ?? 'Uncategorised',
                    'item_count' => (int) $row->item_count,
                    'quantity_on_hand' => Money::normalize((string) $row->quantity_on_hand),
                    'stock_value' => Money::normalize((string) $row->stock_value),
                ])->values();
        }

        return response()->json(['data' => $data]);
    }

    public function movementSummary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', StockMovement::class);

        $request->validate([
            'group' => ['nullable', Rule::in(['day', 'month'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'warehouse_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
        ]);

        $group = (string) $request->input('group', 'day');
        $from = ($request->date('from') ?? now()->subDays(29))->startOfDay();
        $to = ($request->date('to') ?? now())->endOfDay();
        $period = $group === 'month' ? "DATE_FORMAT(stock_movements.moved_at, '%Y-%m')" : 'DATE(stock_movements.moved_at)';

        $query = StockMovement::query()->visibleBranches()
            ->whereBetween('stock_movements.moved_at', [$from, $to])
            ->selectRaw("{$period} AS period")
            ->selectRaw('COUNT(*) AS movements')
            ->selectRaw('SUM(CASE WHEN stock_movements.quantity > 0 THEN stock_movements.quantity ELSE 0 END) AS quantity_in')
            ->selectRaw('SUM(CASE WHEN stock_movements.quantity < 0 THEN -stock_movements.quantity ELSE 0 END) AS quantity_out')
            ->selectRaw('SUM(CASE WHEN stock_movements.quantity > 0 THEN stock_movements.total_cost ELSE 0 END) AS value_in')
            ->selectRaw('SUM(CASE WHEN stock_movements.quantity < 0 THEN -stock_movements.total_cost ELSE 0 END) AS value_out')
            ->groupByRaw($period)
            ->orderByRaw($period);

        if ($warehouseId = $request->integer('warehouse_id')) {
            $query->where('stock_movements.warehouse_id', $warehouseId);
        }

        if ($productId = $request->integer('product_id')) {
            $query->where('stock_movements.product_id', $productId);
        }

        $rows = $query->get()->map(fn ($row) => [
            'period' => (string) $row->period,
            'movements' => (int) $row->movements,
            'quantity_in' => Money::normalize((string) $row->quantity_in),
            'quantity_out' => Money::normalize((string) $row->quantity_out),
            'value_in' => Money::normalize((string) $row->value_in),
            'value_out' => Money::normalize((string) $row->value_out),
        ])->values();

        return response()->json([
            'data' => [
                'group' => $group,
                'from' => $from->toJSON(),
                'to' => $to->toJSON(),
                'currency' => $this->tenant->company()?->currency,
                'rows' => $rows,
                'totals' => [
                    'movements' => $rows->sum('movements'),
                    'quantity_in' => Money::sum($rows->pluck('quantity_in')->all()),
                    'quantity_out' => Money::sum($rows->pluck('quantity_out')->all()),
                    'value_in' => Money::sum($rows->pluck('value_in')->all()),
                    'value_out' => Money::sum($rows->pluck('value_out')->all()),
                ],
            ],
        ]);
    }
}
