<?php

namespace App\Actions\HR;

use App\Enums\HR\LeaveRequestStatus;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveRequest;
use App\Models\User;
use App\Notifications\HR\LeaveRequestDecided;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Approves a pending request and deducts the days from the employee's balance
 * for the year of the start date (paid types only), under a row lock.
 */
class ApproveLeaveRequestAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(LeaveRequest $request, ?User $approver): LeaveRequest
    {
        $request = DB::transaction(function () use ($request, $approver): LeaveRequest {
            /** @var LeaveRequest $locked */
            $locked = LeaveRequest::query()->with(['leaveType', 'employee'])->lockForUpdate()->findOrFail($request->id);

            if (! $locked->isPending()) {
                throw new ConflictHttpException('Only pending leave requests can be approved.');
            }

            if ($locked->leaveType->is_paid) {
                $balance = LeaveBalance::query()
                    ->where('employee_id', $locked->employee_id)
                    ->where('leave_type_id', $locked->leave_type_id)
                    ->where('year', $locked->start_date->year)
                    ->lockForUpdate()
                    ->first();

                if ($balance === null || bccomp($balance->remaining(), (string) $locked->days, 1) < 0) {
                    throw ValidationException::withMessages([
                        'leave_type_id' => ['The employee no longer has enough balance for this request.'],
                    ]);
                }

                $balance->used = bcadd((string) $balance->used, (string) $locked->days, 1);
                $balance->save();
            }

            $locked->fill([
                'status' => LeaveRequestStatus::Approved,
                'approver_id' => $approver?->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $this->audit->log('leave.requests.approved', $locked, ['status' => LeaveRequestStatus::Pending->value], [
                'status' => LeaveRequestStatus::Approved->value, 'days' => (string) $locked->days,
            ], ['auto' => $approver === null], entityLabel: $locked->employee->fullName());

            return $locked;
        });

        $this->notifyRequester($request);

        return $request;
    }

    private function notifyRequester(LeaveRequest $request): void
    {
        $request->loadMissing(['employee.user', 'leaveType']);

        $request->employee->user?->notify(new LeaveRequestDecided($request));
    }
}
