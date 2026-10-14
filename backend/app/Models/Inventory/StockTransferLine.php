<?php

namespace App\Models\Inventory;

use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Inventory\StockTransferLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'stock_transfer_id', 'product_id', 'quantity', 'unit_cost'])]
class StockTransferLine extends Model
{
    /** @use HasFactory<StockTransferLineFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
