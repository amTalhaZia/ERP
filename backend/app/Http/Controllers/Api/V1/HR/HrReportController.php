<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Enums\HR\EmployeeStatus;
use App\Enums\HR\LeaveRequestStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\HR\AttendanceRecord;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Aggregate HR reports. Everything is computed in SQL (GROUP BY / SUM); no
 * row-by-row PHP so the endpoints stay flat as headcount grows.
 */
class HrReportController extends ApiController
{
    public function headcount(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $range = $this->range($request, required: false);
        $employed = fn () => Employee::query()->visibleBranches()->employed();

        $byDepartment = $employed()
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->groupBy('employees.department_id', 'departments.name')
            ->orderByDesc('total')
            ->selectRaw('employees.department_id AS id, departments.name AS name, COUNT(*) AS total')
            ->toBase()->get()
            ->map(fn (object $r) => ['id' => $r->id === null ? null : (int) $r->id, 'name' => $r->name ?? 'Unassigned', 'total' => (int) $r->total]);

        $byBranch = $employed()
            ->leftJoin('branches', 'branches.id', '=', 'employees.branch_id')
            ->groupBy('employees.branch_id', 'branches.name')
            ->orderByDesc('total')
            ->selectRaw('employees.branch_id AS id, branches.name AS name, COUNT(*) AS total')
            ->toBase()->get()
            ->map(fn (object $r) => ['id' => $r->id === null ? null : (int) $r->id, 'name' => $r->name ?? 'Unassigned', 'total' => (int) $r->total]);

        $byType = $employed()
            ->groupBy('employment_type')
            ->selectRaw('employment_type AS `key`, COUNT(*) AS total')
            ->toBase()->get()
            ->map(fn (object $r) => ['key' => $r->key, 'total' => (int) $r->total]);

        $byStatus = Employee::query()->visibleBranches()
            ->groupBy('status')
            ->selectRaw('status AS `key`, COUNT(*) AS total')
            ->toBase()->get()
            ->map(fn (object $r) => ['key' => $r->key, 'total' => (int) $r->total]);

        $joiners = null;
        $leavers = null;

        if ($range !== null) {
            $joiners = Employee::query()->visibleBranches()->whereBetween('hired_at', [$range['from'], $range['to']])->count();
            $leavers = Employee::query()->visibleBranches()
                ->where('status', EmployeeStatus::Terminated->value)
                ->whereBetween('terminated_at', [$range['from'], $range['to']])
                ->count();
        }

        return response()->json(['data' => [
            'total' => $employed()->count(),
            'by_department' => $byDepartment,
            'by_branch' => $byBranch,
            'by_employment_type' => $byType,
            'by_status' => $byStatus,
            'joiners' => $joiners,
            'leavers' => $leavers,
            'from' => $range['from'] ?? null,
            'to' => $range['to'] ?? null,
        ]]);
    }

    public function attendance(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $range = $this->range($request, required: true);

        $byDepartment = AttendanceRecord::query()->visibleBranches()
            ->join('employees', 'employees.id', '=', 'attendance_records.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->whereBetween('attendance_records.date', [$range['from'], $range['to']])
            ->groupBy('employees.department_id', 'departments.name')
            ->orderBy('departments.name')
            ->selectRaw(implode(', ', [
                'employees.department_id AS id',
                'departments.name AS name',
                'COUNT(DISTINCT attendance_records.employee_id) AS employees',
                'COUNT(*) AS records',
                "SUM(attendance_records.status = 'present') AS present",
                "SUM(attendance_records.status = 'late') AS late",
                "SUM(attendance_records.status = 'absent') AS absent",
                "SUM(attendance_records.status = 'half_day') AS half_day",
                "SUM(attendance_records.status = 'on_leave') AS on_leave",
                'SUM(attendance_records.worked_minutes) AS worked_minutes',
                'SUM(attendance_records.late_minutes) AS late_minutes',
            ]))
            ->toBase()->get()
            ->map(fn (object $r) => [
                'id' => $r->id === null ? null : (int) $r->id,
                'name' => $r->name ?? 'Unassigned',
                'employees' => (int) $r->employees,
                'records' => (int) $r->records,
                'present' => (int) $r->present,
                'late' => (int) $r->late,
                'absent' => (int) $r->absent,
                'half_day' => (int) $r->half_day,
                'on_leave' => (int) $r->on_leave,
                'worked_hours' => round(((int) $r->worked_minutes) / 60, 2),
                'late_minutes' => (int) $r->late_minutes,
            ]);

        $totals = AttendanceRecord::query()->visibleBranches()
            ->whereBetween('date', [$range['from'], $range['to']])
            ->selectRaw(implode(', ', [
                'COUNT(*) AS records',
                "SUM(status = 'present') AS present",
                "SUM(status = 'late') AS late",
                "SUM(status = 'absent') AS absent",
                'SUM(worked_minutes) AS worked_minutes',
            ]))
            ->toBase()->first();

        return response()->json(['data' => [
            'from' => $range['from'],
            'to' => $range['to'],
            'totals' => [
                'records' => (int) ($totals->records ?? 0),
                'present' => (int) ($totals->present ?? 0),
                'late' => (int) ($totals->late ?? 0),
                'absent' => (int) ($totals->absent ?? 0),
                'worked_hours' => round(((int) ($totals->worked_minutes ?? 0)) / 60, 2),
            ],
            'by_department' => $byDepartment,
        ]]);
    }

    public function leave(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $range = $this->range($request, required: true);

        $byType = LeaveRequest::query()->visibleBranches()
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('leave_requests.status', LeaveRequestStatus::Approved->value)
            ->whereBetween('leave_requests.start_date', [$range['from'], $range['to']])
            ->groupBy('leave_requests.leave_type_id', 'leave_types.name', 'leave_types.code', 'leave_types.color')
            ->orderByDesc('days')
            ->selectRaw('leave_requests.leave_type_id AS id, leave_types.name, leave_types.code, leave_types.color, COUNT(*) AS requests, SUM(leave_requests.days) AS days')
            ->toBase()->get()
            ->map(fn (object $r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'code' => $r->code,
                'color' => $r->color,
                'requests' => (int) $r->requests,
                'days' => number_format((float) $r->days, 1, '.', ''),
            ]);

        $byStatus = LeaveRequest::query()->visibleBranches()
            ->whereBetween('start_date', [$range['from'], $range['to']])
            ->groupBy('status')
            ->selectRaw('status AS `key`, COUNT(*) AS total, SUM(days) AS days')
            ->toBase()->get()
            ->map(fn (object $r) => ['key' => $r->key, 'total' => (int) $r->total, 'days' => number_format((float) $r->days, 1, '.', '')]);

        return response()->json(['data' => [
            'from' => $range['from'],
            'to' => $range['to'],
            'approved_by_type' => $byType,
            'by_status' => $byStatus,
        ]]);
    }

    /**
     * @return array{from: string, to: string}|null
     */
    private function range(Request $request, bool $required): ?array
    {
        $rule = $required ? 'required' : 'nullable';

        $data = $request->validate([
            'from' => [$rule, 'date_format:Y-m-d', 'required_with:to'],
            'to' => [$rule, 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
        ]);

        if (empty($data['from']) || empty($data['to'])) {
            return null;
        }

        return ['from' => $data['from'], 'to' => $data['to']];
    }
}
