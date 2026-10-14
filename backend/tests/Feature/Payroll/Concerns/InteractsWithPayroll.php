<?php

namespace Tests\Feature\Payroll\Concerns;

use App\Actions\Finance\SeedCompanyFinanceDefaultsAction;
use App\Enums\Payroll\CalculationMethod;
use App\Models\Company;
use App\Models\Finance\Account;
use App\Models\HR\Employee;
use App\Models\Payroll\EmployeeSalary;
use App\Models\Payroll\PayComponent;
use App\Models\Payroll\TaxTable;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Arrange helpers for payroll tests: companies with the standard chart and
 * FY 2026 (clock frozen in Oct 2026), employees with salaries, the reference
 * component set and tax table, and API shortcuts for the run lifecycle.
 */
trait InteractsWithPayroll
{
    public const ALL_PAYROLL = ['payroll.view', 'payroll.manage', 'payroll.process'];

    protected function setUpPayroll(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00'));
    }

    protected function actAs(User $user, Company $company): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAsIn($user, $company);
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array{0: User, 1: Company}
     */
    protected function payrollUser(array $permissions = self::ALL_PAYROLL, ?Company $company = null): array
    {
        [$user, $company] = $this->userWith($permissions, $company);

        if (! Account::forCompany($company->id)->exists()) {
            app(SeedCompanyFinanceDefaultsAction::class)->handle($company);
        }

        return [$user, $company];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function colleague(Company $company, array $permissions = self::ALL_PAYROLL): User
    {
        return User::factory()->inCompany($company, $permissions)->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function employee(Company $company, array $attributes = []): Employee
    {
        return Employee::factory()->create(array_merge(['company_id' => $company->id, 'hired_at' => '2025-01-01'], $attributes));
    }

    /**
     * @param  list<array{0: PayComponent, 1?: ?string, 2?: ?string}>  $overrides  [component, amount, rate]
     */
    protected function salary(Employee $employee, string $base = '5000', array $overrides = [], string $from = '2026-01-01'): EmployeeSalary
    {
        $salary = EmployeeSalary::factory()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'base_salary' => $base,
            'effective_from' => $from,
        ]);

        foreach ($overrides as $row) {
            $salary->components()->create([
                'company_id' => $employee->company_id,
                'pay_component_id' => $row[0]->id,
                'amount' => $row[1] ?? null,
                'rate' => $row[2] ?? null,
            ]);
        }

        return $salary;
    }

    /**
     * The reference set: allowance fixed 500 taxable, deduction 10 % of base,
     * employer contribution 5 % of base.
     *
     * @return array{allowance: PayComponent, deduction: PayComponent, employer: PayComponent}
     */
    protected function referenceComponents(Company $company): array
    {
        return [
            'allowance' => PayComponent::factory()->create([
                'company_id' => $company->id, 'code' => 'HOUSING', 'name' => 'Housing allowance',
                'calculation' => CalculationMethod::Fixed, 'default_amount' => '500', 'is_taxable' => true,
            ]),
            'deduction' => PayComponent::factory()->deduction('10')->create(['company_id' => $company->id, 'code' => 'PENSION', 'name' => 'Pension (employee)']),
            'employer' => PayComponent::factory()->employerContribution('5')->create(['company_id' => $company->id, 'code' => 'PENSION_ER', 'name' => 'Pension (employer)']),
        ];
    }

    /** Monthly table: 0–1000 0 %, 1000–5000 10 %, above 5000 20 %. */
    protected function referenceTaxTable(Company $company): TaxTable
    {
        return TaxTable::factory()
            ->withBrackets([['0', '1000', '0'], ['1000', '5000', '10'], ['5000', null, '20']])
            ->create(['company_id' => $company->id, 'name' => 'Reference table', 'effective_from' => '2026-01-01']);
    }

    /**
     * Employee with base 5000 + the reference components, plus the tax table.
     */
    protected function referenceEmployee(Company $company, array $attributes = []): Employee
    {
        $components = $this->referenceComponents($company);
        $this->referenceTaxTable($company);
        $employee = $this->employee($company, $attributes);
        $this->salary($employee, '5000', [[$components['allowance']], [$components['deduction']], [$components['employer']]]);

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    protected function runPayload(string $month = '2026-09', array $extra = []): array
    {
        $start = Carbon::parse($month.'-01');

        return array_merge([
            'name' => $start->format('F Y').' payroll',
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'payment_date' => $start->copy()->endOfMonth()->toDateString(),
        ], $extra);
    }

    protected function createRun(User $user, Company $company, string $month = '2026-09'): int
    {
        return (int) $this->actAs($user, $company)
            ->postJson('/api/v1/payroll-runs', $this->runPayload($month))
            ->assertCreated()
            ->json('data.id');
    }

    protected function calculatedRun(User $user, Company $company, string $month = '2026-09'): int
    {
        $id = $this->createRun($user, $company, $month);
        $this->postJson("/api/v1/payroll-runs/{$id}/calculate")->assertOk();

        return $id;
    }

    /**
     * Create + calculate by $creator, approve + finalize by a colleague.
     */
    protected function finalizedRun(User $creator, Company $company, ?User $approver = null, string $month = '2026-09'): int
    {
        $approver ??= $this->colleague($company);
        $id = $this->calculatedRun($creator, $company, $month);

        $this->actAs($approver, $company)->postJson("/api/v1/payroll-runs/{$id}/approve")->assertOk();
        $this->postJson("/api/v1/payroll-runs/{$id}/finalize")->assertOk();

        return $id;
    }
}
