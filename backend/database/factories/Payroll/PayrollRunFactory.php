<?php

namespace Database\Factories\Payroll;

use App\Enums\Payroll\PayrollRunStatus;
use App\Models\Company;
use App\Models\Payroll\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'PAYRUN-2026-'.fake()->unique()->numerify('9#####'),
            'name' => 'September 2026 payroll',
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'payment_date' => '2026-09-30',
            'currency' => 'USD',
            'status' => PayrollRunStatus::Draft,
            'scope_key' => fn (array $attrs) => PayrollRun::scopeKeyFor($attrs['period_start'], $attrs['period_end'], null, null),
        ];
    }
}
