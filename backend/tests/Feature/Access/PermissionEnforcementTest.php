<?php

namespace Tests\Feature\Access;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class PermissionEnforcementTest extends TestCase
{
    public function test_user_without_branches_manage_cannot_create_a_branch(): void
    {
        [$viewer, $company] = $this->userWith(['branches.view']);

        $this->actingAsIn($viewer, $company)
            ->postJson('/api/v1/branches', ['name' => 'North', 'code' => 'NTH'])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');

        $this->assertDatabaseMissing('branches', ['code' => 'NTH']);
    }

    public function test_user_without_branches_view_cannot_list_branches(): void
    {
        [$user, $company] = $this->userWith(['employees.view']);

        $this->actingAsIn($user, $company)->getJson('/api/v1/branches')->assertForbidden();
    }

    public function test_user_with_branches_manage_can_create_update_and_delete_a_branch(): void
    {
        [$manager, $company] = $this->userWith(['branches.view', 'branches.manage']);

        $created = $this->actingAsIn($manager, $company)
            ->postJson('/api/v1/branches', ['name' => 'North', 'code' => 'NTH', 'city' => 'Leeds'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'NTH');

        $id = $created->json('data.id');

        $this->putJson("/api/v1/branches/{$id}", ['name' => 'North East'])
            ->assertOk()
            ->assertJsonPath('data.name', 'North East');

        $this->deleteJson("/api/v1/branches/{$id}")->assertNoContent();
        $this->assertSoftDeleted('branches', ['id' => $id]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'branches.created', 'entity_id' => $id, 'actor_id' => $manager->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'branches.updated', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'branches.archived', 'entity_id' => $id]);
    }

    public function test_head_office_branch_cannot_be_deleted(): void
    {
        [$manager, $company] = $this->userWith(['branches.view', 'branches.manage']);
        $ho = Branch::factory()->for($company)->headOffice()->create();

        $this->actingAsIn($manager, $company)->deleteJson("/api/v1/branches/{$ho->id}")->assertForbidden();
        $this->assertDatabaseHas('branches', ['id' => $ho->id, 'deleted_at' => null]);
    }

    public function test_permissions_are_scoped_per_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $user = User::factory()->inCompany($companyA, ['branches.view', 'branches.manage'])->create();
        $this->grantInCompany($user, $companyB, ['branches.view']);

        $this->actingAsIn($user, $companyA)
            ->postJson('/api/v1/branches', ['name' => 'X', 'code' => 'X1'])
            ->assertCreated();

        $this->actingAsIn($user->fresh(), $companyB)
            ->postJson('/api/v1/branches', ['name' => 'Y', 'code' => 'Y1'])
            ->assertForbidden();

        $this->assertDatabaseHas('branches', ['code' => 'X1', 'company_id' => $companyA->id]);
        $this->assertDatabaseMissing('branches', ['code' => 'Y1']);
    }

    public function test_duplicate_branch_code_within_a_company_is_rejected_but_allowed_across_companies(): void
    {
        [$manager, $company] = $this->userWith(['branches.view', 'branches.manage']);
        Branch::factory()->for($company)->create(['code' => 'DUP']);
        Branch::factory()->create(['code' => 'DUP']); // another company — fine

        $this->actingAsIn($manager, $company)
            ->postJson('/api/v1/branches', ['name' => 'Dup', 'code' => 'DUP'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_sort_parameter_outside_allow_list_falls_back_to_default_instead_of_erroring(): void
    {
        [$viewer, $company] = $this->userWith(['branches.view']);
        Branch::factory()->for($company)->count(2)->create();

        $this->actingAsIn($viewer, $company)
            ->getJson('/api/v1/branches?sort=password;DROP TABLE users')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
