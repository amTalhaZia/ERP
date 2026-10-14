<?php

namespace App\Models\Inventory;

use App\Enums\Inventory\SerialStatus;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Inventory\SerialNumberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'product_id', 'batch_id', 'warehouse_id', 'serial', 'status'])]
class SerialNumber extends Model
{
    /** @use HasFactory<SerialNumberFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return ['status' => SerialStatus::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
