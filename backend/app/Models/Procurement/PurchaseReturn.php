<?php

namespace App\Models\Procurement;

use App\Enums\Procurement\PurchaseReturnReason;
use App\Enums\Procurement\PurchaseReturnStatus;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use App\Support\Tenancy\RestrictsToAllowedBranches;
use Database\Factories\Procurement\PurchaseReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'number', 'purchase_order_id', 'goods_receipt_id', 'return_date', 'reason', 'status', 'notes',
    'total', 'created_by', 'confirmed_at', 'cancelled_at',
])]
class PurchaseReturn extends Model
{
    /** @use HasFactory<PurchaseReturnFactory> */
    use Auditable, BelongsToCompany, HasFactory, RestrictsToAllowedBranches;

    /** @var array<int, string> branch restriction derived via these parent relations */
    protected array $branchRelations = ['purchaseOrder'];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'reason' => PurchaseReturnReason::class,
            'status' => PurchaseReturnStatus::class,
            'total' => 'decimal:4',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReturnLine::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseReturnStatus::Draft;
    }
}
