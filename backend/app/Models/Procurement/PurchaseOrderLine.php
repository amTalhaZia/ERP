<?php

namespace App\Models\Procurement;

use App\Models\Inventory\Product;
use App\Support\Money\Money;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Procurement\PurchaseOrderLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'purchase_order_id', 'line_no', 'product_id', 'description', 'quantity', 'uom', 'unit_price',
    'discount_percent', 'tax_rate', 'line_subtotal', 'tax_amount', 'line_total', 'quantity_received', 'quantity_returned',
])]
class PurchaseOrderLine extends Model
{
    /** @use HasFactory<PurchaseOrderLineFactory> */
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
            'quantity_received' => 'decimal:4',
            'quantity_returned' => 'decimal:4',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** Quantity still expected from the supplier. */
    public function quantityRemaining(): string
    {
        return Money::sub((string) $this->quantity, (string) $this->quantity_received);
    }

    /** Quantity received that has not yet been returned. */
    public function quantityReturnable(): string
    {
        return Money::sub((string) $this->quantity_received, (string) $this->quantity_returned);
    }

    /** Unit cost after line discount; the cost basis handed to inventory on receipt. */
    public function effectiveUnitCost(): string
    {
        $factor = Money::sub('1', Money::div((string) $this->discount_percent, '100'));

        return Money::mul((string) $this->unit_price, $factor);
    }

    /** Catalogue product (soft-deleted products still resolve for historical lines). */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
