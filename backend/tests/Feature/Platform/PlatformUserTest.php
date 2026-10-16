<?php

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class PlatformUserTest extends TestCase
{
    public function test_guest_gets_401_and_tenant_user_gets_404(): void
    {
        $this->getJson('/api/v1/platform/users')->assertUnauthorized();

        [$user, $company] = $this->userWith(['users.manage']);
        $this->actingAsIn($user, $company)->getJson('/api/v1/platform/users')->assertNotFound();
        $this->actingAsIn($user, $company)->patchJson("/api/v1/platform/users/{$user->id}", ['platform_role' => Role::PLATFORM_OWNER])->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'platform_role' => null]);
    }

    public function test_platform_admin_lists_all_users_with_search_and_filters(): void
    {
        $admin = User::factory()->platformAdmin()->create(['name' => 'Admin Person']);
        $company = Company::factory()->create();
        $member = User::factory()->inCompany($company)->create(['name' => 'Quentin Member']);
        User::factory()->inactive()->create(['name' => 'Gone User']);

        $response = $this->actingAs($admin, 'web')->getJson('/api/v1/platform/users?sort=name')->assertOk();
        $response->assertJsonCount(3, 'data');

        $row = collect($response->json('data'))->firstWhere('id', $member->id);
        $this->assertSame(1, $row['memberships_count']);
        $this->assertNull($row['platform_role']);

        $this->actingAs($admin, 'web')->getJson('/api/v1/platform/users?search=quentin')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin, 'web')->getJson('/api/v1/platform/users?filter[is_active]=false')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Gone User');
        $this->actingAs($admin, 'web')->getJson('/api/v1/platform/users?filter[platform_role]=platform.admin')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $admin->id);
        $this->actingAs($admin, 'web')->getJson('/api/v1/platform/users?filter[platform_role]=none')->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($admin, 'web')->getJson("/api/v1/platform/users/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.memberships.0.company_id', $company->id)
            ->assertJsonPath('data.memberships.0.company_name', $company->name);
    }

    public function test_list_shows_company_names_and_filters_by_company(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $northwind = Company::factory()->create(['name' => 'Northwind']);
        $indus = Company::factory()->create(['name' => 'Indus']);
        $nwUser = User::factory()->inCompany($northwind)->create();
        $indusUser = User::factory()->inCompany($indus)->create();
        $loner = User::factory()->create();

        $all = $this->actingAs($admin, 'web')->getJson('/api/v1/platform/users')->assertOk();
        $row = collect($all->json('data'))->firstWhere('id', $nwUser->id);
        $this->assertSame('Northwind', $row['memberships'][0]['company_name']);

        $this->actingAs($admin, 'web')->getJson("/api/v1/platform/users?filter[company_id]={$indus->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $indusUser->id);

        $none = $this->actingAs($admin, 'web')->getJson('/api/v1/platform/users?filter[company_id]=none')->assertOk();
        $this->assertEqualsCanonicalizing([$admin->id, $loner->id], collect($none->json('data'))->pluck('id')->all());
    }

    public function test_update_requires_a_field(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin, 'web')->patchJson("/api/v1/platform/users/{$target->id}", [])->assertUnprocessable()->assertJsonValidationErrors(['is_active']);
        $this->actingAs($admin, 'web')->patchJson("/api/v1/platform/users/{$target->id}", ['platform_role' => 'superuser'])->assertUnprocessable()->assertJsonValidationErrors(['platform_role']);
    }

    public function test_platform_admin_can_deactivate_and_reactivate_users_but_not_change_platform_roles(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin, 'web')->patchJson("/api/v1/platform/users/{$target->id}", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $target->refresh();
        $this->assertFalse($target->is_active);
        $this->assertNotNull($target->deactivated_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.user_updated', 'entity_id' => $target->id, 'actor_id' => $admin->id]);

        $this->actingAs($admin, 'web')->patchJson("/api/v1/platform/users/{$target->id}", ['is_active' => true])->assertOk();
        $this->assertNull($target->fresh()->deactivated_at);

        $this->actingAs($admin, 'web')->patchJson("/api/v1/platform/users/{$target->id}", ['platform_role' => Role::PLATFORM_ADMIN])
            ->assertForbidden()->assertJsonPath('code', 'auth.forbidden');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'platform_role' => null]);
    }

    public function test_platform_owner_can_grant_and_revoke_platform_roles(): void
    {
        $owner = User::factory()->platformOwner()->create();
        $target = User::factory()->create();

        $this->actingAs($owner, 'web')->patchJson("/api/v1/platform/users/{$target->id}", ['platform_role' => Role::PLATFORM_ADMIN])
            ->assertOk()->assertJsonPath('data.platform_role', Role::PLATFORM_ADMIN)->assertJsonPath('data.is_platform_admin', true);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'platform_role' => Role::PLATFORM_ADMIN]);

        $audit = AuditLog::query()->where('action', 'platform.user_updated')->where('entity_id', $target->id)->latest('id')->firstOrFail();
        $this->assertNull($audit->old_values['platform_role']);
        $this->assertSame(Role::PLATFORM_ADMIN, $audit->new_values['platform_role']);

        $this->actingAs($owner, 'web')->patchJson("/api/v1/platform/users/{$target->id}", ['platform_role' => null])
            ->assertOk()->assertJsonPath('data.platform_role', null);
    }

    public function test_user_cannot_demote_or_deactivate_themselves(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->actingAs($owner, 'web')->patchJson("/api/v1/platform/users/{$owner->id}", ['platform_role' => Role::PLATFORM_ADMIN])
            ->assertUnprocessable()->assertJsonValidationErrors(['platform_role']);

        $this->actingAs($owner, 'web')->patchJson("/api/v1/platform/users/{$owner->id}", ['is_active' => false])
            ->assertUnprocessable()->assertJsonValidationErrors(['is_active']);

        $this->assertDatabaseHas('users', ['id' => $owner->id, 'platform_role' => Role::PLATFORM_OWNER, 'is_active' => true]);
    }

    public function test_unknown_user_returns_404(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'web')->patchJson('/api/v1/platform/users/999999', ['is_active' => false])->assertNotFound();
    }
}
