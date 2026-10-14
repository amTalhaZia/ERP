<?php

namespace App\Actions\HR;

use App\Enums\HR\AttendanceSource;
use App\Enums\HR\AttendanceStatus;
use App\Models\HR\AttendanceRecord;
use App\Models\HR\Employee;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Employee check-in / check-out for "today" (company timezone).
 */
class SelfServiceAttendanceAction
{
    public function __construct(
        private readonly AttendanceCalculator $calculator,
        private readonly TenantContext $tenant,
    ) {}

    public function checkIn(Employee $employee): AttendanceRecord
    {
        return DB::transaction(function () use ($employee): AttendanceRecord {
            $date = $this->today();
            $record = $this->todayRecord($employee, $date);

            if ($record->check_in !== null) {
                throw new ConflictHttpException('You have already checked in today.');
            }

            $now = now()->toImmutable();
            $computed = $this->calculator->compute($employee, $date, $now, null);

            $record->check_in = $now;
            $record->late_minutes = $computed['late_minutes'];
            $record->worked_minutes = 0;
            $record->status = $computed['late_minutes'] > 0 ? AttendanceStatus::Late : AttendanceStatus::Present;
            $record->source = AttendanceSource::SelfService;
            $record->save();

            return $record;
        });
    }

    public function checkOut(Employee $employee): AttendanceRecord
    {
        return DB::transaction(function () use ($employee): AttendanceRecord {
            $date = $this->today();
            $record = $this->todayRecord($employee, $date);

            if ($record->check_in === null) {
                throw new ConflictHttpException('Check in before checking out.');
            }
            if ($record->check_out !== null) {
                throw new ConflictHttpException('You have already checked out today.');
            }

            $now = now()->toImmutable();
            $computed = $this->calculator->compute($employee, $date, $record->check_in->toImmutable(), $now);

            $record->check_out = $now;
            $record->worked_minutes = $computed['worked_minutes'];
            $record->late_minutes = $computed['late_minutes'];
            $record->save();

            return $record;
        });
    }

    private function today(): string
    {
        return now($this->tenant->company()?->timezone ?? 'UTC')->format('Y-m-d');
    }

    private function todayRecord(Employee $employee, string $date): AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->lockForUpdate()
            ->first() ?? new AttendanceRecord(['employee_id' => $employee->id, 'date' => $date]);
    }
}
