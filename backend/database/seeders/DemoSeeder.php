<?php

namespace Database\Seeders;

use App\Actions\Company\CreateCompanyAction;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DEVELOPMENT ONLY. Creates two clearly-labelled demo companies (is_demo=true)
 * with branches, departments and one user per standard role, then calls each
 * module's demo seeder when present.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Refuses to run in production. All demo users share the password printed at
 * the end (DEMO_PASSWORD env or a random one) — there is no fixed backdoor.
 */
class DemoSeeder extends Seeder
{
    /** @var array<string, string> role slug => email local-part */
    private const ROLE_USERS = [
        'company.owner' => 'owner',
        'company.admin' => 'admin',
        'hr.manager' => 'hr',
        'accountant' => 'accountant',
        'sales.manager' => 'sales',
        'inventory.manager' => 'inventory',
        'procurement.officer' => 'procurement',
        'department.manager' => 'manager',
        'employee' => 'employee',
        'auditor' => 'auditor',
    ];

    public function run(CreateCompanyAction $createCompany, TenantContext $tenant): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must never run in production.');
        }

        $this->call(PermissionSeeder::class);

        $password = env('DEMO_PASSWORD') ?: Str::password(14, symbols: false);
        $hash = Hash::make($password);

        $companies = [
            ['name' => 'Northwind Trading (Demo)', 'domain' => 'northwind.test', 'currency' => 'USD', 'timezone' => 'America/New_York', 'country' => 'US', 'city' => 'New York',
                'branches' => [['Boston Office', 'BOS', 'Boston', 42.3601, -71.0589], ['Chicago Office', 'CHI', 'Chicago', 41.8781, -87.6298], ['Austin Office', 'AUS', 'Austin', 30.2672, -97.7431]],
                'hq' => [40.7128, -74.0060]],
            ['name' => 'Indus Manufacturing (Demo)', 'domain' => 'indus.test', 'currency' => 'PKR', 'timezone' => 'Asia/Karachi', 'country' => 'PK', 'city' => 'Karachi',
                'branches' => [['Lahore Plant', 'LHE', 'Lahore', 31.5204, 74.3587], ['Islamabad Office', 'ISB', 'Islamabad', 33.6844, 73.0479]],
                'hq' => [24.8607, 67.0011]],
        ];

        $platform = User::query()->firstOrCreate(['email' => 'platform@erp.test'], [
            'name' => 'Platform Owner (Demo)', 'password' => $hash, 'is_active' => true, 'platform_role' => Role::PLATFORM_OWNER,
        ]);
        $platform->forceFill(['email_verified_at' => now()])->saveQuietly();

        foreach ($companies as $def) {
            if (Company::query()->where('name', $def['name'])->exists()) {
                $this->command?->warn("{$def['name']} already exists — skipped.");

                continue;
            }

            $owner = $this->user("owner@{$def['domain']}", 'Olivia Owner', $hash);

            $company = $createCompany->handle([
                'name' => $def['name'],
                'legal_name' => str_replace(' (Demo)', ' Ltd.', $def['name']),
                'email' => "info@{$def['domain']}",
                'currency' => $def['currency'],
                'timezone' => $def['timezone'],
                'country' => $def['country'],
                'city' => $def['city'],
                'address_line1' => '1 Demo Street',
                'is_demo' => true,
            ], $owner);

            $tenant->set($company, null);

            Branch::query()->where('is_head_office', true)->update(['latitude' => $def['hq'][0], 'longitude' => $def['hq'][1]]);
            foreach ($def['branches'] as [$name, $code, $city, $lat, $lng]) {
                Branch::query()->create(['name' => $name, 'code' => $code, 'city' => $city, 'country' => $def['country'], 'latitude' => $lat, 'longitude' => $lng]);
            }

            $parents = [];
            foreach (['Finance' => 'FIN', 'Human Resources' => 'HR', 'Sales' => 'SAL', 'Operations' => 'OPS', 'Procurement' => 'PRC', 'IT' => 'IT'] as $name => $code) {
                $parents[$code] = Department::query()->create(['name' => $name, 'code' => $code]);
            }
            Department::query()->create(['name' => 'Accounts Payable', 'code' => 'FIN-AP', 'parent_id' => $parents['FIN']->id]);
            Department::query()->create(['name' => 'Warehouse', 'code' => 'OPS-WH', 'parent_id' => $parents['OPS']->id]);

            $roles = Role::query()->where('company_id', $company->id)->pluck('id', 'slug');
            foreach (self::ROLE_USERS as $slug => $local) {
                if ($slug === 'company.owner') {
                    continue;
                }
                $user = $this->user("{$local}@{$def['domain']}", Str::headline($local).' User', $hash);
                CompanyUser::query()->firstOrCreate(
                    ['company_id' => $company->id, 'user_id' => $user->id],
                    ['is_default' => true, 'is_active' => true, 'joined_at' => now()],
                );
                $user->roles()->syncWithoutDetaching([$roles[$slug] => ['company_id' => $company->id]]);
            }

            // Module demo data (each is optional and self-contained).
            foreach ([
                'Database\\Seeders\\Demo\\HrDemoSeeder',
                'Database\\Seeders\\Demo\\PayrollDemoSeeder',
                'Database\\Seeders\\Demo\\InventoryDemoSeeder',
                'Database\\Seeders\\Demo\\ProcurementDemoSeeder',
                'Database\\Seeders\\Demo\\SalesDemoSeeder',
                'Database\\Seeders\\Demo\\FinanceDemoSeeder',
                'Database\\Seeders\\Demo\\ProjectsDemoSeeder',
            ] as $seeder) {
                if (class_exists($seeder)) {
                    $this->callWith($seeder, ['company' => $company]);
                }
            }

            $tenant->clear();
            $this->command?->info("Seeded {$def['name']}");
        }

        $this->command?->newLine();
        $this->command?->warn('DEMO ACCOUNTS (development only) — password for all: '.$password);
        $this->command?->line('  platform@erp.test (platform owner)');
        foreach (self::ROLE_USERS as $slug => $local) {
            $this->command?->line("  {$local}@northwind.test / {$local}@indus.test  ({$slug})");
        }
    }

    private function user(string $email, string $name, string $hash): User
    {
        $user = User::query()->firstOrCreate(['email' => $email], ['name' => $name, 'password' => $hash, 'is_active' => true]);
        $user->forceFill(['email_verified_at' => now(), 'password' => $hash])->saveQuietly();

        return $user;
    }
}
