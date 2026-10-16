<?php

namespace Tests\Feature\Platform;

use App\Models\Company;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlatformCompanyTest extends TestCase
{
    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/platform/companies')->assertUnauthorized();
    }

    public function test_non_platform_user_gets_404_on_every_platform_route(): void
    {
        [$user, $company] = $this->userWith(['users.manage', 'roles.manage', 'audit.view']);

        $this->actingAsIn($user, $company)->getJson('/api/v1/platform/companies')->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->actingAsIn($user, $company)->postJson('/api/v1/platform/companies', ['name' => 'X'])->assertNotFound();
        $this->actingAsIn($user, $company)->getJson('/api/v1/platform/users')->assertNotFound();
        $this->actingAsIn($user, $company)->getJson('/api/v1/platform/system')->assertNotFound();
        $this->actingAsIn($user, $company)->getJson('/api/v1/platform/audit-logs')->assertNotFound();

        $this->assertDatabaseMissing('companies', ['name' => 'X']);
    }

    public function test_deactivated_platform_admin_gets_404(): void
    {
        $admin = User::factory()->platformAdmin()->inactive()->create();

        // `active` middleware signs out deactivated accounts first (401).
        $this->actingAs($admin, 'web')->getJson('/api/v1/platform/companies')->assertStatus(401);
    }

    public function test_platform_admin_lists_all_companies_with_counts_search_and_filters(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $active = Company::factory()->create(['name' => 'Acme Active']);
        $inactive = Company::factory()->inactive()->create(['name' => 'Beta Inactive']);
        User::factory()->inCompany($active)->create();

        $response = $this->actingAs($admin, 'web')->getJson('/api/v1/platform/companies?sort=name')->assertOk();
        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.users_count', 1)
            ->assertJsonPath('data.0.branches_count', 0);

        $this->actingAs($admin, 'web')->getJson('/api/v1/platform/companies?filter[is_active]=false')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $inactive->id);
        $this->actingAs($admin, 'web')->getJson('/api/v1/platform/companies?search=acme')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_creating_a_company_requires_name_and_owner(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'web')->postJson('/api/v1/platform/companies', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'owner']);

        $this->actingAs($admin, 'web')->postJson('/api/v1/platform/companies', ['name' => 'X', 'owner' => ['name' => 'O']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['owner.email']);
    }

    public function test_creates_a_company_with_head_office_roles_and_a_new_owner_who_gets_a_reset_link(): void
    {
        Notification::fake();
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin, 'web')->postJson('/api/v1/platform/companies', [
            'name' => 'Globex Corporation',
            'currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
            'country' => 'DE',
            'owner' => ['name' => 'Hank Scorpio', 'email' => 'Hank@Globex.example'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Globex Corporation')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.users_count', 1)
            ->assertJsonPath('data.branches_count', 1);

        $company = Company::query()->where('name', 'Globex Corporation')->firstOrFail();
        $owner = User::query()->where('email', 'hank@globex.example')->firstOrFail();

        $this->assertNull($owner->email_verified_at);
        $this->assertDatabaseHas('branches', ['company_id' => $company->id, 'is_head_office' => true]);
        $this->assertDatabaseHas('roles', ['company_id' => $company->id, 'slug' => 'company.owner', 'is_system' => true]);
        $this->assertDatabaseHas('company_user', ['company_id' => $company->id, 'user_id' => $owner->id, 'is_active' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.company_created', 'entity_id' => $company->id, 'company_id' => $company->id, 'actor_id' => $admin->id]);
        $this->assertTrue($owner->hasPermission('users.manage', $company->id));

        Notification::assertSentTo($owner, ResetPassword::class);
        Notification::assertSentTo($owner, UserInvitedNotification::class);
    }

    public function test_creating_a_company_for_an_existing_owner_reuses_the_account(): void
    {
        Notification::fake();
        $admin = User::factory()->platformAdmin()->create();
        $existing = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($admin, 'web')->postJson('/api/v1/platform/companies', [
            'name' => 'Second Co',
            'owner' => ['name' => 'Whatever', 'email' => 'owner@example.com'],
        ])->assertCreated();

        $this->assertSame(1, User::query()->where('email', 'owner@example.com')->count());
        $company = Company::query()->where('name', 'Second Co')->firstOrFail();
        $this->assertDatabaseHas('company_user', ['company_id' => $company->id, 'user_id' => $existing->id]);
        Notification::assertNotSentTo($existing, ResetPassword::class);
        Notification::assertSentTo($existing, UserInvitedNotification::class);
    }

    public function test_shows_updates_activates_and_deactivates_a_company_with_audit_rows(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $company = Company::factory()->create(['name' => 'Before']);

        $this->actingAs($admin, 'web')->getJson("/api/v1/platform/companies/{$company->id}")->assertOk()->assertJsonPath('data.name', 'Before');

        $this->actingAs($admin, 'web')->putJson("/api/v1/platform/companies/{$company->id}", ['name' => 'After', 'is_demo' => true])
            ->assertOk()->assertJsonPath('data.name', 'After')->assertJsonPath('data.is_demo', true);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'After', 'is_demo' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.company_updated', 'entity_id' => $company->id, 'actor_id' => $admin->id]);

        $this->actingAs($admin, 'web')->postJson("/api/v1/platform/companies/{$company->id}/deactivate")->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.company_deactivated', 'entity_id' => $company->id]);

        $this->actingAs($admin, 'web')->postJson("/api/v1/platform/companies/{$company->id}/activate")->assertOk()->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.company_activated', 'entity_id' => $company->id]);
    }

    public function test_update_with_invalid_payload_returns_422(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $company = Company::factory()->create();

        $this->actingAs($admin, 'web')->putJson("/api/v1/platform/companies/{$company->id}", ['name' => '', 'currency' => 'EURO'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'currency']);
    }

    public function test_deleting_an_active_company_is_refused_and_an_inactive_one_is_soft_deleted(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $company = Company::factory()->create();

        $this->actingAs($admin, 'web')->deleteJson("/api/v1/platform/companies/{$company->id}")->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'deleted_at' => null]);

        $company->forceFill(['is_active' => false])->save();

        $this->actingAs($admin, 'web')->deleteJson("/api/v1/platform/companies/{$company->id}")->assertNoContent();
        $this->assertSoftDeleted('companies', ['id' => $company->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.company_deleted', 'entity_id' => $company->id]);

        $this->actingAs($admin, 'web')->getJson("/api/v1/platform/companies/{$company->id}")->assertNotFound();
    }

    public function test_unknown_company_returns_404(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'web')->getJson('/api/v1/platform/companies/999999')->assertNotFound();
    }

    public function test_company_detail_shows_owner_and_members_with_roles_only(): void
    {
        Notification::fake();
        $admin = User::factory()->platformAdmin()->create();

        $id = $this->actingAs($admin, 'web')->postJson('/api/v1/platform/companies', [
            'name' => 'Initech',
            'owner' => ['name' => 'Bill Lumbergh', 'email' => 'bill@initech.example'],
        ])->assertCreated()->json('data.id');

        $this->actingAs($admin, 'web')->getJson("/api/v1/platform/companies/{$id}")
            ->assertOk()
            ->assertJsonPath('data.owners.0.email', 'bill@initech.example')
            ->assertJsonPath('data.employees_count', 0);

        $this->actingAs($admin, 'web')->getJson("/api/v1/platform/companies/{$id}/members")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'bill@initech.example')
            ->assertJsonPath('data.0.roles.0.slug', 'company.owner')
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.salary');

        [$user, $company] = $this->userWith(['users.view']);
        $this->actingAsIn($user, $company)->getJson("/api/v1/platform/companies/{$id}/members")->assertNotFound();
    }
}
