<?php

namespace App\Actions\HR;

use App\Models\HR\Holiday;
use App\Models\User;
use App\Notifications\HR\HolidayAnnounced;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Notifies the active members of the holiday's company. A branch holiday only
 * reaches members who can work in that branch (unrestricted members or those
 * whose allowed branches include it). Past dates are never announced.
 */
class AnnounceHolidayAction
{
    /** @return int number of people notified */
    public function handle(Holiday $holiday): int
    {
        if ($holiday->date->lt(now()->startOfDay())) {
            return 0;
        }

        $holiday->loadMissing('branch:id,name');
        $branchId = $holiday->branch_id;

        $sent = 0;
        User::query()
            ->where('is_active', true)
            ->whereHas('memberships', function (Builder $q) use ($holiday, $branchId): void {
                $q->where('company_id', $holiday->company_id)->where('is_active', true);

                if ($branchId !== null) {
                    $q->where(fn (Builder $b) => $b->whereNull('branch_ids')->orWhereJsonContains('branch_ids', $branchId));
                }
            })
            ->select(['id', 'name', 'email', 'preferences'])
            ->chunkById(500, function ($users) use ($holiday, &$sent): void {
                Notification::send($users, new HolidayAnnounced($holiday));
                $sent += $users->count();
            });

        return $sent;
    }
}
