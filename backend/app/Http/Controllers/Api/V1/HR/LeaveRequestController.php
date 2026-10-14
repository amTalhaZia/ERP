<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Actions\HR\ApproveLeaveRequestAction;
use App\Actions\HR\CancelLeaveRequestAction;
use App\Actions\HR\RejectLeaveRequestAction;
use App\Actions\HR\SubmitLeaveRequestAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\HR\StoreLeaveRequestRequest;
use App\Http\Resources\HR\LeaveRequestResource;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestController extends ApiController
{
    /** @var array<int, string> */
    private const RELATIONS = [
        'employee:id,first_name,last_name,employee_code,department_id',
        'leaveType:id,name,code,is_paid,color',
        'approver:id,name',
    ];

    /** Holders of leave.view/approve/manage see everything; others only their own requests. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', LeaveRequest::class);

        $query = LeaveRequest::query()->with(self::RELATIONS);

        if (! Gate::allows('viewAll', LeaveRequest::class)) {
            $query->whereHas('employee', fn (Builder $q) => $q->where('user_id', $request->user()->id));
        }

        $this->applyFilters($query, $request, ['status', 'leave_type_id', 'employee_id']);
        $this->applyDateRange($query, $request, 'start_date');

        if ($departmentId = (int) ($request->input('filter.department_id') ?: $request->integer('department_id'))) {
            $query->whereHas('employee', fn (Builder $q) => $q->where('department_id', $departmentId));
        }

        $this->applySort($query, $request, ['start_date', 'end_date', 'days', 'status', 'created_at'], '-created_at');

        return $this->paginated($query->paginate($this->perPage($request)), LeaveRequestResource::class);
    }

    public function store(StoreLeaveRequestRequest $request, SubmitLeaveRequestAction $action): JsonResponse
    {
        Gate::authorize('create', LeaveRequest::class);

        $employee = Employee::forUser($request->user());

        if ($employee === null || $employee->isTerminated()) {
            throw ValidationException::withMessages(['employee' => ['Your account is not linked to an active employee record in this company.']]);
        }

        $leaveRequest = $action->handle($employee, $request->safe()->except('attachment'), $request->file('attachment'));

        return (new LeaveRequestResource($leaveRequest->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(LeaveRequest $leaveRequest): LeaveRequestResource
    {
        Gate::authorize('view', $leaveRequest);

        return new LeaveRequestResource($leaveRequest->load(self::RELATIONS));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest, ApproveLeaveRequestAction $action): LeaveRequestResource
    {
        Gate::authorize('approve', $leaveRequest);

        return new LeaveRequestResource($action->handle($leaveRequest, $request->user())->load(self::RELATIONS));
    }

    public function reject(Request $request, LeaveRequest $leaveRequest, RejectLeaveRequestAction $action): LeaveRequestResource
    {
        Gate::authorize('reject', $leaveRequest);

        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        return new LeaveRequestResource($action->handle($leaveRequest, $request->user(), $reason)->load(self::RELATIONS));
    }

    public function cancel(LeaveRequest $leaveRequest, CancelLeaveRequestAction $action): LeaveRequestResource
    {
        Gate::authorize('cancel', $leaveRequest);

        return new LeaveRequestResource($action->handle($leaveRequest)->load(self::RELATIONS));
    }

    /** GET /leave-requests/{id}/attachment — owner or leave viewers/approvers; streamed from the private disk and audited. */
    public function attachment(LeaveRequest $leaveRequest, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('downloadAttachment', $leaveRequest);

        if ($leaveRequest->attachment_path === null || ! Storage::disk('local')->exists($leaveRequest->attachment_path)) {
            abort(404);
        }

        $audit->log('leave.requests.attachment_downloaded', $leaveRequest, context: ['employee_id' => $leaveRequest->employee_id]);

        $extension = pathinfo($leaveRequest->attachment_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $leaveRequest->attachment_path,
            "leave-request-{$leaveRequest->id}".($extension ? ".{$extension}" : ''),
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }
}
