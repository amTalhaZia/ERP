<?php

namespace App\Actions\HR;

use App\Enums\HR\ContractStatus;
use App\Enums\HR\EmployeeStatus;
use App\Models\CompanyUser;
use App\Models\HR\Employee;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Offboarding: marks the employee terminated, closes active contracts and
 * deactivates the linked company membership so the login loses tenant access.
 */
class TerminateEmployeeAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Employee $employee, string $terminatedAt, string $reason): Employee
    {
        return DB::transaction(function () use ($employee, $terminatedAt, $reason): Employee {
            $previousStatus = $employee->status?->value;

            $employee->fill([
                'status' => EmployeeStatus::Terminated,
                'terminated_at' => $terminatedAt,
                'termination_reason' => $reason,
            ])->save();

            $employee->contracts()
                ->where('status', ContractStatus::Active->value)
                ->get()
                ->each(function ($contract) use ($terminatedAt): void {
                    $contract->fill([
                        'status' => ContractStatus::Terminated,
                        'end_date' => $contract->end_date ?? $terminatedAt,
                    ])->save();
                });

            if ($employee->user_id !== null) {
                CompanyUser::query()
                    ->where('company_id', $employee->company_id)
                    ->where('user_id', $employee->user_id)
                    ->update(['is_active' => false]);
            }

            $this->audit->log(
                'employees.terminated',
                $employee,
                ['status' => $previousStatus],
                ['status' => EmployeeStatus::Terminated->value, 'terminated_at' => $terminatedAt],
                ['reason' => $reason, 'membership_deactivated' => $employee->user_id !== null],
                entityLabel: $employee->fullName(),
            );

            return $employee;
        });
    }
}
