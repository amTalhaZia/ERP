<?php

namespace App\Models\Inventory;

use App\Enums\Inventory\StockMovementType;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Inventory\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only stock ledger line. Rows are never updated or deleted; a mistake
 * is corrected with a compensating movement (adjustment).
 */
#[Fillable([
    'company_id', 'product_id', 'warehouse_id', 'location_id', 'batch_id', 'type', 'quantity',
    'unit_cost', 'total_cost', 'balance_after', 'reference_type', 'reference_id', 'reason', 'moved_at', 'created_by',
])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use BelongsToCompany, HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('stock_movements is append-only; record a compensating movement instead.'));
        static::deleting(fn () => throw new \LogicException('stock_movements is append-only; record a compensating movement instead.'));
    }

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'moved_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'location_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }
}
