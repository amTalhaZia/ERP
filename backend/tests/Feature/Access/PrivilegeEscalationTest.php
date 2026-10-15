<?php

namespace Tests\Feature\Access;

use App\Actions\Company\CreateCompanyAction;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Company Administrators (everything except payroll.process) must not be able
 * to reach the Company Owner role or permissions they do not hold — neither
 * for others nor for themselves.
 */
class PrivilegeEscalationTest extends TestCase
{
    private Company $company;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = User::factory()->create();
        $this->company = app(CreateCompanyAction::class)->handle(['name' => 'Escalation Co', 'currency' => 'USD'], $this->owner);

        $this->admin = User::factory()->inCompany($this->company)->create();
        $this->admin->roles()->attach($this->role('company.admin')->id, ['company_id' => $this->company->id]);
    }

    private function role(string $slug): Role
    {
        return Role::forTenant($this->company->id)->where('slug', $slug)->firstOrFail();
    }

    private function permissionId(string $slug): int
    {
        return (int) Permission::query()->where('slug', $slug)->value('id');
    }

    /**
     * @return array<int, string>
     */
    private function slugsOf(Role $role): array
    {
        return $role->permissions()->orderBy('slug')->pluck('slug')->all();
    }

    // -- Owner role -------------------------------------------------------------

    public function test_admin_cannot_invite_someone_as_company_owner(): void
    {
        $this->actingAsIn($this->admin, $this->company)
            ->postJson('/api/v1/users', ['name' => 'Mallory', 'email' => 'mallory@example.com', 'role_ids' => [$this->role('company.owner')->id]])
            ->assertForbidden()
            ->assertJsonPath('code', 'access.escalation_denied');

        $this->assertDatabaseMissing('users', ['email' => 'mallory@example.com']);
    }

    public function test_admin_cannot_assign_the_owner_role_via_membership_update(): void
    {
        $member = User::factory()->inCompany($this->company)->create();
        $member->roles()->attach($this->role('employee')->id, ['company_id' => $this->company->id]);

        $this->actingAsIn($this->admin, $this->company)
            ->putJson("/api/v1/users/{$member->id}", ['role_ids' => [$this->role('company.owner')->id]])
            ->assertForbidden()
            ->assertJsonPath('code', 'access.escalation_denied');

        // Not even for themselves.
        $this->actingAsIn($this->admin, $this->company)
            ->putJson("/api/v1/users/{$this->admin->id}", ['role_ids' => [$this->role('company.admin')->id, $this->role('company.owner')->id]])
            ->assertForbidden()
            ->assertJsonPath('code', 'access.escalation_denied');

        $this->assertDatabaseMissing('role_user', ['role_id' => $this->role('company.owner')->id, 'user_id' => $member->id]);
        $this->assertDatabaseMissing('role_user', ['role_id' => $this->role('company.owner')->id, 'user_id' => $this->admin->id]);
    }

    public function test_admin_cannot_deactivate_the_owners_membership(): void
    {
        $this->actingAsIn($this->admin, $this->company)
            ->putJson("/api/v1/users/{$this->owner->id}", ['is_active' => false])
            ->assertForbidden()
            ->assertJsonPath('code', 'access.escalation_denied');

        $this->assertDatabaseHas('company_user', ['company_id' => $this->company->id, 'user_id' => $this->owner->id, 'is_active' => true]);
    }

    public function test_admin_cannot_remove_the_owner_from_the_company(): void
    {
        $this->actingAsIn($this->admin, $this->company)
            ->deleteJson("/api/v1/users/{$this->owner->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'access.escalation_denied');

        $this->assertDatabaseHas('company_user', ['company_id' => $this->company->id, 'user_id' => $this->owner->id]);
    }

    public function test_admin_can_still_deactivate_a_regular_member(): void
    {
        $member = User::factory()->inCompany($this->company)->create();

        $this->actingAsIn($this->admin, $this->company)
            ->putJson("/api/v1/users/{$member->id}", ['is_active' => false])
            ->assertOk();

        $this->assertDatabaseHas('company_user', ['company_id' => $this->company->id, 'user_id' => $member->id, 'is_active' => false]);
    }

    public function test_admin_cannot_remove_the_owner_role_from_the_owner(): void
    {
        $this->actingAsIn($this->admin, $this->company)
            ->putJson("/api/v1/users/{$this->owner->id}", ['role_ids' => [$this->role('employee')->id]])
            ->assertForbidden()
            ->assertJsonPath('code', 'access.escalation_denied');

        $this->assertDatabaseHas('role_user', ['role_id' => $this->role('company.owner')->id, 'user_id' => $this->owner->id]);
    }

    public function test_admin_cannot_assign_a_role_with_permissions_they_lack(): void
    {
        $member = User::factory()->inCompany($this->company)->create();

        $this->actingAsIn($this->admin, $this->company)
            ->putJson("/api/v1/users/{$member->id}", ['role_ids' => [$this->role('hr.manager')->id]]) // includes payroll.process
            ->assertForbidden()
            ->assertJsonPath('code', 'access.escalation_denied');

        $this->assertDatabaseMissing('role_user', ['role_id' => $this->role('hr.manager')->id, 'user_id' => $member->id]);
    }

    // -- Role permissions ---------------------------------------------------------

    public function test_admin_cannot_add_payroll_process_to_their_own_role(): void
    {
        $adminRole = $this->role('company.admin');
        $before = $this->slugsOf($adminRole);
        $ids = $adminRole->permissions()->pluck('permissions.id')->push($this->permissionId('payroll.process'))->all();

        $this->actingAsIn($this->admin, $this->company)
            ->putJson("/api/v1/roles/{$adminRole->id}", ['permission_ids' => $ids])
            ->assertForbidden()
            ->assertJsonPath('code', 'access.escalation_denied');

        $this->assertSame($before, $this->slugsOf($adminRole->refresh()));
        $this->assertNotContains('payroll.process', $this->slugsOf($adminRole));
    }

    public function test_admin_cannot_create_a_custom_role_containing_a_permission_they_lack(): void
    {
        $this->actingAsIn($this->admin, $this->company)
            ->postJson('/api/v1/roles', ['name' => 'Payroll Runner', 'permission_ids' => [$this->permissionId('payroll.view'), $this->permissionId('payroll.process')]])
            ->assertForbidden()
            ->assertJsonPath('code', 'access.escalation_denied');

        $this->assertDatabaseMissing('roles', ['company_id' => $this->company->id, 'name' => 'Payroll Runner']);
    }

    public function test_admin_cannot_add_a_permission_they_lack_to_another_role(): void
    {
        $role = $this->role('employee');
        $ids = $role->permissions()->pluck('permissions.id')->push($this->permissionId('payroll.process'))->all();

        $this->actingAsIn($this->admin, $this->company)
            ->putJson("/api/v1/roles/{$role->id}", ['permission_ids' => $ids])
            ->assertForbidden()
            ->assertJsonPath('code', 'access.escalation_denied');

        $this->assertNotContains('payroll.process', $this->slugsOf($role->refresh()));
    }

    public function test_admin_can_create_and_assign_a_role_within_their_own_permissions(): void
    {
        $response = $this->actingAsIn($this->admin, $this->company)
            ->postJson('/api/v1/roles', ['name' => 'Payroll Viewer', 'permission_ids' => [$this->permissionId('payroll.view'), $this->permissionId('employees.view')]])
            ->assertCreated();

        $roleId = (int) $response->json('data.id');
        $member = User::factory()->inCompany($this->company)->create();

        $this->actingAsIn($this->admin, $this->company)
            ->putJson("/api/v1/users/{$member->id}", ['role_ids' => [$roleId]])
            ->assertOk();

        $this->actingAsIn($this->admin, $this->company)
            ->postJson('/api/v1/users', ['name' => 'Newbie', 'email' => 'newbie@example.com', 'role_ids' => [$roleId]])
            ->assertCreated();

        $this->assertDatabaseHas('role_user', ['role_id' => $roleId, 'user_id' => $member->id, 'company_id' => $this->company->id]);
        $this->assertDatabaseHas('users', ['email' => 'newbie@example.com']);
    }

    // -- Owner may do all of it -----------------------------------------------------

    public function test_owner_can_assign_owner_role_and_grant_any_permission(): void
    {
        $this->actingAsIn($this->owner, $this->company)
            ->postJson('/api/v1/users', ['name' => 'Co Owner', 'email' => 'coowner@example.com', 'role_ids' => [$this->role('company.owner')->id]])
            ->assertCreated();

        $member = User::factory()->inCompany($this->company)->create();
        $this->actingAsIn($this->owner, $this->company)
            ->putJson("/api/v1/users/{$member->id}", ['role_ids' => [$this->role('company.owner')->id, $this->role('hr.manager')->id]])
            ->assertOk();

        $adminRole = $this->role('company.admin');
        $ids = $adminRole->permissions()->pluck('permissions.id')->push($this->permissionId('payroll.process'))->all();
        $this->actingAsIn($this->owner, $this->company)
            ->putJson("/api/v1/roles/{$adminRole->id}", ['permission_ids' => $ids])
            ->assertOk();
        $this->assertContains('payroll.process', $this->slugsOf($adminRole->refresh()));

        $this->actingAsIn($this->owner, $this->company)
            ->postJson('/api/v1/roles', ['name' => 'Payroll Runner', 'permission_ids' => [$this->permissionId('payroll.process')]])
            ->assertCreated();

        $this->assertDatabaseHas('role_user', ['role_id' => $this->role('company.owner')->id, 'user_id' => $member->id]);
    }

    public function test_owner_role_stays_locked_even_for_owners(): void
    {
        $this->actingAsIn($this->owner, $this->company)
            ->putJson("/api/v1/roles/{$this->role('company.owner')->id}", ['permission_ids' => [$this->permissionId('dashboard.view')]])
            ->assertStatus(409);
    }
}
