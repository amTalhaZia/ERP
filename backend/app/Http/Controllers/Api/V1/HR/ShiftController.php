<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\HR\AssignShiftRequest;
use App\Http\Requests\HR\StoreShiftRequest;
use App\Http\Resources\HR\EmployeeShiftResource;
use App\Http\Resources\HR\ShiftResource;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeShift;
use App\Models\HR\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ShiftController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Shift::class);

        $query = Shift::query()->withCount('assignments');
        $this->applySearch($query, $request, ['name']);
        $this->applyFilters($query, $request, ['is_default']);
        $this->applySort($query, $request, ['name', 'start_time', 'created_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), ShiftResource::class);
    }

    public function store(StoreShiftRequest $request): JsonResponse
    {
        Gate::authorize('create', Shift::class);

        $shift = DB::transaction(function () use ($request): Shift {
            $data = $request->validated();

            if ($data['is_default'] ?? false) {
                Shift::query()->where('is_default', true)->update(['is_default' => false]);
            }

            return Shift::query()->create($data);
        });

        return (new ShiftResource($shift))->response()->setStatusCode(201);
    }

    public function show(Shift $shift): ShiftResource
    {
        Gate::authorize('view', $shift);

        return new ShiftResource($shift->loadCount('assignments'));
    }

    public function update(StoreShiftRequest $request, Shift $shift): ShiftResource
    {
        Gate::authorize('update', $shift);

        DB::transaction(function () use ($request, $shift): void {
            $data = $request->validated();

            if (($data['is_default'] ?? false) && ! $shift->is_default) {
                Shift::query()->where('is_default', true)->update(['is_default' => false]);
            }

            $shift->fill($data)->save();
        });

        return new ShiftResource($shift->refresh());
    }

    public function destroy(Shift $shift): JsonResponse
    {
        Gate::authorize('delete', $shift);

        if ($shift->assignments()->whereNull('effective_to')->exists()) {
            return $this->message('Employees are still assigned to this shift.', ['code' => 'conflict'], 409);
        }

        $shift->delete();

        return $this->noContent();
    }

    /** Shift history for one employee. */
    public function assignments(Employee $employee): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Shift::class);
        Gate::authorize('view', $employee);

        return EmployeeShiftResource::collection(
            $employee->shiftAssignments()->with('shift:id,name')->orderByDesc('effective_from')->get(),
        );
    }

    /** Assign a shift; the previous open-ended assignment is closed the day before. */
    public function assign(AssignShiftRequest $request, Employee $employee): JsonResponse
    {
        Gate::authorize('assign', Shift::class);
        Gate::authorize('update', $employee);

        $assignment = DB::transaction(function () use ($request, $employee): EmployeeShift {
            $from = CarbonImmutable::parse($request->validated('effective_from'));

            $employee->shiftAssignments()
                ->whereNull('effective_to')
                ->where('effective_from', '<', $from->toDateString())
                ->update(['effective_to' => $from->subDay()->toDateString()]);

            return $employee->shiftAssignments()->create($request->validated());
        });

        return (new EmployeeShiftResource($assignment->load('shift:id,name')))->response()->setStatusCode(201);
    }
}
