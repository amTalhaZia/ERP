<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false, 'deactivated_at' => now()]);
    }

    public function platformOwner(): static
    {
        return $this->state(['platform_role' => Role::PLATFORM_OWNER]);
    }

    public function platformAdmin(): static
    {
        return $this->state(['platform_role' => Role::PLATFORM_ADMIN]);
    }

    /**
     * Give the user an active membership in the company, with a role holding
     * exactly the given permission slugs (created on the fly). This is the
     * primary way tests arrange "a user in company X who can do Y".
     *
     * @param  array<int, string>  $permissions
     */
    public function inCompany(Company $company, array $permissions = [], bool $default = true, ?array $branchIds = null): static
    {
        return $this->afterCreating(function (User $user) use ($company, $permissions, $default, $branchIds): void {
            CompanyUser::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'is_default' => $default,
                'is_active' => true,
                'branch_ids' => $branchIds,
                'joined_at' => now(),
            ]);

            if ($permissions !== []) {
                $role = Role::query()->create([
                    'company_id' => $company->id,
                    'slug' => 'test-'.Str::lower(Str::random(6)),
                    'name' => 'Test Role',
                    'is_system' => false,
                ]);

                $ids = collect($permissions)->map(fn (string $slug) => Permission::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['module' => Str::before($slug, '.'), 'description' => $slug, 'is_platform' => str_starts_with($slug, 'platform.')],
                )->id);

                $role->permissions()->sync($ids->all());
                $user->roles()->attach($role->id, ['company_id' => $company->id]);
            }
        });
    }
}
