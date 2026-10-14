<?php

namespace Tests\Feature\Access;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    private function permissionId(string $slug): int
    {
        return (int) Permission::query()->where('slug', $slug)->value('id');
    }

    private function systemRole(Company $company, string $slug, string $name): Role
    {
        return Role::query()->create(['company_id' => $company->id, 'slug' => $slug, 'name' => $name, 'is_system' => true]);
    }

    // -- Guards ---------------------------------------------------------------

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/roles')->assertUnauthorized();
        $this->getJson('/api/v1/permissions')->assertUnauthorized();
    }

    public function test_user_without_roles_view_cannot_list_roles_or_permissions(): void
    {
        [$user, $company] = $this->userWith(['users.view']);

        $this->actingAsIn($user, $company)->getJson('/api/v1/roles')->assertForbidden();
        $this->actingAsIn($user, $company)->getJson('/api/v1/permissions')->assertForbidden();
    }

    public function test_user_without_roles_manage_cannot_create_a_role(): void
    {
        [$viewer, $company] = $this->userWith(['roles.view']);

        $this->actingAsIn($viewer, $company)
            ->postJson('/api/v1/roles', ['name' => 'Sneaky', 'permission_ids' => [$this->permissionId('users.manage')]])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'Sneaky']);
    }

    public function test_returns_404_for_another_companys_role(): void
    {
        [$admin, $companyA] = $this->userWith(['roles.view', 'roles.manage']);
        $roleB = Role::factory()->for(Company::factory()->create())->create(['name' => 'Foreign']);

        $this->actingAsIn($admin, $companyA)->getJson("/api/v1/roles/{$roleB->id}")->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->actingAsIn($admin, $companyA)->putJson("/api/v1/roles/{$roleB->id}", ['name' => 'Hijacked'])->assertNotFound();
        $this->actingAsIn($admin, $companyA)->deleteJson("/api/v1/roles/{$roleB->id}")->assertNotFound();

        $this->assertDatabaseHas('roles', ['id' => $roleB->id, 'name' => 'Foreign']);
        $this->actingAsIn($admin, $companyA)->getJson('/api/v1/roles')->assertOk()->assertJsonMissing(['id' => $roleB->id]);
    }

    // -- Catalogue -------------------------------------------------------------

    public function test_permission_catalogue_is_grouped_by_module_and_excludes_platform(): void
    {
        [$viewer, $company] = $this->userWith(['roles.view']);

        $response = $this->actingAsIn($viewer, $company)->getJson('/api/v1/permissions')->assertOk();

        $modules = collect($response->json('data'))->pluck('module');
        $this->assertContains('access', $modules);
        $this->assertNotContains('platform', $modules);

        $access = collect($response->json('data'))->firstWhere('module', 'access');
        $slugs = collect($access['permissions'])->pluck('slug');
        $this->assertTrue($slugs->contains('roles.manage'));
        $this->assertSame($this->permissionId('roles.manage'), collect($access['permissions'])->firstWhere('slug', 'roles.manage')['id']);
    }

    // -- CRUD ------------------------------------------------------------------

    public function test_lists_roles_with_user_and_permission_counts(): void
    {
        [$admin, $company] = $this->userWith(['roles.view']);
        $role = Role::factory()->for($company)->create(['name' => 'Approver']);
        $role->permissions()->sync([$this->permissionId('invoices.approve'), $this->permissionId('invoices.view')]);
        $member = User::factory()->inCompany($company)->create();
        $member->roles()->attach($role->id, ['company_id' => $company->id]);

        $response = $this->actingAsIn($admin, $company)->getJson('/api/v1/roles?search=Approver')->assertOk()->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.id', $role->id)
            ->assertJsonPath('data.0.users_count', 1)
            ->assertJsonPath('data.0.permissions_count', 2);
    }

    public function test_creating_a_role_requires_name_and_permissions(): void
    {
        [$admin, $company] = $this->userWith(['roles.manage']);

        $this->actingAsIn($admin, $company)->postJson('/api/v1/roles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'permission_ids']);
    }

    public function test_creating_a_role_rejects_platform_permissions(): void
    {
        [$admin, $company] = $this->userWith(['roles.manage']);

        $this->actingAsIn($admin, $company)
            ->postJson('/api/v1/roles', ['name' => 'Oops', 'permission_ids' => [$this->permissionId('platform.companies.manage')]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permission_ids.0']);

        $this->assertDatabaseMissing('roles', ['name' => 'Oops']);
    }

    public function test_creates_a_role_with_permissions_and_audits_the_change(): void
    {
        [$admin, $company] = $this->userWith(['roles.manage']);

        $response = $this->actingAsIn($admin, $company)->postJson('/api/v1/roles', [
            'name' => 'Invoice Approver',
            'description' => 'Approves invoices',
            'permission_ids' => [$this->permissionId('invoices.view'), $this->permissionId('invoices.approve')],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Invoice Approver')
            ->assertJsonPath('data.slug', 'invoice-approver')
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.permissions_count', 2);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('roles', ['id' => $id, 'company_id' => $company->id, 'slug' => 'invoice-approver']);
        $this->assertSame(2, DB::table('permission_role')->where('role_id', $id)->count());

        $audit = AuditLog::query()->where('action', 'roles.permissions_changed')->where('entity_id', $id)->firstOrFail();
        $this->assertEqualsCanonicalizing(['invoices.approve', 'invoices.view'], $audit->context['added']);
        $this->assertSame([], $audit->context['removed']);
        $this->assertSame($company->id, $audit->company_id);
    }

    public function test_updates_a_custom_role_and_audits_added_and_removed_slugs(): void
    {
        [$admin, $company] = $this->userWith(['roles.manage']);
        $role = Role::factory()->for($company)->create(['name' => 'Old']);
        $role->permissions()->sync([$this->permissionId('invoices.view')]);

        $this->actingAsIn($admin, $company)->putJson("/api/v1/roles/{$role->id}", [
            'name' => 'Renamed',
            'permission_ids' => [$this->permissionId('invoices.approve')],
        ])->assertOk()->assertJsonPath('data.name', 'Renamed');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Renamed']);
        $audit = AuditLog::query()->where('action', 'roles.permissions_changed')->where('entity_id', $role->id)->firstOrFail();
        $this->assertSame(['invoices.approve'], $audit->context['added']);
        $this->assertSame(['invoices.view'], $audit->context['removed']);
    }

    public function test_system_role_name_is_read_only_but_permissions_are_editable(): void
    {
        [$admin, $company] = $this->userWith(['roles.manage']);
        $role = $this->systemRole($company, 'accountant', 'Accountant');

        $this->actingAsIn($admin, $company)->putJson("/api/v1/roles/{$role->id}", ['name' => 'Bean Counter'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);

        $this->actingAsIn($admin, $company)->putJson("/api/v1/roles/{$role->id}", [
            'name' => 'Accountant',
            'permission_ids' => [$this->permissionId('accounts.view')],
        ])->assertOk()->assertJsonPath('data.permissions_count', 1);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Accountant']);
    }

    public function test_company_owner_role_is_fully_locked(): void
    {
        [$admin, $company] = $this->userWith(['roles.manage']);
        $owner = $this->systemRole($company, 'company.owner', 'Company Owner');

        $this->actingAsIn($admin, $company)->putJson("/api/v1/roles/{$owner->id}", ['permission_ids' => [$this->permissionId('accounts.view')]])
            ->assertStatus(409)->assertJsonPath('code', 'conflict');

        $this->actingAsIn($admin, $company)->deleteJson("/api/v1/roles/{$owner->id}")->assertStatus(409);
        $this->assertDatabaseHas('roles', ['id' => $owner->id]);
    }

    public function test_deleting_a_role_with_users_returns_409_and_without_users_succeeds(): void
    {
        [$admin, $company] = $this->userWith(['roles.manage']);
        $role = Role::factory()->for($company)->create();
        $member = User::factory()->inCompany($company)->create();
        $member->roles()->attach($role->id, ['company_id' => $company->id]);

        $this->actingAsIn($admin, $company)->deleteJson("/api/v1/roles/{$role->id}")->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);

        $member->roles()->detach($role->id);

        $this->actingAsIn($admin, $company)->deleteJson("/api/v1/roles/{$role->id}")->assertNoContent();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'roles.deleted', 'entity_id' => $role->id]);
    }

    public function test_show_returns_the_role_with_its_permissions(): void
    {
        [$viewer, $company] = $this->userWith(['roles.view']);
        $role = Role::factory()->for($company)->create();
        $role->permissions()->sync([$this->permissionId('leave.approve')]);

        $this->actingAsIn($viewer, $company)->getJson("/api/v1/roles/{$role->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $role->id)
            ->assertJsonPath('data.permissions.0.slug', 'leave.approve')
            ->assertJsonPath('data.users_count', 0);
    }
}
