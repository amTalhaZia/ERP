<?php

namespace App\Actions\Finance;

use App\Enums\Finance\PeriodStatus;
use App\Models\Company;
use App\Models\Finance\FinancialPeriod;
use App\Models\Finance\FinancialYear;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a 12-month financial year with monthly periods. Without an explicit
 * start date the year is the one containing today, anchored on the company's
 * fiscal_year_start_month.
 */
class GeneratePeriodsAction
{
    public function handle(Company $company, ?string $startDate = null, ?string $name = null): FinancialYear
    {
        $start = $startDate === null
            ? self::currentYearStart($company)
            : Carbon::parse($startDate)->startOfDay();

        if ($start->day !== 1) {
            throw ValidationException::withMessages(['start_date' => 'The financial year must start on the first day of a month.']);
        }

        $end = $start->copy()->addYear()->subDay();

        return DB::transaction(function () use ($company, $start, $end, $name): FinancialYear {
            $overlaps = FinancialYear::forCompany($company->id)
                ->where('start_date', '<=', $end->toDateString())
                ->where('end_date', '>=', $start->toDateString())
                ->exists();

            if ($overlaps) {
                throw ValidationException::withMessages(['start_date' => 'A financial year already covers part of this range.']);
            }

            $year = FinancialYear::query()->create([
                'company_id' => $company->id,
                'name' => $name ?? self::defaultName($start, $end),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => PeriodStatus::Open,
            ]);

            for ($i = 0; $i < 12; $i++) {
                $periodStart = $start->copy()->addMonthsNoOverflow($i)->startOfMonth();
                $periodEnd = $periodStart->copy()->endOfMonth()->startOfDay();

                FinancialPeriod::query()->create([
                    'company_id' => $company->id,
                    'financial_year_id' => $year->id,
                    'name' => $periodStart->format('M Y'),
                    'start_date' => $periodStart->toDateString(),
                    'end_date' => $periodEnd->min($end)->toDateString(),
                    'status' => PeriodStatus::Open,
                ]);
            }

            return $year->load('periods');
        });
    }

    public static function currentYearStart(Company $company): Carbon
    {
        $month = max(1, min(12, (int) ($company->fiscal_year_start_month ?: 1)));
        $start = Carbon::create((int) now()->format('Y'), $month, 1)->startOfDay();

        return $start->isAfter(now()) ? $start->subYear() : $start;
    }

    private static function defaultName(Carbon $start, Carbon $end): string
    {
        return $start->year === $end->year
            ? 'FY '.$start->year
            : 'FY '.$start->year.'/'.$end->format('y');
    }
}
