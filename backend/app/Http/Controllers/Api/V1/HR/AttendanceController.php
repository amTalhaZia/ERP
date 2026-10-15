<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Actions\HR\RecordAttendanceAction;
use App\Actions\HR\SelfServiceAttendanceAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\HR\BulkAttendanceRequest;
use App\Http\Requests\HR\StoreAttendanceRequest;
use App\Http\Resources\HR\AttendanceRecordResource;
use App\Models\HR\AttendanceRecord;
use App\Models\HR\Employee;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AttendanceController extends ApiController
{
    private const EMPLOYEE_COLUMNS = 'employee:id,first_name,last_name,employee_code,department_id';

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', AttendanceRecord::class);

        $query = AttendanceRecord::query()->visibleBranches()->with(self::EMPLOYEE_COLUMNS);
        $this->applyFilters($query, $request, ['employee_id', 'status', 'source']);
        $this->applyDateRange($query, $request, 'date');

        if ($departmentId = $this->departmentFilter($request)) {
            $query->whereHas('employee', fn (Builder $q) => $q->where('department_id', $departmentId));
        }

        $this->applySort($query, $request, ['date', 'status', 'worked_minutes', 'late_minutes', 'created_at'], '-date');

        return $this->paginated($query->paginate($this->perPage($request)), AttendanceRecordResource::class);
    }

    public function store(StoreAttendanceRequest $request, RecordAttendanceAction $action): JsonResponse
    {
        Gate::authorize('create', AttendanceRecord::class);

        $data = $request->validated();
        $employee = Employee::query()->findOrFail($data['employee_id']);
        $record = $action->handle($employee, $data['date'], $data);

        return (new AttendanceRecordResource($record->load(self::EMPLOYEE_COLUMNS)))
            ->response()->setStatusCode($record->wasRecentlyCreated ? 201 : 200);
    }

    public function bulk(BulkAttendanceRequest $request, RecordAttendanceAction $action): JsonResponse
    {
        Gate::authorize('create', AttendanceRecord::class);

        $written = $action->bulk($request->validated('date'), $request->validated('records'), $request->validated('source', 'manual'));

        return response()->json(['message' => "{$written} attendance record(s) saved.", 'data' => ['written' => $written]]);
    }

    public function show(AttendanceRecord $attendance): AttendanceRecordResource
    {
        Gate::authorize('view', $attendance);

        return new AttendanceRecordResource($attendance->load(self::EMPLOYEE_COLUMNS));
    }

    public function update(StoreAttendanceRequest $request, AttendanceRecord $attendance, RecordAttendanceAction $action): AttendanceRecordResource
    {
        Gate::authorize('update', $attendance);

        $attendance->loadMissing('employee');
        $record = $action->handle($attendance->employee, $attendance->date->toDateString(), $request->validated());

        return new AttendanceRecordResource($record->load(self::EMPLOYEE_COLUMNS));
    }

    public function destroy(AttendanceRecord $attendance, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('delete', $attendance);

        $attendance->delete();
        $audit->log('attendance.deleted', $attendance, ['employee_id' => $attendance->employee_id, 'date' => $attendance->date->toDateString()]);

        return $this->noContent();
    }

    // -- Self-service ----------------------------------------------------------

    public function checkIn(Request $request, SelfServiceAttendanceAction $action): JsonResponse
    {
        $record = $action->checkIn($this->ownEmployee($request));

        return (new AttendanceRecordResource($record))->response()->setStatusCode(201);
    }

    public function checkOut(Request $request, SelfServiceAttendanceAction $action): AttendanceRecordResource
    {
        return new AttendanceRecordResource($action->checkOut($this->ownEmployee($request)));
    }

    // -- Aggregates ------------------------------------------------------------

    /** Per-employee totals for a period: `?from&to&department_id`. */
    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AttendanceRecord::class);

        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'department_id' => ['nullable', 'integer'],
        ]);

        $rows = AttendanceRecord::query()->visibleBranches()
            ->join('employees', 'employees.id', '=', 'attendance_records.employee_id')
            ->whereBetween('attendance_records.date', [$validated['from'], $validated['to']])
            ->when($validated['department_id'] ?? null, fn (Builder $q, int $id) => $q->where('employees.department_id', $id))
            ->groupBy('attendance_records.employee_id', 'employees.first_name', 'employees.last_name', 'employees.employee_code', 'employees.department_id')
            ->orderBy('employees.last_name')
            ->selectRaw(implode(', ', [
                'attendance_records.employee_id',
                'employees.first_name',
                'employees.last_name',
                'employees.employee_code',
                'employees.department_id',
                "SUM(attendance_records.status = 'present') AS present_days",
                "SUM(attendance_records.status = 'late') AS late_days",
                "SUM(attendance_records.status = 'absent') AS absent_days",
                "SUM(attendance_records.status = 'half_day') AS half_days",
                "SUM(attendance_records.status = 'on_leave') AS leave_days",
                'SUM(attendance_records.worked_minutes) AS worked_minutes',
                'SUM(attendance_records.late_minutes) AS late_minutes',
            ]))
            ->toBase()
            ->get()
            ->map(fn (object $r) => [
                'employee_id' => (int) $r->employee_id,
                'employee_code' => $r->employee_code,
                'name' => trim($r->first_name.' '.$r->last_name),
                'department_id' => $r->department_id === null ? null : (int) $r->department_id,
                'present_days' => (int) $r->present_days,
                'late_days' => (int) $r->late_days,
                'absent_days' => (int) $r->absent_days,
                'half_days' => (int) $r->half_days,
                'leave_days' => (int) $r->leave_days,
                'worked_minutes' => (int) $r->worked_minutes,
                'worked_hours' => round(((int) $r->worked_minutes) / 60, 2),
                'late_minutes' => (int) $r->late_minutes,
            ]);

        return response()->json(['data' => $rows, 'meta' => ['from' => $validated['from'], 'to' => $validated['to']]]);
    }

    private function ownEmployee(Request $request): Employee
    {
        $employee = Employee::forUser($request->user());

        if ($employee === null) {
            throw ValidationException::withMessages(['employee' => ['Your account is not linked to an employee record in this company.']]);
        }

        if ($employee->isTerminated()) {
            throw ValidationException::withMessages(['employee' => ['Your employee record is terminated.']]);
        }

        return $employee;
    }

    /**
     * GET /attendance/today — the caller's own attendance record for today
     * (company timezone) or null, plus the shift that applies today. Any
     * member may call it; members without an employee record get 404.
     */
    public function today(Request $request, TenantContext $tenant): JsonResponse
    {
        $employee = Employee::forUser($request->user());

        if ($employee === null) {
            return $this->message('Your account is not linked to an employee record in this company.', ['code' => 'hr.no_employee_record'], 404);
        }

        $timezone = $tenant->company()?->timezone ?? 'UTC';
        $date = now($timezone)->format('Y-m-d');
        $record = AttendanceRecord::query()->where('employee_id', $employee->id)->whereDate('date', $date)->first();
        $shift = $employee->shiftOn($date);

        return response()->json([
            'data' => [
                'date' => $date,
                'timezone' => $timezone,
                'employee_id' => $employee->id,
                'record' => $record === null ? null : (new AttendanceRecordResource($record))->resolve($request),
                'shift' => $shift === null ? null : [
                    'id' => $shift->id,
                    'name' => $shift->name,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                    'break_minutes' => $shift->break_minutes,
                    'is_working_day' => $shift->worksOn(CarbonImmutable::parse($date)),
                ],
                'can_check_in' => $record?->check_in === null && ! $employee->isTerminated(),
                'can_check_out' => $record?->check_in !== null && $record->check_out === null,
            ],
        ]);
    }

    /** `filter[department_id]` or the legacy plain `department_id`. */
    private function departmentFilter(Request $request): int
    {
        return (int) ($request->input('filter.department_id') ?: $request->integer('department_id'));
    }
}
