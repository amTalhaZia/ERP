<?php

namespace App\Actions\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\Supplier;
use App\Models\User;
use App\Services\Procurement\DocumentTotals;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates or replaces a draft purchase order with its lines, recomputing all
 * totals through DocumentTotals. Lines are rewritten wholesale.
 */
class SavePurchaseOrderAction
{
    public function __construct(
        private readonly DocumentNumberer $numberer,
        private readonly DocumentTotals $totals,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated payload incl. `lines`
     */
    public function handle(array $data, User $actor, ?PurchaseOrder $order = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $actor, $order): PurchaseOrder {
            $supplier = Supplier::query()->findOrFail($data['supplier_id'] ?? $order?->supplier_id);
            $inputLines = collect($data['lines'] ?? [])->sortKeys()->values()->all();
            $computed = $this->totals->document($inputLines, $data['shipping_cost'] ?? $order?->shipping_cost ?? 0);

            $header = Arr::except($data, ['lines']);
            $header['currency'] = $header['currency'] ?? $order?->currency ?? $supplier->currency ?? $this->tenant->company()?->currency ?? 'USD';
            $header['payment_terms_days'] = $header['payment_terms_days'] ?? $order?->payment_terms_days ?? $supplier->payment_terms_days;
            $header += Arr::only($computed, ['subtotal', 'discount_total', 'tax_total', 'shipping_cost', 'total']);

            if ($order === null) {
                $order = PurchaseOrder::query()->create($header + [
                    'number' => $this->numberer->next('purchase_order'),
                    'status' => PurchaseOrderStatus::Draft,
                    'created_by' => $actor->id,
                ]);
            } else {
                $order->fill($header)->save();
                $order->lines()->delete();
            }

            foreach ($inputLines as $index => $line) {
                $order->lines()->create([
                    'company_id' => $order->company_id,
                    'line_no' => $index + 1,
                    'product_id' => $line['product_id'] ?? null,
                    'description' => $line['description'],
                    'quantity' => Money::normalize($line['quantity']),
                    'uom' => $line['uom'] ?? null,
                    'unit_price' => Money::normalize($line['unit_price']),
                    'discount_percent' => Money::round(Money::normalize($line['discount_percent'] ?? 0), 2),
                    'tax_rate' => Money::normalize($line['tax_rate'] ?? 0),
                    'line_subtotal' => $computed['lines'][$index]['line_subtotal'],
                    'tax_amount' => $computed['lines'][$index]['tax_amount'],
                    'line_total' => $computed['lines'][$index]['line_total'],
                ]);
            }

            return $order->refresh()->load(['lines', 'supplier:id,code,name', 'requisition:id,number']);
        });
    }
}
