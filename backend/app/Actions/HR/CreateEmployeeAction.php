<?php

namespace App\Actions\HR;

use App\Enums\HR\EmployeeStatus;
use App\Models\HR\Employee;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Onboarding: issues the next EMP-#### code inside the same transaction that
 * persists the employee so the sequence never has gaps.
 */
class CreateEmployeeAction
{
    public function __construct(
        private readonly DocumentNumberer $numberer,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Employee
    {
        return DB::transaction(function () use ($attributes): Employee {
            $attributes['employee_code'] = $this->numberer->next('employee', yearly: false, prefix: 'EMP', padding: 4);
            $attributes['status'] ??= EmployeeStatus::Probation->value;
            $attributes['currency'] ??= $this->tenant->company()?->currency;

            return Employee::query()->create($attributes);
        });
    }
}
