<?php

namespace Tests\Feature\Payroll;

use App\Models\AuditLog;
use App\Models\Payroll\EmployeeSalary;
use App\Models\Payroll\PayComponent;
use App\Models\Payroll\PayrollRun;
use App\Models\Payroll\SalaryStructure;
use App\Models\Payroll\TaxTable;
use Tests\Feature\Payroll\Concerns\InteractsWithPayroll;
use Tests\TestCase;

class PayrollSetupTest extends TestCase
{
    use InteractsWithPayroll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPayroll();
    }

    public function test_manager_creates_updates_and_deletes_a_pay_component(): void
    {
        [$user, $company] = $this->payrollUser(['payroll.manage']);

        $id = $this->actAs($user, $company)->postJson('/api/v1/pay-components', [
            'code' => 'transport', 'name' => 'Transport allowance', 'kind' => 'earning', 'calculation' => 'fixed',
            'default_amount' => '150.50', 'is_taxable' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'TRANSPORT')
            ->assertJsonPath('data.default_amount', '150.5000')
            ->assertJsonPath('data.effective_gl_account_code', '6000')
            ->json('data.id');

        $this->putJson("/api/v1/pay-components/{$id}", ['name' => 'Commuting allowance', 'gl_account_code' => '6000'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Commuting allowance');

        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'pay.components.created']);

        $this->deleteJson("/api/v1/pay-components/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('pay_components', ['id' => $id]);
    }

    public function test_pay_component_validation(): void
    {
        [$user, $company] = $this->payrollUser(['payroll.manage']);
        PayComponent::factory()->create(['company_id' => $company->id, 'code' => 'BONUS']);

        $this->actAs($user, $company)->postJson('/api/v1/pay-components', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'kind', 'calculation']);

        $this->postJson('/api/v1/pay-components', [
            'code' => 'bonus', 'name' => 'Dup', 'kind' => 'earning', 'calculation' => 'percent_of_gross', 'gl_account_code' => '9999',
        ])->assertUnprocessable()->assertJsonValidationErrors(['code', 'calculation', 'gl_account_code']);

        $this->postJson('/api/v1/pay-components', ['code' => 'TAX', 'name' => 'Reserved', 'kind' => 'deduction', 'calculation' => 'fixed'])
            ->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_component_in_use_cannot_be_deleted(): void
    {
        [$user, $company] = $this->payrollUser(['payroll.manage']);
        $component = PayComponent::factory()->create(['company_id' => $company->id]);
        $this->salary($this->employee($company), '1000', [[$component]]);

        $this->actAs($user, $company)->deleteJson("/api/v1/pay-components/{$component->id}")
            ->assertStatus(409)->assertJsonPath('code', 'payroll.component_in_use');
    }

    public function test_reading_setup_requires_payroll_permission_and_writing_requires_manage(): void
    {
        [$viewer, $company] = $this->payrollUser(['payroll.view']);
        [$nobody] = $this->payrollUser(['employees.view'], $company);

        $this->actAs($nobody, $company)->getJson('/api/v1/pay-components')->assertForbidden();
        $this->getJson('/api/v1/tax-tables')->assertForbidden();
        $this->getJson('/api/v1/salary-structures')->assertForbidden();

        $this->actAs($viewer, $company)->getJson('/api/v1/pay-components')->assertOk();
        $this->postJson('/api/v1/pay-components', ['code' => 'X', 'name' => 'X', 'kind' => 'earning', 'calculation' => 'fixed'])->assertForbidden();
        $this->assertDatabaseCount('pay_components', 0);
    }

    public function test_tax_table_brackets_are_replaced_on_save(): void
    {
        [$user, $company] = $this->payrollUser(['payroll.manage']);

        $id = $this->actAs($user, $company)->postJson('/api/v1/tax-tables', [
            'name' => 'Configured table', 'effective_from' => '2026-01-01', 'period' => 'monthly',
            'brackets' => [
                ['from_amount' => '1000', 'to_amount' => null, 'rate' => '15'],
                ['from_amount' => '0', 'to_amount' => '1000', 'rate' => '0'],
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.brackets')
            ->assertJsonPath('data.brackets.0.from_amount', '0.0000')
            ->assertJsonPath('data.brackets.1.rate', '15.0000')
            ->json('data.id');

        $this->putJson("/api/v1/tax-tables/{$id}", [
            'brackets' => [['from_amount' => '0', 'to_amount' => null, 'rate' => '12.5', 'fixed_amount' => '10']],
        ])
            ->assertOk()
            ->assertJsonCount(1, 'data.brackets')
            ->assertJsonPath('data.brackets.0.rate', '12.5000')
            ->assertJsonPath('data.brackets.0.fixed_amount', '10.0000');

        $this->assertSame(1, TaxTable::query()->findOrFail($id)->brackets()->count());
    }

    public function test_tax_table_validation_rejects_empty_and_overlapping_brackets(): void
    {
        [$user, $company] = $this->payrollUser(['payroll.manage']);

        $this->actAs($user, $company)->postJson('/api/v1/tax-tables', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'effective_from', 'period', 'brackets']);

        $this->postJson('/api/v1/tax-tables', [
            'name' => 'Bad', 'effective_from' => '2026-01-01', 'period' => 'annual',
            'brackets' => [['from_amount' => '0', 'to_amount' => '2000', 'rate' => '0'], ['from_amount' => '1000', 'rate' => '10']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['brackets']);
    }

    public function test_salary_structure_with_components(): void
    {
        [$user, $company] = $this->payrollUser(['payroll.manage']);
        $components = $this->referenceComponents($company);

        $id = $this->actAs($user, $company)->postJson('/api/v1/salary-structures', [
            'name' => 'Engineers', 'currency' => 'usd',
            'components' => [
                ['pay_component_id' => $components['allowance']->id, 'amount' => '750'],
                ['pay_component_id' => $components['deduction']->id],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonCount(2, 'data.components')
            ->assertJsonPath('data.components.0.code', 'HOUSING')
            ->assertJsonPath('data.components.0.amount', '750.0000')
            ->json('data.id');

        $this->putJson("/api/v1/salary-structures/{$id}", ['components' => [['pay_component_id' => $components['employer']->id, 'rate' => '7']]])
            ->assertOk()
            ->assertJsonCount(1, 'data.components')
            ->assertJsonPath('data.components.0.rate', '7.0000');

        $this->postJson('/api/v1/salary-structures', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'currency']);
    }

    public function test_structure_components_and_overrides_drive_calculation(): void
    {
        [$user, $company] = $this->payrollUser();
        $components = $this->referenceComponents($company);
        $structure = SalaryStructure::factory()->create(['company_id' => $company->id]);
        $structure->components()->create(['company_id' => $company->id, 'pay_component_id' => $components['allowance']->id, 'amount' => '800']);
        $structure->components()->create(['company_id' => $company->id, 'pay_component_id' => $components['deduction']->id]);
        $employee = $this->employee($company);
        $salary = $this->salary($employee, '4000', [[$components['allowance'], '1000']]);
        $salary->update(['salary_structure_id' => $structure->id]);

        // Employee override 1000 beats the structure's 800; deduction 10 % of 4000 = 400; no tax table.
        $id = $this->calculatedRun($user, $company);
        $this->assertSame(1, PayrollRun::query()->count());
        $this->getJson("/api/v1/payroll-runs/{$id}")
            ->assertJsonPath('data.totals.gross', '5000.0000')
            ->assertJsonPath('data.totals.deductions', '400.0000')
            ->assertJsonPath('data.totals.net', '4600.0000');
    }

    public function test_employee_salary_history(): void
    {
        [$user, $company] = $this->payrollUser(['payroll.view', 'payroll.manage']);
        $employee = $this->employee($company);
        $this->salary($employee, '4000', [], '2025-01-01');

        $this->actAs($user, $company)->postJson("/api/v1/employees/{$employee->id}/salaries", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['base_salary', 'currency', 'effective_from']);

        $this->postJson("/api/v1/employees/{$employee->id}/salaries", [
            'base_salary' => '4500.00', 'currency' => 'USD', 'effective_from' => '2026-04-01', 'notes' => 'Annual review',
        ])
            ->assertCreated()
            ->assertJsonPath('data.base_salary', '4500.0000')
            ->assertJsonPath('data.pay_frequency', 'monthly');

        $this->getJson("/api/v1/employees/{$employee->id}/salaries")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.effective_from', '2026-04-01')
            ->assertJsonPath('data.1.effective_to', '2026-03-31');

        // The salary amount itself is redacted from the audit trail.
        $audit = AuditLog::query()->where('action', 'employee.salaries.created')->latest('id')->firstOrFail();
        $this->assertSame('[redacted]', $audit->new_values['base_salary']);
    }

    public function test_employee_salaries_require_permission_and_tenant(): void
    {
        [$user, $company] = $this->payrollUser(['payroll.view', 'payroll.manage']);
        [$nobody] = $this->payrollUser(['employees.view'], $company);
        [, $other] = $this->payrollUser();
        $foreign = $this->employee($other);
        $own = $this->employee($company);

        $this->actAs($user, $company)->getJson("/api/v1/employees/{$foreign->id}/salaries")->assertNotFound();
        $this->postJson("/api/v1/employees/{$foreign->id}/salaries", ['base_salary' => '1', 'currency' => 'USD', 'effective_from' => '2026-01-01'])
            ->assertNotFound();

        $this->actAs($nobody, $company)->getJson("/api/v1/employees/{$own->id}/salaries")->assertForbidden();
        $this->assertSame(0, EmployeeSalary::withoutTenancy()->count());
    }

    public function test_another_companys_setup_records_are_not_found(): void
    {
        [$user, $company] = $this->payrollUser();
        [, $other] = $this->payrollUser();
        $component = PayComponent::factory()->create(['company_id' => $other->id]);
        $table = TaxTable::factory()->create(['company_id' => $other->id]);
        $structure = SalaryStructure::factory()->create(['company_id' => $other->id]);

        $this->actAs($user, $company)->getJson("/api/v1/pay-components/{$component->id}")->assertNotFound();
        $this->putJson("/api/v1/pay-components/{$component->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/v1/tax-tables/{$table->id}")->assertNotFound();
        $this->getJson("/api/v1/salary-structures/{$structure->id}")->assertNotFound();

        $this->assertDatabaseHas('pay_components', ['id' => $component->id, 'name' => $component->name]);
        $this->assertDatabaseHas('tax_tables', ['id' => $table->id]);
    }
}
