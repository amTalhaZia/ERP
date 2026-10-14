<?php

namespace App\Services\Procurement;

use App\Enums\Procurement\GoodsReceiptStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\Procurement\RequisitionStatus;
use App\Support\Money\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate SQL behind /procurement/reports/*. Everything is tenant-filtered
 * explicitly (query builder, no Eloquent scope) and money comes back as
 * DECIMAL strings normalised through Money.
 */
class ProcurementReportService
{
    /**
     * Committed purchase-order spend grouped by supplier, month or department.
     *
     * @return array{group_by: string, rows: array<int, array<string, mixed>>, total: string, order_count: int}
     */
    public function spend(int $companyId, ?string $from, ?string $to, string $groupBy = 'supplier'): array
    {
        $query = DB::table('purchase_orders AS po')
            ->where('po.company_id', $companyId)
            ->whereIn('po.status', PurchaseOrderStatus::committedValues());
        $this->dateRange($query, 'po.order_date', $from, $to);

        match ($groupBy) {
            'month' => $query
                ->selectRaw("DATE_FORMAT(po.order_date, '%Y-%m') AS group_key, DATE_FORMAT(po.order_date, '%Y-%m') AS label, COUNT(*) AS order_count, SUM(po.total) AS total")
                ->groupByRaw("DATE_FORMAT(po.order_date, '%Y-%m')")
                ->orderBy('group_key'),
            'department' => $query
                ->leftJoin('purchase_requisitions AS pr', 'pr.id', '=', 'po.requisition_id')
                ->leftJoin('departments AS d', 'd.id', '=', 'pr.department_id')
                ->selectRaw("d.id AS group_key, COALESCE(d.name, 'Unassigned') AS label, COUNT(*) AS order_count, SUM(po.total) AS total")
                ->groupBy('d.id', 'd.name')
                ->orderByDesc('total'),
            default => $query
                ->join('suppliers AS s', 's.id', '=', 'po.supplier_id')
                ->selectRaw('po.supplier_id AS group_key, s.name AS label, COUNT(*) AS order_count, SUM(po.total) AS total')
                ->groupBy('po.supplier_id', 's.name')
                ->orderByDesc('total'),
        };

        $rows = $query->get()->map(fn (object $row) => [
            'key' => $row->group_key,
            'label' => $row->label,
            'order_count' => (int) $row->order_count,
            'total' => Money::normalize($row->total),
        ])->all();

        return [
            'group_by' => $groupBy,
            'rows' => $rows,
            'total' => Money::sum(array_column($rows, 'total')),
            'order_count' => array_sum(array_column($rows, 'order_count')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statusSummary(int $companyId, ?string $from, ?string $to): array
    {
        $orders = DB::table('purchase_orders')->where('company_id', $companyId);
        $this->dateRange($orders, 'order_date', $from, $to);
        $orderRows = $orders->selectRaw('status, COUNT(*) AS doc_count, SUM(total) AS total')->groupBy('status')->get();

        $requisitions = DB::table('purchase_requisitions')->where('company_id', $companyId);
        $this->dateRange($requisitions, 'created_at', $from, $to);
        $requisitionRows = $requisitions->selectRaw('status, COUNT(*) AS doc_count, SUM(estimated_total) AS total')->groupBy('status')->get();

        $approval = DB::table('purchase_requisitions')
            ->where('company_id', $companyId)
            ->whereNotNull('submitted_at')
            ->whereNotNull('approved_at')
            ->whereIn('status', [RequisitionStatus::Approved->value, RequisitionStatus::Ordered->value]);
        $this->dateRange($approval, 'created_at', $from, $to);
        $avgApprovalSeconds = $approval->selectRaw('AVG(TIMESTAMPDIFF(SECOND, submitted_at, approved_at)) AS seconds')->value('seconds');

        $receipts = DB::table('goods_receipts AS gr')
            ->join('purchase_orders AS po', 'po.id', '=', 'gr.purchase_order_id')
            ->where('gr.company_id', $companyId)
            ->where('gr.status', GoodsReceiptStatus::Confirmed->value)
            ->whereNotNull('po.expected_date');
        $this->dateRange($receipts, 'gr.received_date', $from, $to);
        $receiptStats = $receipts->selectRaw('COUNT(*) AS receipt_count, SUM(CASE WHEN gr.received_date <= po.expected_date THEN 1 ELSE 0 END) AS on_time_count')->first();

        $receiptCount = (int) ($receiptStats?->receipt_count ?? 0);
        $onTime = (int) ($receiptStats?->on_time_count ?? 0);

        return [
            'purchase_orders' => $this->statusRows($orderRows, PurchaseOrderStatus::cases()),
            'requisitions' => $this->statusRows($requisitionRows, RequisitionStatus::cases()),
            'avg_requisition_approval_hours' => $avgApprovalSeconds === null ? null : round((float) $avgApprovalSeconds / 3600, 2),
            'on_time_receipt' => [
                'receipts' => $receiptCount,
                'on_time' => $onTime,
                'rate' => $receiptCount === 0 ? null : round($onTime / $receiptCount, 4),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function supplierPerformance(int $companyId, ?string $from, ?string $to): array
    {
        $orders = DB::table('purchase_orders AS po')
            ->where('po.company_id', $companyId)
            ->whereIn('po.status', PurchaseOrderStatus::committedValues());
        $this->dateRange($orders, 'po.order_date', $from, $to);
        $orderRows = $orders->selectRaw('po.supplier_id, COUNT(*) AS order_count, SUM(po.total) AS total')
            ->groupBy('po.supplier_id')->get()->keyBy('supplier_id');

        $receipts = DB::table('goods_receipts AS gr')
            ->join('purchase_orders AS po', 'po.id', '=', 'gr.purchase_order_id')
            ->where('gr.company_id', $companyId)
            ->where('gr.status', GoodsReceiptStatus::Confirmed->value);
        $this->dateRange($receipts, 'gr.received_date', $from, $to);
        $receiptRows = $receipts->selectRaw('po.supplier_id, COUNT(*) AS receipt_count, AVG(DATEDIFF(gr.received_date, po.order_date)) AS avg_lead_time_days, SUM(CASE WHEN po.expected_date IS NOT NULL AND gr.received_date <= po.expected_date THEN 1 ELSE 0 END) AS on_time_count, SUM(CASE WHEN po.expected_date IS NOT NULL THEN 1 ELSE 0 END) AS dated_count')
            ->groupBy('po.supplier_id')->get()->keyBy('supplier_id');

        $quality = DB::table('goods_receipt_lines AS grl')
            ->join('goods_receipts AS gr', 'gr.id', '=', 'grl.goods_receipt_id')
            ->join('purchase_orders AS po', 'po.id', '=', 'gr.purchase_order_id')
            ->where('gr.company_id', $companyId)
            ->where('gr.status', GoodsReceiptStatus::Confirmed->value);
        $this->dateRange($quality, 'gr.received_date', $from, $to);
        $qualityRows = $quality->selectRaw('po.supplier_id, SUM(grl.quantity_received) AS received, SUM(grl.quantity_rejected) AS rejected')
            ->groupBy('po.supplier_id')->get()->keyBy('supplier_id');

        $supplierIds = $orderRows->keys()->merge($receiptRows->keys())->merge($qualityRows->keys())->unique()->values();

        if ($supplierIds->isEmpty()) {
            return [];
        }

        $suppliers = DB::table('suppliers')->where('company_id', $companyId)->whereIn('id', $supplierIds)
            ->get(['id', 'code', 'name', 'rating', 'lead_time_days'])->keyBy('id');

        return $supplierIds->map(function (int $supplierId) use ($suppliers, $orderRows, $receiptRows, $qualityRows): array {
            $supplier = $suppliers->get($supplierId);
            $order = $orderRows->get($supplierId);
            $receipt = $receiptRows->get($supplierId);
            $qty = $qualityRows->get($supplierId);
            $received = Money::normalize($qty?->received ?? 0);
            $rejected = Money::normalize($qty?->rejected ?? 0);
            $handled = Money::add($received, $rejected);
            $dated = (int) ($receipt?->dated_count ?? 0);

            return [
                'supplier_id' => $supplierId,
                'code' => $supplier?->code,
                'name' => $supplier?->name,
                'rating' => $supplier?->rating === null ? null : (int) $supplier->rating,
                'promised_lead_time_days' => $supplier?->lead_time_days === null ? null : (int) $supplier->lead_time_days,
                'order_count' => (int) ($order?->order_count ?? 0),
                'total' => Money::normalize($order?->total ?? 0),
                'receipt_count' => (int) ($receipt?->receipt_count ?? 0),
                'avg_lead_time_days' => $receipt?->avg_lead_time_days === null ? null : round((float) $receipt->avg_lead_time_days, 1),
                'on_time_rate' => $dated === 0 ? null : round((int) $receipt->on_time_count / $dated, 4),
                'quantity_received' => $received,
                'quantity_rejected' => $rejected,
                'rejected_ratio' => Money::isZero($handled) ? null : round((float) Money::div($rejected, $handled), 4),
            ];
        })->sortByDesc('total')->values()->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int, \BackedEnum>  $cases
     * @return array<int, array{status: string, count: int, total: string}>
     */
    private function statusRows($rows, array $cases): array
    {
        $byStatus = $rows->keyBy('status');

        return array_map(fn (\BackedEnum $case) => [
            'status' => (string) $case->value,
            'count' => (int) ($byStatus->get($case->value)?->doc_count ?? 0),
            'total' => Money::normalize($byStatus->get($case->value)?->total ?? 0),
        ], $cases);
    }

    private function dateRange(Builder $query, string $column, ?string $from, ?string $to): void
    {
        if ($from !== null && $from !== '') {
            $query->whereDate($column, '>=', $from);
        }
        if ($to !== null && $to !== '') {
            $query->whereDate($column, '<=', $to);
        }
    }
}
