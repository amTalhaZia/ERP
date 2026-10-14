<?php

namespace App\Models\Payroll;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-employee component override/addition on top of the salary structure.
 */
#[Fillable(['company_id', 'employee_salary_id', 'pay_component_id', 'amount', 'rate'])]
class EmployeeSalaryComponent extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'rate' => 'decimal:4'];
    }

    public function salary(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalary::class, 'employee_salary_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class, 'pay_component_id');
    }
}
