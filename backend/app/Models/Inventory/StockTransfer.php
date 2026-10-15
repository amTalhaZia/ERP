<?php

namespace App\Models\Inventory;

use App\Enums\Inventory\StockTransferStatus;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use App\Support\Tenancy\RestrictsToAllowedBranches;
use Database\Factories\Inventory\StockTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'company_id', 'number', 'from_warehouse_id', 'to_warehouse_id', 'status', 'notes',
    'shipped_at', 'received_at', 'created_by', 'shipped_by', 'received_by',
])]
class StockTransfer extends Model
{
    /** @use HasFactory<StockTransferFactory> */
    use Auditable, BelongsToCompany, HasFactory, RestrictsToAllowedBranches;

    /** @var array<int, string> branch restriction derived via these parent relations */
    protected array $branchRelations = ['fromWarehouse', 'toWarehouse'];

    protected function casts(): array
    {
        return [
            'status' => StockTransferStatus::class,
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function isDraft(): bool
    {
        return $this->status === StockTransferStatus::Draft;
    }
}
