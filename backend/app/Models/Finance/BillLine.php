<?php

namespace App\Models\Finance;

use App\Models\Inventory\Product;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bill line. Amounts are computed server-side by DocumentTotalsCalculator;
 * `tax_rate` snapshots the rate at the time the line was saved.
 */
#[Fillable([
    'company_id', 'bill_id', 'line_no', 'product_id', 'description', 'quantity', 'unit_price',
    'discount_percent', 'tax_rate_id', 'tax_rate', 'account_id', 'line_subtotal', 'discount_amount',
    'tax_amount', 'line_total',
])]
class BillLine extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_percent' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'line_subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /** Catalogue product (soft-deleted products still resolve for historical lines). */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
