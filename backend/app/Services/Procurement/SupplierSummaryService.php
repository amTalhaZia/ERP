<?php

namespace App\Services\Procurement;

use App\Enums\Finance\PaymentPartnerType;
use App\Enums\Procurement\GoodsReceiptStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Procurement\Supplier;
use App\Services\Finance\Receivables\ReceivablesReportService;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate figures for a single supplier's detail page. The payable balance
 * comes from the AP module (open supplier bills).
 */
class SupplierSummaryService
{
    public function __construct(private readonly ReceivablesReportService $receivables) {}

    /**
     * @return array<string, mixed>
     */
    public function summarize(Supplier $supplier): array
    {
        $byStatus = DB::table('purchase_orders')
            ->where('company_id', $supplier->company_id)
            ->where('supplier_id', $supplier->id)
            ->selectRaw('status, COUNT(*) AS order_count, SUM(total) AS total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn (object $row) => [
                'status' => $row->status,
                'count' => (int) $row->order_count,
                'total' => Money::normalize($row->total),
            ])
            ->all();

        $committed = array_filter($byStatus, fn (array $row) => in_array($row['status'], PurchaseOrderStatus::committedValues(), true));

        $receipts = DB::table('goods_receipts AS gr')
            ->join('purchase_orders AS po', 'po.id', '=', 'gr.purchase_order_id')
            ->where('gr.company_id', $supplier->company_id)
            ->where('po.supplier_id', $supplier->id)
            ->where('gr.status', GoodsReceiptStatus::Confirmed->value)
            ->selectRaw('COUNT(*) AS receipt_count, AVG(DATEDIFF(gr.received_date, po.order_date)) AS avg_lead_time_days, MAX(gr.received_date) AS last_received_date')
            ->first();

        $lastOrder = DB::table('purchase_orders')
            ->where('company_id', $supplier->company_id)
            ->where('supplier_id', $supplier->id)
            ->where('status', '!=', PurchaseOrderStatus::Cancelled->value)
            ->max('order_date');

        $payable = $this->receivables->partnerBalance(
            (int) $supplier->company_id, PaymentPartnerType::Supplier, (int) $supplier->id, now()->toDateString(),
        );

        return [
            'supplier_id' => $supplier->id,
            'currency' => $supplier->currency,
            'purchase_orders' => [
                'by_status' => array_values($byStatus),
                'total_count' => array_sum(array_column($byStatus, 'count')),
                'committed_count' => array_sum(array_column($committed, 'count')),
                'committed_total' => Money::sum(array_column($committed, 'total')),
            ],
            'goods_receipts_count' => (int) ($receipts?->receipt_count ?? 0),
            'avg_lead_time_days' => $receipts?->avg_lead_time_days === null ? null : round((float) $receipts->avg_lead_time_days, 1),
            'promised_lead_time_days' => $supplier->lead_time_days,
            'last_order_date' => $lastOrder,
            'last_received_date' => $receipts?->last_received_date,
            'outstanding_payable' => $payable['outstanding'],
            'overdue_payable' => $payable['overdue'],
            'open_bills_count' => $payable['open_count'],
            'overdue_bills_count' => $payable['overdue_count'],
        ];
    }
}
