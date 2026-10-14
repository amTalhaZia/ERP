<?php

namespace Tests\Feature\HR;

use App\Models\Company;
use App\Models\Department;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HR\Concerns\SwitchesUsers;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use SwitchesUsers;

    private const ALL = ['employees.view', 'employees.create', 'employees.update', 'employees.delete'];

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'employment_type' => 'full_time',
            'hired_at' => '2026-10-01',
            'national_id' => 'NID-123456789',
            'bank_account_number' => '000111222333',
            'base_salary' => '7500.50',
        ], $overrides);
    }

    public function test_creates_an_employee_with_a_sequential_code_and_audit_entry(): void
    {
        [$hr, $company] = $this->userWith(self::ALL);
        $department = Department::factory()->for($company)->create();
        $designation = Designation::factory()->for($company)->create();

        $response = $this->switchTo($hr, $company)->postJson('/api/v1/employees', $this->payload([
            'department_id' => $department->id,
            'designation_id' => $designation->id,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.employee_code', 'EMP-0001')
            ->assertJsonPath('data.status', 'probation')
            ->assertJsonPath('data.base_salary', '7500.5000')
            ->assertJsonPath('data.department.name', $department->name)
            ->assertJsonMissingPath('data.national_id')
            ->assertJsonMissingPath('data.sensitive');

        $id = $response->json('data.id');
        $this->assertDatabaseHas('employees', ['id' => $id, 'company_id' => $company->id, 'email' => 'ada@example.test']);
        $this->assertNotSame('NID-123456789', Employee::withoutTenancy()->toBase()->where('id', $id)->value('national_id'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'employees.created', 'entity_id' => $id, 'actor_id' => $hr->id]);

        $this->postJson('/api/v1/employees', $this->payload(['email' => 'second@example.test']))
            ->assertCreated()
            ->assertJsonPath('data.employee_code', 'EMP-0002');
    }

    public function test_empty_payload_returns_422_with_required_fields(): void
    {
        [$hr, $company] = $this->userWith(self::ALL);

        $this->switchTo($hr, $company)->postJson('/api/v1/employees', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'employment_type', 'hired_at']);
    }

    public function test_user_without_employees_create_gets_403_and_nothing_is_saved(): void
    {
        [$viewer, $company] = $this->userWith(['employees.view']);

        $this->switchTo($viewer, $company)->postJson('/api/v1/employees', $this->payload())
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');

        $this->assertDatabaseMissing('employees', ['email' => 'ada@example.test']);
    }

    public function test_cross_tenant_employee_read_update_delete_return_404(): void
    {
        [$hr, $company] = $this->userWith(self::ALL);
        $other = Employee::factory()->for(Company::factory())->create(['first_name' => 'Original']);

        $this->switchTo($hr, $company)->getJson("/api/v1/employees/{$other->id}")->assertNotFound();
        $this->putJson("/api/v1/employees/{$other->id}", ['first_name' => 'Hijacked'])->assertNotFound();
        $this->deleteJson("/api/v1/employees/{$other->id}")->assertNotFound();
        $this->postJson("/api/v1/employees/{$other->id}/terminate", ['terminated_at' => '2026-10-10', 'reason' => 'x'])->assertNotFound();

        $this->assertDatabaseHas('employees', ['id' => $other->id, 'first_name' => 'Original', 'deleted_at' => null]);
        $this->getJson('/api/v1/employees')->assertOk()->assertJsonMissing(['id' => $other->id]);
    }

    public function test_references_to_another_companys_department_or_manager_are_rejected(): void
    {
        [$hr, $company] = $this->userWith(self::ALL);
        $foreignDepartment = Department::factory()->for(Company::factory())->create();
        $foreignManager = Employee::factory()->create();

        $this->switchTo($hr, $company)->postJson('/api/v1/employees', $this->payload([
            'department_id' => $foreignDepartment->id,
            'manager_id' => $foreignManager->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['department_id', 'manager_id']);
    }

    public function test_manager_cannot_be_self_or_a_descendant(): void
    {
        [$hr, $company] = $this->userWith(self::ALL);
        $boss = Employee::factory()->for($company)->create();
        $lead = Employee::factory()->for($company)->create(['manager_id' => $boss->id]);
        $dev = Employee::factory()->for($company)->create(['manager_id' => $lead->id]);

        $this->switchTo($hr, $company)->putJson("/api/v1/employees/{$boss->id}", ['manager_id' => $boss->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['manager_id']);

        $this->putJson("/api/v1/employees/{$boss->id}", ['manager_id' => $dev->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['manager_id']);

        $this->assertDatabaseHas('employees', ['id' => $boss->id, 'manager_id' => null]);
    }

    public function test_show_masks_sensitive_fields_only_for_editors(): void
    {
        [$editor, $company] = $this->userWith(['employees.view', 'employees.update']);
        $viewer = User::factory()->inCompany($company, ['employees.view'])->create();
        $employee = Employee::factory()->for($company)->create(['bank_account_number' => '123456789012']);

        $this->switchTo($editor, $company)->getJson("/api/v1/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.sensitive.bank_account_number', '********9012');

        $this->switchTo($viewer, $company)->getJson("/api/v1/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.sensitive');
    }

    public function test_terminate_sets_status_and_deactivates_the_linked_membership(): void
    {
        [$hr, $company] = $this->userWith(self::ALL);
        $login = User::factory()->inCompany($company)->create();
        $employee = Employee::factory()->for($company)->forUser($login)->create();

        $this->switchTo($hr, $company)->postJson("/api/v1/employees/{$employee->id}/terminate", [
            'terminated_at' => '2026-10-10',
            'reason' => 'Resigned',
        ])->assertOk()->assertJsonPath('data.status', 'terminated');

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'status' => 'terminated', 'termination_reason' => 'Resigned']);
        $this->assertDatabaseHas('company_user', ['company_id' => $company->id, 'user_id' => $login->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employees.terminated', 'entity_id' => $employee->id]);

        $this->postJson("/api/v1/employees/{$employee->id}/terminate", ['terminated_at' => '2026-10-10', 'reason' => 'again'])
            ->assertStatus(409);

        $this->postJson("/api/v1/employees/{$employee->id}/reactivate")->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('company_user', ['company_id' => $company->id, 'user_id' => $login->id, 'is_active' => true]);
    }

    public function test_terminate_requires_employees_update(): void
    {
        [$viewer, $company] = $this->userWith(['employees.view']);
        $employee = Employee::factory()->for($company)->create();

        $this->switchTo($viewer, $company)
            ->postJson("/api/v1/employees/{$employee->id}/terminate", ['terminated_at' => '2026-10-10', 'reason' => 'x'])
            ->assertForbidden();

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'status' => 'active']);
    }

    public function test_directory_and_org_chart_list_active_employees_as_a_tree(): void
    {
        [$viewer, $company] = $this->userWith(['employees.view']);
        $boss = Employee::factory()->for($company)->create();
        $report = Employee::factory()->for($company)->create(['manager_id' => $boss->id]);
        Employee::factory()->for($company)->terminated()->create();

        $this->switchTo($viewer, $company)->getJson('/api/v1/employees/directory')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/employees/org-chart')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $boss->id)
            ->assertJsonPath('data.0.children.0.id', $report->id);
    }

    public function test_photo_upload_stores_on_public_disk(): void
    {
        Storage::fake('public');
        [$hr, $company] = $this->userWith(self::ALL);
        $employee = Employee::factory()->for($company)->create();

        $this->switchTo($hr, $company)
            ->post("/api/v1/employees/{$employee->id}/photo", ['photo' => UploadedFile::fake()->image('me.jpg')], ['Accept' => 'application/json'])
            ->assertOk();

        $path = Employee::withoutTenancy()->find($employee->id)->photo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_one_active_contract_per_employee(): void
    {
        [$hr, $company] = $this->userWith(self::ALL);
        $employee = Employee::factory()->for($company)->create();
        $payload = [
            'employee_id' => $employee->id, 'contract_type' => 'full_time', 'start_date' => '2026-01-01',
            'salary' => '5000', 'currency' => 'USD',
        ];

        $this->switchTo($hr, $company)->postJson('/api/v1/employment-contracts', $payload)
            ->assertCreated()->assertJsonPath('data.salary', '5000.0000');

        $this->postJson('/api/v1/employment-contracts', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $this->assertDatabaseCount('employment_contracts', 1);
    }
}
