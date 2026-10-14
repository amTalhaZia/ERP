<?php

namespace Database\Factories\Payroll;

use App\Enums\Payroll\CalculationMethod;
use App\Enums\Payroll\PayComponentKind;
use App\Models\Company;
use App\Models\Payroll\PayComponent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PayComponent>
 */
class PayComponentFactory extends Factory
{
    protected $model = PayComponent::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => 'ALW'.Str::upper(Str::random(5)),
            'name' => 'Housing allowance',
            'kind' => PayComponentKind::Earning,
            'calculation' => CalculationMethod::Fixed,
            'default_amount' => '500.0000',
            'default_rate' => '0.0000',
            'is_taxable' => true,
            'reduces_taxable' => false,
            'affects_gross' => true,
            'gl_account_code' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function deduction(string $rate = '10', CalculationMethod $calculation = CalculationMethod::PercentOfBase): static
    {
        return $this->state([
            'code' => 'DED'.Str::upper(Str::random(5)),
            'name' => 'Pension (employee)',
            'kind' => PayComponentKind::Deduction,
            'calculation' => $calculation,
            'default_amount' => '0.0000',
            'default_rate' => $rate,
            'is_taxable' => false,
        ]);
    }

    public function employerContribution(string $rate = '5'): static
    {
        return $this->state([
            'code' => 'ER'.Str::upper(Str::random(5)),
            'name' => 'Pension (employer)',
            'kind' => PayComponentKind::EmployerContribution,
            'calculation' => CalculationMethod::PercentOfBase,
            'default_amount' => '0.0000',
            'default_rate' => $rate,
            'is_taxable' => false,
        ]);
    }
}
