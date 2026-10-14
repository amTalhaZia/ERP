<?php

namespace App\Actions\HR;

use App\Enums\HR\LeaveRequestStatus;
use App\Models\HR\LeaveRequest;
use App\Models\User;
use App\Notifications\HR\LeaveRequestDecided;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RejectLeaveRequestAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(LeaveRequest $request, User $approver, string $reason): LeaveRequest
    {
        $request = DB::transaction(function () use ($request, $approver, $reason): LeaveRequest {
            /** @var LeaveRequest $locked */
            $locked = LeaveRequest::query()->with('employee')->lockForUpdate()->findOrFail($request->id);

            if (! $locked->isPending()) {
                throw new ConflictHttpException('Only pending leave requests can be rejected.');
            }

            $locked->fill([
                'status' => LeaveRequestStatus::Rejected,
                'approver_id' => $approver->id,
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            $this->audit->log('leave.requests.rejected', $locked, ['status' => LeaveRequestStatus::Pending->value], [
                'status' => LeaveRequestStatus::Rejected->value,
            ], ['reason' => $reason], entityLabel: $locked->employee->fullName());

            return $locked;
        });

        $request->loadMissing(['employee.user', 'leaveType']);
        $request->employee->user?->notify(new LeaveRequestDecided($request));

        return $request;
    }
}
