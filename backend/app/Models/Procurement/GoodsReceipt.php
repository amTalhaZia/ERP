<?php

namespace App\Models\Procurement;

use App\Enums\Procurement\GoodsReceiptStatus;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use App\Support\Tenancy\RestrictsToAllowedBranches;
use Database\Factories\Procurement\GoodsReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'number', 'purchase_order_id', 'received_date', 'warehouse_id', 'status', 'received_by',
    'supplier_delivery_note', 'notes', 'confirmed_at', 'cancelled_at',
])]
class GoodsReceipt extends Model
{
    /** @use HasFactory<GoodsReceiptFactory> */
    use Auditable, BelongsToCompany, HasFactory, RestrictsToAllowedBranches;

    /** @var array<int, string> branch restriction derived via these parent relations */
    protected array $branchRelations = ['purchaseOrder'];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'status' => GoodsReceiptStatus::class,
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isDraft(): bool
    {
        return $this->status === GoodsReceiptStatus::Draft;
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }
}
