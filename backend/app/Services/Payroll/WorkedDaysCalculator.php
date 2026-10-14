<?php

namespace App\Services\Payroll;

use App\Actions\HR\LeaveDaysCalculator;
use App\Enums\HR\LeaveRequestStatus;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Working / unpaid / paid days of an employee in a pay period.
 *
 *  - working_days: working days in the period per the HR calendar (the
 *    employee's shift pattern, else Mon–Fri, minus company/branch holidays)
 *    via HR's LeaveDaysCalculator;
 *  - unpaid_days: working days outside the employment window (hired after the
 *    period start / terminated before its end) plus APPROVED leave of a leave
 *    type with is_paid = false that overlaps the period (clipped to it);
 *  - paid_days = working_days − unpaid_days (never below 0).
 */
class WorkedDaysCalculator
{
    public function __construct(private readonly LeaveDaysCalculator $days) {}

    /**
     * Approved unpaid leave overlapping the period for the given employees.
     *
     * @param  array<int, int>  $employeeIds
     * @return Collection<int, Collection<int, LeaveRequest>>
     */
    public function unpaidLeaveFor(array $employeeIds, string $start, string $end): Collection
    {
        if ($employeeIds === []) {
            return collect();
        }

        return LeaveRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', LeaveRequestStatus::Approved->value)
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->whereHas('leaveType', fn ($q) => $q->where('is_paid', false))
            ->orderBy('start_date')
            ->get()
            ->groupBy('employee_id');
    }

    /**
     * @param  Collection<int, LeaveRequest>  $unpaidLeave  this employee's approved unpaid leave
     * @return array{working_days: string, unpaid_days: string, paid_days: string}
     */
    public function compute(Employee $employee, string $start, string $end, Collection $unpaidLeave): array
    {
        $periodStart = CarbonImmutable::parse($start);
        $periodEnd = CarbonImmutable::parse($end);
        $working = $this->days->days($employee, $start, $end);

        $from = $employee->hired_at !== null && $employee->hired_at->toDateString() > $start
            ? CarbonImmutable::parse($employee->hired_at->toDateString()) : $periodStart;
        $to = $employee->terminated_at !== null && $employee->terminated_at->toDateString() < $end
            ? CarbonImmutable::parse($employee->terminated_at->toDateString()) : $periodEnd;

        if ($from->greaterThan($to)) {
            $employed = '0.0';
        } elseif ($from->equalTo($periodStart) && $to->equalTo($periodEnd)) {
            $employed = $working;
        } else {
            $employed = $this->days->days($employee, $from->toDateString(), $to->toDateString());
        }

        $leave = '0.0';

        if ($from->lessThanOrEqualTo($to)) {
            foreach ($unpaidLeave as $request) {
                $s = $request->start_date->greaterThan($from) ? CarbonImmutable::parse($request->start_date->toDateString()) : $from;
                $e = $request->end_date->lessThan($to) ? CarbonImmutable::parse($request->end_date->toDateString()) : $to;

                if ($s->greaterThan($e)) {
                    continue;
                }

                $inside = $request->start_date->toDateString() >= $from->toDateString()
                    && $request->end_date->toDateString() <= $to->toDateString();

                // Fully inside: trust the request's own day count (supports half days).
                $leave = bcadd($leave, $inside ? (string) $request->days : $this->days->days($employee, $s->toDateString(), $e->toDateString()), 1);
            }
        }

        $unpaid = bcadd(bcsub($working, $employed, 1), $leave, 1);

        if (bccomp($unpaid, $working, 1) > 0) {
            $unpaid = $working;
        }

        return [
            'working_days' => bcadd($working, '0', 1),
            'unpaid_days' => $unpaid,
            'paid_days' => bcsub($working, $unpaid, 1),
        ];
    }
}
