<?php

namespace App\Actions\HR;

use App\Enums\HR\LeaveRequestStatus;
use App\Models\HR\Employee;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Notifications\HR\LeaveRequestSubmitted;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SubmitLeaveRequestAction
{
    public function __construct(
        private readonly LeaveDaysCalculator $calculator,
        private readonly ApproveLeaveRequestAction $approve,
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array{leave_type_id: int, start_date: string, end_date: string, reason?: string|null}  $data
     */
    public function handle(Employee $employee, array $data, ?UploadedFile $attachment = null): LeaveRequest
    {
        $request = DB::transaction(function () use ($employee, $data, $attachment): LeaveRequest {
            /** @var LeaveType $type */
            $type = LeaveType::query()->findOrFail($data['leave_type_id']);
            $days = $this->calculator->days($employee, $data['start_date'], $data['end_date']);

            if (bccomp($days, '0', 1) <= 0) {
                throw ValidationException::withMessages(['start_date' => ['The selected range contains no working days.']]);
            }

            $this->assertNoOverlap($employee, $data['start_date'], $data['end_date']);

            if ($type->is_paid) {
                $this->assertSufficientBalance($employee, $type, (int) substr($data['start_date'], 0, 4), $days);
            }

            $path = $attachment?->store("companies/{$this->tenant->id()}/employees/{$employee->id}/leave", 'local');

            $request = LeaveRequest::query()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'days' => $days,
                'reason' => $data['reason'] ?? null,
                'status' => LeaveRequestStatus::Pending,
                'attachment_path' => $path ?: null,
            ]);

            $this->audit->log('leave.requests.submitted', $request, null, [
                'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'days' => $days,
            ], entityLabel: $employee->fullName());

            if (! $type->requires_approval) {
                $request = $this->approve->handle($request, null);
            }

            return $request;
        });

        if ($request->isPending()) {
            $this->notifyApprovers($employee, $request);
        }

        return $request;
    }

    private function assertNoOverlap(Employee $employee, string $start, string $end): void
    {
        $overlaps = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequestStatus::Pending->value, LeaveRequestStatus::Approved->value])
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['start_date' => ['You already have a leave request overlapping these dates.']]);
        }
    }

    private function assertSufficientBalance(Employee $employee, LeaveType $type, int $year, string $days): void
    {
        $balance = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->first();

        $remaining = $balance?->remaining() ?? '0.0';

        if (bccomp($remaining, $days, 1) < 0) {
            throw ValidationException::withMessages([
                'leave_type_id' => ["Insufficient {$type->name} balance: {$remaining} day(s) remaining, {$days} requested."],
            ]);
        }
    }

    /** Everyone in the company holding leave.approve, except the requester. */
    private function notifyApprovers(Employee $employee, LeaveRequest $request): void
    {
        $companyId = $this->tenant->id();

        $approvers = User::query()
            ->where('is_active', true)
            ->when($employee->user_id, fn (Builder $q) => $q->where('id', '!=', $employee->user_id))
            ->whereHas('memberships', fn (Builder $q) => $q->where('company_id', $companyId)->where('is_active', true))
            ->whereHas('roles', fn (Builder $q) => $q
                ->where('roles.company_id', $companyId)
                ->where('role_user.company_id', $companyId)
                ->whereHas('permissions', fn (Builder $p) => $p->where('slug', 'leave.approve')))
            ->get();

        if ($approvers->isNotEmpty()) {
            Notification::send($approvers, new LeaveRequestSubmitted($request, $employee->fullName()));
        }
    }
}
