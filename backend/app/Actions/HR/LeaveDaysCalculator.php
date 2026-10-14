<?php

namespace App\Actions\HR;

use App\Models\HR\Employee;
use App\Models\HR\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;

/**
 * Counts leave days between two dates, excluding non-working days (the
 * employee's shift pattern, or Sat/Sun when none) and company/branch holidays.
 */
class LeaveDaysCalculator
{
    public function days(Employee $employee, string $startDate, string $endDate): string
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();
        $shift = $employee->shiftOn($startDate);

        $holidays = Holiday::query()
            ->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhere('branch_id', $employee->branch_id))
            ->where(fn (Builder $q) => $q->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orWhere('is_recurring', true))
            ->get(['date', 'is_recurring']);

        $exact = $holidays->where('is_recurring', false)->map(fn (Holiday $h) => $h->date->toDateString())->flip();
        $recurring = $holidays->where('is_recurring', true)->map(fn (Holiday $h) => $h->date->format('m-d'))->flip();

        $count = 0;

        foreach (CarbonPeriod::create($start, $end) as $day) {
            $isWorkingDay = $shift !== null ? $shift->worksOn($day) : (int) $day->format('N') < 6;

            if (! $isWorkingDay || $exact->has($day->toDateString()) || $recurring->has($day->format('m-d'))) {
                continue;
            }

            $count++;
        }

        return number_format($count, 1, '.', '');
    }
}
