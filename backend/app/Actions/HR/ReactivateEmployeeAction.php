<?php

namespace App\Actions\HR;

use App\Enums\HR\EmployeeStatus;
use App\Models\CompanyUser;
use App\Models\HR\Employee;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class ReactivateEmployeeAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Employee $employee): Employee
    {
        return DB::transaction(function () use ($employee): Employee {
            $previousStatus = $employee->status?->value;

            $employee->fill([
                'status' => EmployeeStatus::Active,
                'terminated_at' => null,
                'termination_reason' => null,
            ])->save();

            if ($employee->user_id !== null) {
                CompanyUser::query()
                    ->where('company_id', $employee->company_id)
                    ->where('user_id', $employee->user_id)
                    ->update(['is_active' => true]);
            }

            $this->audit->log(
                'employees.reactivated',
                $employee,
                ['status' => $previousStatus],
                ['status' => EmployeeStatus::Active->value],
                entityLabel: $employee->fullName(),
            );

            return $employee;
        });
    }
}
