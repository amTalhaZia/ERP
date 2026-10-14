<?php

namespace App\Models\Payroll;

use App\Enums\Payroll\PayFrequency;
use App\Models\HR\Employee;
use App\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\Payroll\EmployeeSalaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row of an employee's salary history. The salary in force for a period
 * is the row with the latest effective_from ≤ period end whose effective_to
 * (if any) is ≥ period start.
 */
#[Fillable([
    'company_id', 'employee_id', 'salary_structure_id', 'base_salary', 'currency', 'effective_from',
    'effective_to', 'pay_frequency', 'notes', 'created_by',
])]
class EmployeeSalary extends Model
{
    /** @use HasFactory<EmployeeSalaryFactory> */
    use Auditable, BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'pay_frequency' => PayFrequency::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
