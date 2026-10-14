<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\PeriodStatus;
use App\Models\Finance\FinancialPeriod;
use App\Models\Finance\FinancialYear;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<FinancialPeriod>
 */
class FinancialPeriodFactory extends Factory
{
    protected $model = FinancialPeriod::class;

    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'financial_year_id' => FinancialYear::factory(),
            'company_id' => fn (array $attrs) => FinancialYear::withoutTenancy()->findOrFail($attrs['financial_year_id'])->company_id,
            'name' => $start->format('M Y'),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'status' => PeriodStatus::Open,
        ];
    }

    public function forMonth(string $yearMonth): static
    {
        $start = Carbon::parse($yearMonth.'-01');

        return $this->state([
            'name' => $start->format('M Y'),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(['status' => PeriodStatus::Closed, 'closed_at' => now()]);
    }
}
