<?php

namespace App\Models\HR;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use App\Support\Tenancy\RestrictsToAllowedBranches;
use Database\Factories\HR\LeaveBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'employee_id', 'leave_type_id', 'year', 'entitled', 'used', 'carried_forward', 'adjusted'])]
class LeaveBalance extends Model
{
    /** @use HasFactory<LeaveBalanceFactory> */
    use Auditable, BelongsToCompany, HasFactory, RestrictsToAllowedBranches;

    /** @var array<int, string> branch restriction derived via these parent relations */
    protected array $branchRelations = ['employee'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitled' => 'decimal:1',
            'used' => 'decimal:1',
            'carried_forward' => 'decimal:1',
            'adjusted' => 'decimal:1',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /** entitled + carried_forward + adjusted - used, as a 1-decimal string. */
    public function remaining(): string
    {
        $total = bcadd(bcadd((string) $this->entitled, (string) $this->carried_forward, 1), (string) $this->adjusted, 1);

        return bcsub($total, (string) $this->used, 1);
    }
}
