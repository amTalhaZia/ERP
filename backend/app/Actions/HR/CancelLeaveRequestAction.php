<?php

namespace App\Actions\HR;

use App\Enums\HR\LeaveRequestStatus;
use App\Models\HR\LeaveRequest;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Requester withdraws a pending request. Nothing was deducted, so nothing is restored.
 */
class CancelLeaveRequestAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(LeaveRequest $request): LeaveRequest
    {
        return DB::transaction(function () use ($request): LeaveRequest {
            /** @var LeaveRequest $locked */
            $locked = LeaveRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! $locked->isPending()) {
                throw new ConflictHttpException('Only pending leave requests can be cancelled.');
            }

            $locked->fill(['status' => LeaveRequestStatus::Cancelled])->save();

            $this->audit->log('leave.requests.cancelled', $locked, ['status' => LeaveRequestStatus::Pending->value], [
                'status' => LeaveRequestStatus::Cancelled->value,
            ]);

            return $locked;
        });
    }
}
