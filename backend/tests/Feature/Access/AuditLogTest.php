<?php

namespace Tests\Feature\Access;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    private function log(Company $company, string $action, ?User $actor = null, array $extra = []): AuditLog
    {
        return AuditLog::query()->create(array_merge([
            'company_id' => $company->id,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'action' => $action,
            'entity_type' => 'App\\Models\\Branch',
            'entity_id' => 1,
            'entity_label' => 'Head Office',
        ], $extra));
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
    }

    public function test_user_without_audit_view_gets_403(): void
    {
        [$user, $company] = $this->userWith(['users.view']);

        $this->actingAsIn($user, $company)->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    public function test_lists_only_the_current_companys_entries_with_actor_and_supports_filters(): void
    {
        [$auditor, $company] = $this->userWith(['audit.view']);
        $other = Company::factory()->create();

        // Start from a clean slate: factories above wrote their own rows.
        AuditLog::query()->delete();

        $login = $this->log($company, 'auth.login', $auditor);
        $this->log($company, 'branches.created', $auditor, ['entity_label' => 'North']);
        $this->log($other, 'auth.login', $auditor);

        $response = $this->actingAsIn($auditor, $company)->getJson('/api/v1/audit-logs')->assertOk();
        $response->assertJsonCount(2, 'data')->assertJsonPath('meta.pagination.total', 2);
        $this->assertEqualsCanonicalizing([$company->id, $company->id], collect($response->json('data'))->pluck('company_id')->all());

        $this->actingAsIn($auditor, $company)->getJson('/api/v1/audit-logs?filter[action]=auth.')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $login->id)
            ->assertJsonPath('data.0.actor', ['id' => $auditor->id, 'name' => $auditor->name]);

        $this->actingAsIn($auditor, $company)->getJson('/api/v1/audit-logs?search=north')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAsIn($auditor, $company)->getJson('/api/v1/audit-logs?filter[entity_type]=App\\Models\\Branch')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAsIn($auditor, $company)->getJson("/api/v1/audit-logs?filter[actor_id]={$auditor->id}")->assertOk()->assertJsonCount(2, 'data');
        $this->actingAsIn($auditor, $company)->getJson('/api/v1/audit-logs?from='.now()->addDay()->toDateString())->assertOk()->assertJsonCount(0, 'data');
        $this->actingAsIn($auditor, $company)->getJson('/api/v1/audit-logs?to='.now()->toDateString())->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_show_returns_404_for_another_companys_entry(): void
    {
        [$auditor, $company] = $this->userWith(['audit.view']);
        $foreign = $this->log(Company::factory()->create(), 'auth.login');
        $own = $this->log($company, 'auth.logout', $auditor);

        $this->actingAsIn($auditor, $company)->getJson("/api/v1/audit-logs/{$foreign->id}")->assertNotFound();
        $this->actingAsIn($auditor, $company)->getJson("/api/v1/audit-logs/{$own->id}")->assertOk()->assertJsonPath('data.action', 'auth.logout');
    }

    public function test_there_are_no_write_routes_for_audit_logs(): void
    {
        [$auditor, $company] = $this->userWith(['audit.view', 'users.manage', 'roles.manage']);
        $own = $this->log($company, 'auth.logout', $auditor);

        $this->actingAsIn($auditor, $company)->postJson('/api/v1/audit-logs', ['action' => 'x'])->assertStatus(405);
        $this->actingAsIn($auditor, $company)->deleteJson("/api/v1/audit-logs/{$own->id}")->assertStatus(405);
        $this->assertDatabaseHas('audit_logs', ['id' => $own->id]);
    }
}
