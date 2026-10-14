<?php

namespace Tests\Feature\Access;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CompanyUserManagementTest extends TestCase
{
    /**
     * @param  array<int, string>  $permissions
     */
    private function roleWith(Company $company, array $permissions, string $name = 'Custom'): Role
    {
        $role = Role::factory()->for($company)->create(['name' => $name]);
        $role->permissions()->sync(Permission::query()->whereIn('slug', $permissions)->pluck('id')->all());

        return $role;
    }

    // -- Guards ---------------------------------------------------------------

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized()->assertJsonPath('code', 'auth.unauthenticated');
        $this->postJson('/api/v1/users', [])->assertUnauthorized();
    }

    public function test_user_without_users_view_cannot_list_members(): void
    {
        [$user, $company] = $this->userWith(['dashboard.view']);

        $this->actingAsIn($user, $company)->getJson('/api/v1/users')->assertForbidden()->assertJsonPath('code', 'auth.forbidden');
    }

    public function test_user_without_users_manage_cannot_invite_and_nothing_is_created(): void
    {
        [$viewer, $company] = $this->userWith(['users.view']);
        $role = $this->roleWith($company, ['dashboard.view']);

        $this->actingAsIn($viewer, $company)
            ->postJson('/api/v1/users', ['name' => 'New', 'email' => 'new@example.com', 'role_ids' => [$role->id]])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_returns_404_for_a_member_of_another_company(): void
    {
        [$admin, $companyA] = $this->userWith(['users.view', 'users.manage']);
        $companyB = Company::factory()->create();
        $outsider = User::factory()->inCompany($companyB, ['dashboard.view'])->create();

        $this->actingAsIn($admin, $companyA)->getJson("/api/v1/users/{$outsider->id}")->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->actingAsIn($admin, $companyA)->putJson("/api/v1/users/{$outsider->id}", ['is_active' => false])->assertNotFound();
        $this->actingAsIn($admin, $companyA)->deleteJson("/api/v1/users/{$outsider->id}")->assertNotFound();

        $this->assertDatabaseHas('company_user', ['user_id' => $outsider->id, 'company_id' => $companyB->id, 'is_active' => true]);
    }

    // -- Listing ---------------------------------------------------------------

    public function test_lists_only_members_of_the_current_company_with_their_roles(): void
    {
        [$admin, $company] = $this->userWith(['users.view']);
        $role = $this->roleWith($company, ['dashboard.view'], 'Viewer');
        $member = User::factory()->inCompany($company)->create(['name' => 'Zed Member']);
        $member->roles()->attach($role->id, ['company_id' => $company->id]);
        User::factory()->inCompany(Company::factory()->create())->create(); // another company

        $response = $this->actingAsIn($admin, $company)->getJson('/api/v1/users?sort=name');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.total', 2);

        $row = collect($response->json('data'))->firstWhere('id', $member->id);
        $this->assertSame('Zed Member', $row['name']);
        $this->assertSame([['id' => $role->id, 'name' => 'Viewer', 'slug' => $role->slug]], $row['roles']);
        $this->assertTrue($row['is_active']);

        $this->actingAsIn($admin, $company)->getJson('/api/v1/users?search=zed')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $member->id);
        $this->actingAsIn($admin, $company)->getJson("/api/v1/users?filter[role_id]={$role->id}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $member->id);
    }

    // -- Invite ----------------------------------------------------------------

    public function test_invite_requires_name_email_and_roles(): void
    {
        [$admin, $company] = $this->userWith(['users.manage']);

        $this->actingAsIn($admin, $company)
            ->postJson('/api/v1/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'role_ids']);
    }

    public function test_invite_rejects_a_role_from_another_company(): void
    {
        [$admin, $company] = $this->userWith(['users.manage']);
        $foreignRole = Role::factory()->create(); // belongs to a fresh company

        $this->actingAsIn($admin, $company)
            ->postJson('/api/v1/users', ['name' => 'X', 'email' => 'x@example.com', 'role_ids' => [$foreignRole->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_ids.0']);

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_inviting_a_new_email_creates_an_unverified_account_sends_reset_link_and_audits(): void
    {
        Notification::fake();
        [$admin, $company] = $this->userWith(['users.manage']);
        $role = $this->roleWith($company, ['dashboard.view'], 'Viewer');
        $branch = Branch::factory()->for($company)->create();

        $response = $this->actingAsIn($admin, $company)->postJson('/api/v1/users', [
            'name' => 'Nadia New',
            'email' => 'Nadia@Example.com',
            'role_ids' => [$role->id],
            'branch_ids' => [$branch->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'nadia@example.com')
            ->assertJsonPath('data.roles.0.id', $role->id)
            ->assertJsonPath('data.branch_ids', [$branch->id])
            ->assertJsonMissingPath('data.password');

        $user = User::query()->where('email', 'nadia@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('company_user', ['company_id' => $company->id, 'user_id' => $user->id, 'is_active' => true, 'is_default' => true]);
        $this->assertDatabaseHas('role_user', ['role_id' => $role->id, 'user_id' => $user->id, 'company_id' => $company->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'users.invited', 'entity_id' => $user->id, 'actor_id' => $admin->id, 'company_id' => $company->id]);

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertSentTo($user, UserInvitedNotification::class);
    }

    public function test_inviting_an_existing_account_adds_a_membership_without_a_reset_link(): void
    {
        Notification::fake();
        [$admin, $company] = $this->userWith(['users.manage']);
        $role = $this->roleWith($company, ['dashboard.view']);
        $existing = User::factory()->inCompany(Company::factory()->create())->create(['email' => 'existing@example.com']);

        $this->actingAsIn($admin, $company)
            ->postJson('/api/v1/users', ['name' => 'Ignored', 'email' => 'existing@example.com', 'role_ids' => [$role->id]])
            ->assertCreated()
            ->assertJsonPath('data.id', $existing->id)
            ->assertJsonPath('data.is_default', false);

        $this->assertSame(1, User::query()->where('email', 'existing@example.com')->count());
        $this->assertDatabaseHas('company_user', ['company_id' => $company->id, 'user_id' => $existing->id]);

        Notification::assertNotSentTo($existing, ResetPassword::class);
        Notification::assertSentTo($existing, UserInvitedNotification::class);
    }

    public function test_inviting_an_existing_member_returns_409(): void
    {
        Notification::fake();
        [$admin, $company] = $this->userWith(['users.manage']);
        $role = $this->roleWith($company, ['dashboard.view']);
        $member = User::factory()->inCompany($company)->create();

        $this->actingAsIn($admin, $company)
            ->postJson('/api/v1/users', ['name' => $member->name, 'email' => $member->email, 'role_ids' => [$role->id]])
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict');

        Notification::assertNothingSent();
    }

    public function test_invite_stores_a_database_notification_for_the_invited_user(): void
    {
        [$admin, $company] = $this->userWith(['users.manage']);
        $role = $this->roleWith($company, ['dashboard.view']);

        $this->actingAsIn($admin, $company)
            ->postJson('/api/v1/users', ['name' => 'Dee', 'email' => 'dee@example.com', 'role_ids' => [$role->id]])
            ->assertCreated();

        $user = User::query()->where('email', 'dee@example.com')->firstOrFail();
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id, 'type' => UserInvitedNotification::class]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'dee@example.com']);
    }

    // -- Update / remove -------------------------------------------------------

    public function test_update_requires_at_least_one_field(): void
    {
        [$admin, $company] = $this->userWith(['users.manage']);
        $member = User::factory()->inCompany($company)->create();

        $this->actingAsIn($admin, $company)->putJson("/api/v1/users/{$member->id}", [])->assertUnprocessable()->assertJsonValidationErrors(['role_ids']);
    }

    public function test_updates_roles_branches_and_activation_of_a_membership_and_audits_role_names(): void
    {
        [$admin, $company] = $this->userWith(['users.manage']);
        $viewer = $this->roleWith($company, ['dashboard.view'], 'Viewer');
        $editor = $this->roleWith($company, ['branches.manage'], 'Editor');
        $branch = Branch::factory()->for($company)->create();
        $member = User::factory()->inCompany($company)->create();
        $member->roles()->attach($viewer->id, ['company_id' => $company->id]);

        $this->actingAsIn($admin, $company)
            ->putJson("/api/v1/users/{$member->id}", ['role_ids' => [$editor->id], 'branch_ids' => [$branch->id], 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.roles.0.name', 'Editor')
            ->assertJsonPath('data.branch_ids', [$branch->id])
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('company_user', ['user_id' => $member->id, 'company_id' => $company->id, 'is_active' => false]);
        $this->assertDatabaseHas('role_user', ['user_id' => $member->id, 'role_id' => $editor->id, 'company_id' => $company->id]);
        $this->assertDatabaseMissing('role_user', ['user_id' => $member->id, 'role_id' => $viewer->id]);
        $this->assertTrue($member->fresh()->is_active, 'user-level flag must be untouched');

        $audit = AuditLog::query()->where('action', 'users.membership_updated')->where('entity_id', $member->id)->firstOrFail();
        $this->assertSame(['Viewer'], $audit->old_values['roles']);
        $this->assertSame(['Editor'], $audit->new_values['roles']);
    }

    public function test_user_cannot_deactivate_themselves_or_drop_their_last_users_manage_role(): void
    {
        [$admin, $company] = $this->userWith(['users.manage']);
        $viewer = $this->roleWith($company, ['dashboard.view'], 'Viewer');

        $this->actingAsIn($admin, $company)->putJson("/api/v1/users/{$admin->id}", ['is_active' => false])
            ->assertUnprocessable()->assertJsonValidationErrors(['is_active']);

        $this->actingAsIn($admin, $company)->putJson("/api/v1/users/{$admin->id}", ['role_ids' => [$viewer->id]])
            ->assertUnprocessable()->assertJsonValidationErrors(['role_ids']);

        $this->assertDatabaseHas('company_user', ['user_id' => $admin->id, 'company_id' => $company->id, 'is_active' => true]);
        $this->assertDatabaseMissing('role_user', ['user_id' => $admin->id, 'role_id' => $viewer->id]);
    }

    public function test_removing_a_member_deletes_membership_and_roles_but_keeps_the_user(): void
    {
        [$admin, $company] = $this->userWith(['users.manage']);
        $member = User::factory()->inCompany($company, ['dashboard.view'])->create();

        $this->actingAsIn($admin, $company)->deleteJson("/api/v1/users/{$member->id}")->assertNoContent();

        $this->assertDatabaseMissing('company_user', ['user_id' => $member->id, 'company_id' => $company->id]);
        $this->assertDatabaseMissing('role_user', ['user_id' => $member->id, 'company_id' => $company->id]);
        $this->assertDatabaseHas('users', ['id' => $member->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'users.membership_removed', 'entity_id' => $member->id, 'company_id' => $company->id]);
    }

    public function test_user_cannot_remove_their_own_membership(): void
    {
        [$admin, $company] = $this->userWith(['users.manage']);

        $this->actingAsIn($admin, $company)->deleteJson("/api/v1/users/{$admin->id}")->assertForbidden();
        $this->assertDatabaseHas('company_user', ['user_id' => $admin->id, 'company_id' => $company->id]);
    }

    public function test_resend_invite_sends_a_fresh_reset_link(): void
    {
        Notification::fake();
        [$admin, $company] = $this->userWith(['users.manage']);
        $member = User::factory()->unverified()->inCompany($company)->create();

        $this->actingAsIn($admin, $company)->postJson("/api/v1/users/{$member->id}/resend-invite")->assertOk();

        Notification::assertSentTo($member, ResetPassword::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'users.invite_resent', 'entity_id' => $member->id]);
    }

    public function test_show_returns_the_member_with_membership_details(): void
    {
        [$admin, $company] = $this->userWith(['users.view']);
        $member = User::factory()->inCompany($company, [], false, [1, 2])->create();

        $this->actingAsIn($admin, $company)->getJson("/api/v1/users/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $member->id)
            ->assertJsonPath('data.membership_id', CompanyUser::query()->where('user_id', $member->id)->value('id'))
            ->assertJsonPath('data.branch_ids', [1, 2])
            ->assertJsonPath('data.roles', []);
    }
}
