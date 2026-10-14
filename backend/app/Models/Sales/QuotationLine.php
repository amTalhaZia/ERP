<?php

namespace App\Models\Sales;

use App\Models\Inventory\Product;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Sales\QuotationLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `product_id` references the Inventory catalogue (FK, nullOnDelete);
 * description/uom/unit_price are snapshots taken when the line is saved.
 */
#[Fillable([
    'company_id', 'quotation_id', 'line_no', 'product_id', 'description', 'quantity', 'uom', 'unit_price',
    'discount_percent', 'tax_rate', 'line_subtotal', 'tax_amount', 'line_total',
])]
class QuotationLine extends Model
{
    /** @use HasFactory<QuotationLineFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'line_subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** Catalogue product (soft-deleted products still resolve for historical lines). */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
