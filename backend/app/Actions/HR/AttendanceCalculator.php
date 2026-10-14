<?php

namespace App\Actions\HR;

use App\Models\HR\Employee;
use App\Models\HR\Shift;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;

/**
 * Derives worked/late minutes for a day from the employee's effective shift.
 * Shift times are interpreted in the company timezone; timestamps are UTC.
 */
class AttendanceCalculator
{
    private const MAX_WORKED_MINUTES = 1440;

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return array{worked_minutes: int, late_minutes: int, shift: Shift|null}
     */
    public function compute(Employee $employee, string $date, ?CarbonInterface $checkIn, ?CarbonInterface $checkOut): array
    {
        $shift = $employee->shiftOn($date);
        $timezone = $this->tenant->company()?->timezone ?? 'UTC';

        $late = 0;
        if ($checkIn !== null && $shift !== null) {
            $late = (int) max(0, intdiv($checkIn->getTimestamp() - $shift->startsAt($date, $timezone)->getTimestamp(), 60));
        }

        $worked = 0;
        if ($checkIn !== null && $checkOut !== null) {
            $gross = intdiv($checkOut->getTimestamp() - $checkIn->getTimestamp(), 60);
            $worked = (int) min(self::MAX_WORKED_MINUTES, max(0, $gross - ($shift?->break_minutes ?? 0)));
        }

        return ['worked_minutes' => $worked, 'late_minutes' => $late, 'shift' => $shift];
    }
}
