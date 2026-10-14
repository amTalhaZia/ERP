<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Actions\HR\GenerateLeaveBalancesAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\HR\LeaveBalanceResource;
use App\Models\HR\Employee;
use App\Models\HR\LeaveBalance;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LeaveBalanceController extends ApiController
{
    /** @var array<int, string> */
    private const RELATIONS = ['employee:id,first_name,last_name,employee_code', 'leaveType:id,name,code,color'];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', LeaveBalance::class);

        $query = LeaveBalance::query()->with(self::RELATIONS);
        $this->applyFilters($query, $request, ['employee_id', 'leave_type_id', 'year']);
        $this->applySort($query, $request, ['year', 'entitled', 'used', 'updated_at'], '-year');

        return $this->paginated($query->paginate($this->perPage($request)), LeaveBalanceResource::class);
    }

    /** The acting user's own balances (defaults to the current year). */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $employee = Employee::forUser($request->user());

        if ($employee === null) {
            throw ValidationException::withMessages(['employee' => ['Your account is not linked to an employee record in this company.']]);
        }

        $year = $request->integer('year', (int) now()->format('Y'));

        return LeaveBalanceResource::collection(
            LeaveBalance::query()->with(self::RELATIONS)->where('employee_id', $employee->id)->where('year', $year)->get(),
        );
    }

    /** Manual correction: `{ adjusted, entitled?, carried_forward? }`. */
    public function adjust(Request $request, LeaveBalance $leaveBalance, AuditLogger $audit): LeaveBalanceResource
    {
        Gate::authorize('adjust', $leaveBalance);

        $data = $request->validate([
            'adjusted' => ['required', 'numeric', 'between:-999.9,999.9'],
            'entitled' => ['sometimes', 'numeric', 'between:0,999.9'],
            'carried_forward' => ['sometimes', 'numeric', 'between:0,999.9'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $before = $leaveBalance->only(['entitled', 'carried_forward', 'adjusted']);
        $leaveBalance->fill(collect($data)->except('note')->all())->save();

        $audit->log('leave.balances.adjusted', $leaveBalance, $before, $leaveBalance->only(['entitled', 'carried_forward', 'adjusted']), [
            'note' => $data['note'] ?? null,
        ]);

        return new LeaveBalanceResource($leaveBalance->refresh()->load(self::RELATIONS));
    }

    public function generate(Request $request, GenerateLeaveBalancesAction $action): JsonResponse
    {
        Gate::authorize('generate', LeaveBalance::class);

        $year = (int) $request->validate(['year' => ['required', 'integer', 'between:2000,2100']])['year'];
        $created = $action->handle($year);

        return response()->json(['message' => "{$created} balance row(s) created for {$year}.", 'data' => ['year' => $year, 'created' => $created]]);
    }
}
