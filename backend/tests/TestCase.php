<?php

namespace Tests;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = PermissionSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Mirror the SPA: requests come from the stateful frontend origin, so
        // Sanctum attaches the cookie session (SANCTUM_STATEFUL_DOMAINS=localhost).
        $this->withHeader('Origin', 'http://localhost');
    }

    /**
     * Act as $user with the company header set, mirroring the SPA.
     */
    protected function actingAsIn(User $user, Company $company): static
    {
        // Sanctum caches the resolved user on the guard; reset so tests can switch users.
        $this->app['auth']->forgetGuards();

        $this->actingAs($user, 'web');
        $this->withHeader(config('erp.company_header'), (string) $company->id);

        return $this;
    }

    /**
     * Arrange a user who holds the given permissions in a fresh company.
     *
     * @param  array<int, string>  $permissions
     * @return array{0: User, 1: Company}
     */
    protected function userWith(array $permissions, ?Company $company = null): array
    {
        $company ??= Company::factory()->create();
        $user = User::factory()->inCompany($company, $permissions)->create();

        return [$user, $company];
    }

    /**
     * Add an existing user to another company with the given permissions.
     *
     * @param  array<int, string>  $permissions
     */
    protected function grantInCompany(User $user, Company $company, array $permissions): void
    {
        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'is_default' => false,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $role = Role::query()->create([
            'company_id' => $company->id,
            'slug' => 'test-'.Str::lower(Str::random(6)),
            'name' => 'Test Role',
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('slug', $permissions)->pluck('id')->all());
        $user->roles()->attach($role->id, ['company_id' => $company->id]);
        $user->forgetPermissionCache();
    }

    protected function assertErrorCode(TestResponse $response, string $code): void
    {
        $response->assertJsonPath('code', $code);
    }
}
