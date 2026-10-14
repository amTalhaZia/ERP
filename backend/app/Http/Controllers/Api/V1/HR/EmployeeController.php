<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Actions\HR\CreateEmployeeAction;
use App\Actions\HR\ReactivateEmployeeAction;
use App\Actions\HR\TerminateEmployeeAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\HR\StoreEmployeeRequest;
use App\Http\Requests\HR\TerminateEmployeeRequest;
use App\Http\Resources\HR\EmployeeResource;
use App\Models\HR\Employee;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends ApiController
{
    /** @var array<int, string> */
    private const RELATIONS = [
        'branch:id,name', 'department:id,name', 'designation:id,title',
        'manager:id,first_name,last_name,employee_code', 'user:id,email',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Employee::class);

        $query = Employee::query()->with(self::RELATIONS);
        $this->applySearch($query, $request, ['first_name', 'last_name', 'email', 'employee_code', 'phone']);
        $this->applyFilters($query, $request, ['status', 'department_id', 'branch_id', 'designation_id', 'employment_type', 'manager_id']);
        $this->applyDateRange($query, $request, 'hired_at');
        $this->applySort($query, $request, ['first_name', 'last_name', 'employee_code', 'email', 'hired_at', 'status', 'created_at'], 'last_name');

        return $this->paginated($query->paginate($this->perPage($request)), EmployeeResource::class);
    }

    /** Lightweight list of everyone on the books (selectors, org chart). */
    public function directory(): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $rows = Employee::query()->employed()
            ->with(['designation:id,title', 'department:id,name'])
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'employee_code', 'first_name', 'last_name', 'email', 'department_id', 'branch_id', 'designation_id', 'manager_id', 'status', 'photo_path'])
            ->map(fn (Employee $e) => $this->directoryRow($e));

        return response()->json(['data' => $rows]);
    }

    /** Reporting-line tree (roots are employees without a manager). */
    public function orgChart(): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $employees = Employee::query()->employed()
            ->with(['designation:id,title', 'department:id,name'])
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'employee_code', 'first_name', 'last_name', 'email', 'department_id', 'branch_id', 'designation_id', 'manager_id', 'status', 'photo_path']);

        $ids = $employees->pluck('id')->flip();
        // Employees whose manager is terminated/missing become roots so nobody disappears from the chart.
        $byManager = $employees->groupBy(fn (Employee $e) => $e->manager_id !== null && $ids->has($e->manager_id) ? $e->manager_id : 0);

        $build = function (int $managerId) use (&$build, $byManager): array {
            return $byManager->get($managerId, collect())
                ->map(fn (Employee $e) => $this->directoryRow($e) + ['children' => $build($e->id)])
                ->values()->all();
        };

        return response()->json(['data' => $build(0)]);
    }

    public function store(StoreEmployeeRequest $request, CreateEmployeeAction $action): JsonResponse
    {
        Gate::authorize('create', Employee::class);

        $employee = $action->handle($request->validated());

        return (new EmployeeResource($employee->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(Employee $employee): EmployeeResource
    {
        Gate::authorize('view', $employee);

        return (new EmployeeResource($employee->load(self::RELATIONS)->loadCount('reports')))
            ->withSensitive(Gate::allows('viewSensitive', $employee));
    }

    public function update(StoreEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        Gate::authorize('update', $employee);

        $employee->fill($request->validated())->save();

        return (new EmployeeResource($employee->refresh()->load(self::RELATIONS)))
            ->withSensitive(Gate::allows('viewSensitive', $employee));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        Gate::authorize('delete', $employee);

        if ($employee->reports()->exists()) {
            return $this->message('Reassign this employee\'s direct reports first.', ['code' => 'conflict'], 409);
        }

        $employee->delete();

        return $this->noContent();
    }

    public function terminate(TerminateEmployeeRequest $request, Employee $employee, TerminateEmployeeAction $action): EmployeeResource
    {
        Gate::authorize('terminate', $employee);

        if ($employee->isTerminated()) {
            abort(409, 'This employee is already terminated.');
        }

        $employee = $action->handle($employee, $request->validated('terminated_at'), $request->validated('reason'));

        return new EmployeeResource($employee->load(self::RELATIONS));
    }

    public function reactivate(Employee $employee, ReactivateEmployeeAction $action): EmployeeResource
    {
        Gate::authorize('reactivate', $employee);

        if (! $employee->isTerminated()) {
            abort(409, 'Only terminated employees can be reactivated.');
        }

        return new EmployeeResource($action->handle($employee)->load(self::RELATIONS));
    }

    public function uploadPhoto(Request $request, Employee $employee, AuditLogger $audit): EmployeeResource
    {
        Gate::authorize('uploadPhoto', $employee);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.config('erp.uploads.image_max_kb')],
        ]);

        if ($employee->photo_path) {
            Storage::disk('public')->delete($employee->photo_path);
        }

        $path = $request->file('photo')->store("companies/{$employee->company_id}/employees/{$employee->id}", 'public');
        $employee->forceFill(['photo_path' => $path])->save();

        $audit->log('employees.photo_updated', $employee, entityLabel: $employee->fullName());

        return new EmployeeResource($employee->load(self::RELATIONS));
    }

    /**
     * @return array<string, mixed>
     */
    private function directoryRow(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => $employee->fullName(),
            'email' => $employee->email,
            'status' => $employee->status?->value,
            'department_id' => $employee->department_id,
            'department' => $employee->department?->name,
            'branch_id' => $employee->branch_id,
            'designation_id' => $employee->designation_id,
            'designation' => $employee->designation?->title,
            'manager_id' => $employee->manager_id,
            'photo_url' => $employee->photoUrl(),
        ];
    }

    /**
     * GET /employees/me — the current user's own employee record in this
     * company. Any active member may call it (no employees.view needed); the
     * masked sensitive block and internal HR notes are never included.
     */
    public function me(Request $request): JsonResponse
    {
        $employee = Employee::forUser($request->user());

        if ($employee === null) {
            return $this->message('Your account is not linked to an employee record in this company.', ['code' => 'hr.no_employee_record'], 404);
        }

        $data = (new EmployeeResource($employee->load(self::RELATIONS)))->withSensitive(false)->resolve($request);
        unset($data['notes']);

        return response()->json(['data' => $data]);
    }
}
