<?php

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class PlatformSystemTest extends TestCase
{
    public function test_system_overview_requires_a_platform_user(): void
    {
        $this->getJson('/api/v1/platform/system')->assertUnauthorized();

        [$user, $company] = $this->userWith(['audit.view']);
        $this->actingAsIn($user, $company)->getJson('/api/v1/platform/system')->assertNotFound();
    }

    public function test_storage_usage_is_reported_per_company_without_record_contents(): void
    {
        $this->getJson('/api/v1/platform/system/usage')->assertUnauthorized();
        [$user, $company] = $this->userWith(['audit.view']);
        $this->actingAsIn($user, $company)->getJson('/api/v1/platform/system/usage')->assertNotFound();

        $admin = User::factory()->platformAdmin()->create();
        $this->app['auth']->forgetGuards();
        $response = $this->actingAs($admin, 'web')->getJson('/api/v1/platform/system/usage?refresh=1')->assertOk();

        $response->assertJsonStructure(['data' => [
            'companies' => [['id', 'name', 'code', 'rows', 'db_bytes', 'file_bytes', 'tables' => [['name', 'rows']]]],
            'shared_bytes',
            'generated_at',
        ]]);

        $row = collect($response->json('data.companies'))->firstWhere('id', $company->id);
        $this->assertNotNull($row);
        $this->assertGreaterThan(0, $row['rows']);
        $this->assertLessThanOrEqual(5, count($row['tables']));
    }

    public function test_system_overview_returns_safe_diagnostics_only(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        Company::factory()->count(2)->create();

        $response = $this->actingAs($admin, 'web')->getJson('/api/v1/platform/system')->assertOk();

        $response->assertJsonStructure(['data' => [
            'app' => ['name', 'version', 'framework', 'environment', 'debug'],
            'php' => ['version'],
            'database' => ['driver', 'version', 'reachable'],
            'services' => ['queue', 'cache', 'session', 'mail'],
            'counts' => ['companies', 'users', 'active_companies', 'active_users', 'platform_users'],
            'storage' => ['disk', 'path_free_bytes', 'path_total_bytes'],
            'tables',
            'generated_at',
        ]]);

        $response->assertJsonPath('data.database.driver', 'mysql')
            ->assertJsonPath('data.database.reachable', true)
            ->assertJsonPath('data.counts.companies', 2)
            ->assertJsonPath('data.counts.users', 1)
            ->assertJsonPath('data.php.version', PHP_VERSION);

        $tables = collect($response->json('data.tables'));
        $this->assertTrue($tables->pluck('name')->contains('companies'));
        $this->assertSame(['name', 'rows', 'size_bytes'], array_keys($tables->first()));

        $body = $response->getContent();
        $this->assertStringNotContainsString(config('database.connections.mysql.password'), $body);
        $this->assertStringNotContainsString('DB_PASSWORD', $body);
        $this->assertStringNotContainsString(config('database.connections.mysql.database'), $body);
    }

    public function test_platform_audit_log_lists_entries_across_companies_with_company_info(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $a = Company::factory()->create(['name' => 'Alpha']);
        $b = Company::factory()->create(['name' => 'Bravo']);
        AuditLog::query()->delete();

        AuditLog::query()->create(['company_id' => $a->id, 'action' => 'auth.login', 'actor_id' => $admin->id, 'actor_name' => $admin->name]);
        AuditLog::query()->create(['company_id' => $b->id, 'action' => 'branches.created', 'entity_label' => 'Depot']);
        AuditLog::query()->create(['company_id' => null, 'action' => 'platform.user_updated']);

        $response = $this->actingAs($admin, 'web')->getJson('/api/v1/platform/audit-logs?sort=action')->assertOk();
        $response->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.action', 'auth.login')
            ->assertJsonPath('data.0.company', ['id' => $a->id, 'name' => 'Alpha'])
            ->assertJsonPath('data.0.actor', ['id' => $admin->id, 'name' => $admin->name])
            ->assertJsonPath('data.2.company', null);

        $this->actingAs($admin, 'web')->getJson("/api/v1/platform/audit-logs?filter[company_id]={$b->id}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.action', 'branches.created');
        $this->actingAs($admin, 'web')->getJson('/api/v1/platform/audit-logs?filter[company_id]=none')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.action', 'platform.user_updated');
        $this->actingAs($admin, 'web')->getJson('/api/v1/platform/audit-logs?filter[action]=platform.')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin, 'web')->getJson('/api/v1/platform/audit-logs?search=depot')->assertOk()->assertJsonCount(1, 'data');

        $id = $response->json('data.1.id');
        $this->actingAs($admin, 'web')->getJson("/api/v1/platform/audit-logs/{$id}")->assertOk()->assertJsonPath('data.company.name', 'Bravo');
    }
}
