<?php

namespace App\Models\Procurement;

use App\Models\Inventory\Product;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Procurement\PurchaseRequisitionLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'purchase_requisition_id', 'line_no', 'product_id', 'description', 'quantity', 'uom',
    'estimated_unit_price', 'line_total',
])]
class PurchaseRequisitionLine extends Model
{
    /** @use HasFactory<PurchaseRequisitionLineFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'quantity' => 'decimal:4',
            'estimated_unit_price' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    /** Catalogue product (soft-deleted products still resolve for historical lines). */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
