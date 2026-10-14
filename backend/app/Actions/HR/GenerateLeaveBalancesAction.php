<?php

namespace App\Actions\HR;

use App\Models\HR\Employee;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveType;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Creates the missing (employee × active leave type) balance rows for a year.
 * Existing rows are left untouched; carry-forward is taken from the previous
 * year's remaining balance, capped by the type's max_carry_forward_days.
 */
class GenerateLeaveBalancesAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(int $year): int
    {
        return DB::transaction(function () use ($year): int {
            $employeeIds = Employee::query()->employed()->pluck('id');
            $types = LeaveType::query()->where('is_active', true)->get();

            $existing = LeaveBalance::query()
                ->where('year', $year)
                ->get(['employee_id', 'leave_type_id'])
                ->map(fn (LeaveBalance $b) => $b->employee_id.':'.$b->leave_type_id)
                ->flip();

            $previous = LeaveBalance::query()
                ->where('year', $year - 1)
                ->get()
                ->keyBy(fn (LeaveBalance $b) => $b->employee_id.':'.$b->leave_type_id);

            $now = now();
            $rows = [];

            foreach ($employeeIds as $employeeId) {
                foreach ($types as $type) {
                    $key = $employeeId.':'.$type->id;

                    if ($existing->has($key)) {
                        continue;
                    }

                    $carried = '0.0';
                    if ($type->carry_forward && ($prev = $previous->get($key)) !== null) {
                        $remaining = $prev->remaining();
                        $carried = bccomp($remaining, '0', 1) > 0 ? $remaining : '0.0';
                        if ($type->max_carry_forward_days !== null && bccomp($carried, (string) $type->max_carry_forward_days, 1) > 0) {
                            $carried = (string) $type->max_carry_forward_days;
                        }
                    }

                    $rows[] = [
                        'company_id' => $this->tenant->id(),
                        'employee_id' => $employeeId,
                        'leave_type_id' => $type->id,
                        'year' => $year,
                        'entitled' => (string) $type->days_per_year,
                        'used' => '0.0',
                        'carried_forward' => $carried,
                        'adjusted' => '0.0',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                LeaveBalance::query()->insert($chunk);
            }

            $this->audit->log('leave.balances.generated', null, null, null, ['year' => $year, 'created' => count($rows)]);

            return count($rows);
        });
    }
}
