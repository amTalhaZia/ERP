<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\HR\StoreLeaveTypeRequest;
use App\Http\Resources\HR\LeaveTypeResource;
use App\Models\HR\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class LeaveTypeController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', LeaveType::class);

        $query = LeaveType::query();
        $this->applySearch($query, $request, ['name', 'code']);
        $this->applyFilters($query, $request, ['is_active', 'is_paid']);
        $this->applySort($query, $request, ['name', 'code', 'days_per_year', 'created_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), LeaveTypeResource::class);
    }

    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        Gate::authorize('create', LeaveType::class);

        $type = LeaveType::query()->create($request->validated());

        return (new LeaveTypeResource($type))->response()->setStatusCode(201);
    }

    public function show(LeaveType $leaveType): LeaveTypeResource
    {
        Gate::authorize('view', $leaveType);

        return new LeaveTypeResource($leaveType);
    }

    public function update(StoreLeaveTypeRequest $request, LeaveType $leaveType): LeaveTypeResource
    {
        Gate::authorize('update', $leaveType);

        $leaveType->fill($request->validated())->save();

        return new LeaveTypeResource($leaveType->refresh());
    }

    public function destroy(LeaveType $leaveType): JsonResponse
    {
        Gate::authorize('delete', $leaveType);

        if ($leaveType->requests()->exists()) {
            return $this->message('Leave requests reference this type; deactivate it instead.', ['code' => 'conflict'], 409);
        }

        $leaveType->delete();

        return $this->noContent();
    }
}
