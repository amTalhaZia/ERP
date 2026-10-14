<?php

namespace App\Actions\Sales;

use App\Enums\Sales\QuotationStatus;
use App\Models\Sales\Customer;
use App\Models\Sales\Quotation;
use App\Models\User;
use App\Services\Sales\DocumentTotals;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a DRAFT quotation with its lines; totals are always
 * recomputed server-side. Callers guard the status (non-draft → 409).
 */
class UpsertQuotationAction
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
     * @param  array<string, mixed>  $data  validated StoreQuotationRequest payload
     */
    public function handle(array $data, ?Quotation $quotation = null, ?User $actor = null): Quotation
    {
        return DB::transaction(function () use ($data, $quotation, $actor): Quotation {
            $lines = $data['lines'] ?? null;
            $attributes = Arr::except($data, ['lines']);

            if ($quotation === null) {
                $customer = Customer::query()->findOrFail($attributes['customer_id']);

                $attributes['number'] = $this->numberer->next('quotation');
                $attributes['status'] = QuotationStatus::Draft;
                $attributes['date'] ??= today()->toDateString();
                $attributes['currency'] ??= $customer->currency ?: ($this->tenant->company()?->currency ?? 'USD');
                $attributes['sales_rep_id'] = array_key_exists('sales_rep_id', $attributes) ? $attributes['sales_rep_id'] : $customer->sales_rep_id;
                $attributes['created_by'] = $actor?->id;

                $quotation = new Quotation;
            }

            $quotation->fill($attributes);

            if ($lines !== null) {
                $computed = $this->totals->compute($lines);
                $quotation->fill(Arr::only($computed, ['subtotal', 'discount_total', 'tax_total', 'total']));
                $quotation->save();

                $quotation->lines()->delete();
                $quotation->lines()->createMany(array_map(
                    fn (array $line) => Arr::only($line, self::LINE_KEYS) + ['uom' => null, 'product_id' => null],
                    $computed['lines'],
                ));
            } else {
                $quotation->save();
            }

            return $quotation;
        });
    }
}
