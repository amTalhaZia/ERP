<?php

namespace App\Models\Sales;

use App\Models\Inventory\Product;
use App\Support\Money\Money;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Sales\SalesOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `product_id` references the Inventory catalogue (FK, nullOnDelete);
 * description/uom/unit_price are snapshots taken when the line is saved.
 */
#[Fillable([
    'company_id', 'sales_order_id', 'line_no', 'product_id', 'description', 'quantity', 'quantity_delivered', 'uom',
    'unit_price', 'discount_percent', 'tax_rate', 'line_subtotal', 'tax_amount', 'line_total',
])]
class SalesOrderLine extends Model
{
    /** @use HasFactory<SalesOrderLineFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:4',
            'quantity_delivered' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'line_subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    /** Quantity still to deliver (ignores open deliveries not yet confirmed). */
    public function quantityRemaining(): string
    {
        return Money::sub((string) $this->quantity, (string) $this->quantity_delivered);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function deliveryLines(): HasMany
    {
        return $this->hasMany(DeliveryLine::class);
    }

    /** Catalogue product (soft-deleted products still resolve for historical lines). */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
