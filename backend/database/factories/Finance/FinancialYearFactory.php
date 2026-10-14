<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\PeriodStatus;
use App\Models\Company;
use App\Models\Finance\FinancialYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialYear>
 */
class FinancialYearFactory extends Factory
{
    protected $model = FinancialYear::class;

    public function definition(): array
    {
        $year = (int) now()->format('Y');

        return [
            'company_id' => Company::factory(),
            'name' => "FY {$year}",
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'status' => PeriodStatus::Open,
        ];
    }

    public function forCalendarYear(int $year): static
    {
        return $this->state(['name' => "FY {$year}", 'start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31"]);
    }
}
