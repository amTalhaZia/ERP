<?php

namespace App\Services\Reports\Definitions\HR;

use App\Services\Reports\Definitions\QueryReportDefinition;
use App\Services\Reports\ReportContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Per-employee attendance counts and worked hours for a period.
 */
class AttendanceSummaryReport extends QueryReportDefinition
{
    public function key(): string
    {
        return 'hr.attendance_summary';
    }

    public function module(): string
    {
        return 'hr';
    }

    public function name(): string
    {
        return 'Attendance summary';
    }

    public function description(): string
    {
        return 'Present, late, absent and leave days with worked hours per employee for a period.';
    }

    public function permissions(): array
    {
        return ['attendance.view'];
    }

    public function params(): array
    {
        return [self::dateParam('from', 'From'), self::dateParam('to', 'To'), self::branchParam()];
    }

    public function defaults(ReportContext $context): array
    {
        return ['from' => $context->monthStart(), 'to' => $context->today()];
    }

    public function columns(array $params): array
    {
        return [
            self::col('employee_code', 'Code'),
            self::col('employee', 'Employee'),
            self::col('department', 'Department'),
            self::col('present', 'Present', 'number'),
            self::col('late', 'Late', 'number'),
            self::col('absent', 'Absent', 'number'),
            self::col('half_day', 'Half day', 'number'),
            self::col('on_leave', 'On leave', 'number'),
            self::col('worked_hours', 'Worked hours', 'number'),
            self::col('late_minutes', 'Late minutes', 'number'),
        ];
    }

    protected function query(ReportContext $context, array $params): Builder
    {
        return DB::table('attendance_records as ar')
            ->join('employees as e', 'e.id', '=', 'ar.employee_id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->where('ar.company_id', $context->companyId())
            ->whereBetween('ar.date', [$params['from'], $params['to']])
            ->when($params['branch_id'] ?? null, fn (Builder $q, int $branchId) => $q->where('e.branch_id', $branchId))
            ->groupBy('e.id', 'e.employee_code', 'e.first_name', 'e.last_name', 'd.name')
            ->orderBy('e.first_name')->orderBy('e.last_name')->orderBy('e.id')
            ->select(['e.id', 'e.employee_code', 'e.first_name', 'e.last_name', 'd.name as department'])
            ->selectRaw("SUM(ar.status = 'present') as present, SUM(ar.status = 'late') as late, SUM(ar.status = 'absent') as absent")
            ->selectRaw("SUM(ar.status = 'half_day') as half_day, SUM(ar.status = 'on_leave') as on_leave")
            ->selectRaw('COALESCE(SUM(ar.worked_minutes), 0) as worked_minutes, COALESCE(SUM(ar.late_minutes), 0) as late_minutes');
    }

    protected function map(object $row): array
    {
        return [
            'employee_code' => $row->employee_code,
            'employee' => trim($row->first_name.' '.$row->last_name),
            'department' => $row->department,
            'present' => (int) $row->present,
            'late' => (int) $row->late,
            'absent' => (int) $row->absent,
            'half_day' => (int) $row->half_day,
            'on_leave' => (int) $row->on_leave,
            'worked_hours' => number_format(((int) $row->worked_minutes) / 60, 2, '.', ''),
            'late_minutes' => (int) $row->late_minutes,
        ];
    }
}
