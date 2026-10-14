<?php

namespace App\Actions\Sales;

use App\Enums\Sales\QuotationStatus;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Converts a Sent/Accepted quotation into a draft sales order: copies header
 * data and every line (snapshots included), links both documents and marks
 * the quotation Converted.
 */
class CreateSalesOrderFromQuotationAction
{
    public function __construct(
        private readonly UpsertSalesOrderAction $upsertOrder,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Quotation $quotation, ?User $actor = null): SalesOrder
    {
        return DB::transaction(function () use ($quotation, $actor): SalesOrder {
            $quotation = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($quotation->status, [QuotationStatus::Sent, QuotationStatus::Accepted], true)) {
                throw new ConflictHttpException('Only sent or accepted quotations can be converted to a sales order.');
            }

            $lines = $quotation->lines()->get()->map(fn ($line) => [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'uom' => $line->uom,
                'unit_price' => (string) $line->unit_price,
                'discount_percent' => (string) $line->discount_percent,
                'tax_rate' => (string) $line->tax_rate,
            ])->all();

            $order = $this->upsertOrder->handle([
                'customer_id' => $quotation->customer_id,
                'quotation_id' => $quotation->id,
                'currency' => $quotation->currency,
                'notes' => $quotation->notes,
                'terms' => $quotation->terms,
                'sales_rep_id' => $quotation->sales_rep_id,
                'lines' => $lines,
            ], null, $actor);

            $quotation->forceFill([
                'status' => QuotationStatus::Converted,
                'converted_sales_order_id' => $order->id,
            ])->save();

            $this->audit->log('quotations.converted', $quotation, context: [
                'sales_order_id' => $order->id,
                'sales_order_number' => $order->number,
            ]);

            return $order;
        });
    }
}
