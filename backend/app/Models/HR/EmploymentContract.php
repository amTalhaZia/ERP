<?php

namespace App\Models\HR;

use App\Enums\HR\ContractStatus;
use App\Enums\HR\EmploymentType;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use App\Support\Tenancy\RestrictsToAllowedBranches;
use Database\Factories\HR\EmploymentContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'employee_id', 'contract_type', 'start_date', 'end_date', 'salary', 'currency',
    'working_hours_per_week', 'document_path', 'status', 'notes',
])]
class EmploymentContract extends Model
{
    /** @use HasFactory<EmploymentContractFactory> */
    use Auditable, BelongsToCompany, HasFactory, RestrictsToAllowedBranches;

    /** @var array<int, string> branch restriction derived via these parent relations */
    protected array $branchRelations = ['employee'];

    protected function casts(): array
    {
        return [
            'contract_type' => EmploymentType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'salary' => 'decimal:4',
            'working_hours_per_week' => 'decimal:2',
            'status' => ContractStatus::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
