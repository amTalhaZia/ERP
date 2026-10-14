<?php

namespace App\Actions\Sales;

use App\Enums\Sales\SalesOrderStatus;
use App\Models\Sales\Customer;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use App\Services\Sales\DocumentTotals;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a DRAFT sales order with its lines; totals are always
 * recomputed server-side. The shipping address defaults to the customer's
 * shipping address snapshot on create. Callers guard the status.
 */
class UpsertSalesOrderAction
{
    /** @var array<int, string> */
    private const LINE_KEYS = [
        'line_no', 'product_id', 'description', 'quantity', 'uom', 'unit_price',
        'discount_percent', 'tax_rate', 'line_subtotal', 'tax_amount', 'line_total',
    ];

    public function __construct(
        private readonly DocumentTotals $totals,
        private readonly DocumentNumberer $numberer,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated StoreSalesOrderRequest payload (+ optional quotation_id)
     */
    public function handle(array $data, ?SalesOrder $order = null, ?User $actor = null): SalesOrder
    {
        return DB::transaction(function () use ($data, $order, $actor): SalesOrder {
            $lines = $data['lines'] ?? null;
            $attributes = Arr::except($data, ['lines']);

            if ($order === null) {
                $customer = Customer::query()->findOrFail($attributes['customer_id']);

                $attributes['number'] = $this->numberer->next('sales_order');
                $attributes['status'] = SalesOrderStatus::Draft;
                $attributes['order_date'] ??= today()->toDateString();
                $attributes['currency'] ??= $customer->currency ?: ($this->tenant->company()?->currency ?? 'USD');
                $attributes['sales_rep_id'] = array_key_exists('sales_rep_id', $attributes) ? $attributes['sales_rep_id'] : $customer->sales_rep_id;
                $attributes['created_by'] = $actor?->id;
                $attributes['shipping_name'] ??= $customer->name;

                foreach (Customer::SHIPPING_FIELDS as $field) {
                    $attributes[$field] ??= $customer->{$field};
                }

                $order = new SalesOrder;
            }

            $order->fill($attributes);

            if ($lines !== null) {
                $computed = $this->totals->compute($lines);
                $order->fill(Arr::only($computed, ['subtotal', 'discount_total', 'tax_total', 'total']));
                $order->save();

                $order->lines()->delete();
                $order->lines()->createMany(array_map(
                    fn (array $line) => Arr::only($line, self::LINE_KEYS) + ['uom' => null, 'product_id' => null],
                    $computed['lines'],
                ));
            } else {
                $order->save();
            }

            return $order;
        });
    }
}
