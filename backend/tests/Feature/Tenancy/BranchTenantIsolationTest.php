<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

/**
 * The canonical cross-company test. Every module copies this shape:
 * read / update / delete / list of another company's record must be 404 or absent.
 */
class BranchTenantIsolationTest extends TestCase
{
    private function arrange(): array
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $userA = User::factory()->inCompany($companyA, ['branches.view', 'branches.manage'])->create();
        $branchB = Branch::factory()->for($companyB)->create();

        return [$userA, $companyA, $companyB, $branchB];
    }

    public function test_listing_branches_excludes_other_companies_rows(): void
    {
        [$userA, $companyA, , $branchB] = $this->arrange();
        $branchA = Branch::factory()->for($companyA)->create();

        $response = $this->actingAsIn($userA, $companyA)->getJson('/api/v1/branches');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $branchA->id)
            ->assertJsonMissing(['id' => $branchB->id]);
    }

    public function test_returns_404_when_reading_another_companys_branch(): void
    {
        [$userA, $companyA, , $branchB] = $this->arrange();

        $this->actingAsIn($userA, $companyA)
            ->getJson("/api/v1/branches/{$branchB->id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    public function test_returns_404_and_does_not_change_another_companys_branch_on_update(): void
    {
        [$userA, $companyA, , $branchB] = $this->arrange();

        $this->actingAsIn($userA, $companyA)
            ->putJson("/api/v1/branches/{$branchB->id}", ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('branches', ['id' => $branchB->id, 'name' => $branchB->name]);
    }

    public function test_returns_404_and_keeps_another_companys_branch_on_delete(): void
    {
        [$userA, $companyA, , $branchB] = $this->arrange();

        $this->actingAsIn($userA, $companyA)
            ->deleteJson("/api/v1/branches/{$branchB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('branches', ['id' => $branchB->id, 'deleted_at' => null]);
    }

    public function test_returns_403_when_header_names_a_company_the_user_is_not_a_member_of(): void
    {
        [$userA, , $companyB] = $this->arrange();

        $this->actingAsIn($userA, $companyB)
            ->getJson('/api/v1/branches')
            ->assertForbidden()
            ->assertJsonPath('code', 'tenant.forbidden');
    }

    public function test_created_branch_is_always_stamped_with_the_active_company_even_if_payload_says_otherwise(): void
    {
        [$userA, $companyA, $companyB] = $this->arrange();

        $response = $this->actingAsIn($userA, $companyA)->postJson('/api/v1/branches', [
            'name' => 'Spoofed',
            'code' => 'SPF',
            'company_id' => $companyB->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.company_id', $companyA->id);
        $this->assertDatabaseHas('branches', ['code' => 'SPF', 'company_id' => $companyA->id]);
        $this->assertDatabaseMissing('branches', ['code' => 'SPF', 'company_id' => $companyB->id]);
    }

    public function test_department_cannot_reference_a_branch_from_another_company(): void
    {
        [$userA, $companyA, , $branchB] = $this->arrange();
        $manager = User::factory()->inCompany($companyA, ['departments.manage'])->create();

        $this->actingAsIn($manager, $companyA)
            ->postJson('/api/v1/departments', ['name' => 'Ops', 'code' => 'OPS', 'branch_id' => $branchB->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);

        $this->assertDatabaseMissing('departments', ['code' => 'OPS']);
    }
}
