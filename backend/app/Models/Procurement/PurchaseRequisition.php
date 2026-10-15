<?php

namespace App\Models\Procurement;

use App\Enums\Procurement\RequisitionPriority;
use App\Enums\Procurement\RequisitionStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use App\Support\Tenancy\RestrictsToAllowedBranches;
use Database\Factories\Procurement\PurchaseRequisitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'number', 'requester_id', 'department_id', 'branch_id', 'needed_by', 'priority', 'status',
    'justification', 'submitted_at', 'approver_id', 'approved_at', 'rejected_at', 'rejection_reason', 'estimated_total',
])]
class PurchaseRequisition extends Model
{
    /** @use HasFactory<PurchaseRequisitionFactory> */
    use Auditable, BelongsToCompany, HasFactory, RestrictsToAllowedBranches;

    /** Branch restriction: direct `branch_id` column. */
    protected string $branchColumn = 'branch_id';

    protected function casts(): array
    {
        return [
            'needed_by' => 'date',
            'priority' => RequisitionPriority::class,
            'status' => RequisitionStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'estimated_total' => 'decimal:4',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionLine::class)->orderBy('line_no');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'requisition_id');
    }

    public function isDraft(): bool
    {
        return $this->status === RequisitionStatus::Draft;
    }
}
