<?php

namespace App\Models\Inventory;

use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Inventory\StockAdjustmentLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'stock_adjustment_id', 'product_id', 'counted_quantity', 'system_quantity', 'difference', 'unit_cost'])]
class StockAdjustmentLine extends Model
{
    /** @use HasFactory<StockAdjustmentLineFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'counted_quantity' => 'decimal:4',
            'system_quantity' => 'decimal:4',
            'difference' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
