<?php

namespace Database\Seeders;

use App\Actions\Company\CreateCompanyAction;
use App\Actions\HR\CreateEmployeeAction;
use App\Actions\HR\GenerateLeaveBalancesAction;
use App\Actions\Inventory\RecordStockMovementAction;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\HR\Designation;
use App\Models\HR\Holiday;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Procurement\Supplier;
use App\Models\Role;
use App\Models\Sales\Customer;
use App\Models\User;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\Demo\Concerns\SeedsDemoCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A deliberately small demo for a hosted showcase: one company, two branches,
 * seven logins (platform, owner, admin, HR, inventory, accountant, employee),
 * six employees, a few products with stock, two customers, two suppliers and
 * an upcoming holiday. No invoices or payroll — those are left to show live.
 *
 *   DEMO_PASSWORD=... php artisan db:seed --class=SmallDemoSeeder
 */
class SmallDemoSeeder extends Seeder
{
    use SeedsDemoCompany;

    /** email local-part => [role slug, display name] */
    private const LOGINS = [
        'admin' => ['company.admin', 'Admin User'],
        'hr' => ['hr.manager', 'Hannah Reyes'],
        'inventory' => ['inventory.manager', 'Ian Walsh'],
        'accountant' => ['accountant', 'Aaron Brooks'],
        'employee' => ['employee', 'Emily Carter'],
    ];

    public function run(CreateCompanyAction $createCompany, TenantContext $tenant): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SmallDemoSeeder must never run in production.');
        }

        $this->call(PermissionSeeder::class);

        $password = env('DEMO_PASSWORD') ?: Str::password(14, symbols: false);
        $hash = Hash::make($password);

        $platform = $this->login('platform@erp.test', 'Platform Owner (Demo)', $hash);
        $platform->forceFill(['platform_role' => Role::PLATFORM_OWNER])->saveQuietly();

        $owner = $this->login('owner@northwind.test', 'Olivia Owner', $hash);
        $company = $createCompany->handle([
            'name' => 'Northwind Trading (Demo)',
            'legal_name' => 'Northwind Trading Ltd.',
            'email' => 'info@northwind.test',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'country' => 'US',
            'city' => 'New York',
            'address_line1' => '1 Demo Street',
            'is_demo' => true,
        ], $owner);

        $tenant->set($company, null);
        Branch::query()->where('is_head_office', true)->update(['latitude' => 40.7128, 'longitude' => -74.0060]);
        Branch::query()->create(['name' => 'Boston Office', 'code' => 'BOS', 'city' => 'Boston', 'country' => 'US', 'latitude' => 42.3601, 'longitude' => -71.0589]);

        $departments = [];
        foreach (['Human Resources' => 'HR', 'Finance' => 'FIN', 'Operations' => 'OPS'] as $name => $code) {
            $departments[$code] = Department::query()->create(['name' => $name, 'code' => $code]);
        }

        $roles = Role::query()->where('company_id', $company->id)->pluck('id', 'slug');
        foreach (self::LOGINS as $local => [$slug, $name]) {
            $user = $this->login("{$local}@northwind.test", $name, $hash);
            CompanyUser::query()->firstOrCreate(
                ['company_id' => $company->id, 'user_id' => $user->id],
                ['is_default' => true, 'is_active' => true, 'joined_at' => now()],
            );
            $user->roles()->syncWithoutDetaching([$roles[$slug] => ['company_id' => $company->id]]);
        }

        $this->bootDemo($company, 'small');
        $this->employees($departments);
        app(GenerateLeaveBalancesAction::class)->handle((int) $this->today()->year);
        $this->inventory();
        $this->partners();

        Holiday::query()->create(['name' => 'Company Day', 'date' => $this->today()->addDays(21)->toDateString(), 'is_recurring' => false]);

        $tenant->clear();

        $this->command?->newLine();
        $this->command?->warn('SMALL DEMO — password for all: '.$password);
        $this->command?->line('  platform@erp.test, owner@ admin@ hr@ inventory@ accountant@ employee@northwind.test');
    }

    private function login(string $email, string $name, string $hash): User
    {
        $user = User::query()->firstOrCreate(['email' => $email], ['name' => $name, 'password' => $hash, 'is_active' => true]);
        $user->forceFill(['email_verified_at' => now(), 'password' => $hash])->saveQuietly();

        return $user;
    }

    /** @param array<string, Department> $departments */
    private function employees(array $departments): void
    {
        $designations = [];
        foreach (['HEAD' => ['Head of Department', 2], 'MGR' => ['Manager', 3], 'LEAD' => ['Team Lead', 4], 'ASSOC' => ['Associate', 6]] as $code => [$title, $level]) {
            $designations[$code] = Designation::query()->create(['title' => $title, 'code' => $code, 'level' => $level, 'is_active' => true]);
        }

        $headOffice = Branch::query()->where('is_head_office', true)->firstOrFail();
        $boston = Branch::query()->where('code', 'BOS')->firstOrFail();
        $create = app(CreateEmployeeAction::class);

        // [first, last, login local-part|null, dept, designation, branch, salary, reports-to index]
        $people = [
            ['Hannah', 'Reyes', 'hr', 'HR', 'HEAD', $headOffice, 7500, null],
            ['Aaron', 'Brooks', 'accountant', 'FIN', 'MGR', $headOffice, 6500, null],
            ['Marcus', 'Bell', null, 'OPS', 'HEAD', $headOffice, 8000, null],
            ['Ian', 'Walsh', 'inventory', 'OPS', 'LEAD', $headOffice, 5200, 2],
            ['Emily', 'Carter', 'employee', 'OPS', 'ASSOC', $headOffice, 3400, 2],
            ['Ruby', 'Coleman', null, 'FIN', 'ASSOC', $boston, 3700, 1],
        ];

        $this->as('hr', function () use ($people, $departments, $designations, $create): void {
            $made = [];
            foreach ($people as $i => [$first, $last, $local, $dept, $designation, $branch, $salary, $reportsTo]) {
                $user = $local ? $this->demoUser($local) : null;
                $made[$i] = $create->handle([
                    'user_id' => $user?->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'email' => $user?->email ?? Str::lower("{$first}.{$last}").'@northwind.test',
                    'phone' => $this->phone(1),
                    'branch_id' => $branch->id,
                    'city' => $branch->city,
                    'country' => 'US',
                    'department_id' => $departments[$dept]->id,
                    'designation_id' => $designations[$designation]->id,
                    'manager_id' => $reportsTo !== null ? $made[$reportsTo]->id : null,
                    'employment_type' => 'full_time',
                    'status' => 'active',
                    'hired_at' => $this->today()->subYears(2 + $i % 3)->startOfMonth()->toDateString(),
                    'base_salary' => $this->amount($salary),
                    'notes' => 'Demo employee — fictional data.',
                ]);
            }

            foreach (['HR' => 0, 'FIN' => 1, 'OPS' => 2] as $code => $index) {
                $departments[$code]->update(['manager_employee_id' => $made[$index]->id]);
            }
        });
    }

    private function inventory(): void
    {
        $this->as('inventory', function (): void {
            $uoms = UnitOfMeasure::query()->pluck('id', 'symbol');
            $category = ProductCategory::query()->create(['name' => 'Office & IT', 'code' => 'OIT', 'is_active' => true]);
            $record = app(RecordStockMovementAction::class);
            $main = $this->mainWarehouse();

            // [name, cost, sale, opening qty, reorder level]
            foreach ([
                ['Wireless Mouse', 8, 15, 120, 20],
                ['USB-C Charger 65W', 18, 35, 60, 15],
                ['A4 Copy Paper (box)', 22, 38, 40, 10],
                ['Headset with Microphone', 25, 49, 8, 15], // starts below its reorder level
            ] as $i => [$name, $cost, $sale, $quantity, $reorder]) {
                $product = Product::query()->create([
                    'category_id' => $category->id,
                    'uom_id' => $uoms['pcs'] ?? $uoms->first(),
                    'sku' => sprintf('NW-OIT-%03d', $i + 1),
                    'name' => $name,
                    'description' => "{$name} (demo catalogue item).",
                    'type' => 'goods',
                    'cost_price' => $this->amount($cost),
                    'sale_price' => $this->amount($sale),
                    'currency' => 'USD',
                    'tax_rate' => '15',
                    'reorder_level' => (string) $reorder,
                    'reorder_quantity' => (string) ($reorder * 3),
                    'is_active' => true,
                ]);

                $record->handle([
                    'product_id' => $product->id,
                    'warehouse_id' => $main->id,
                    'type' => 'opening',
                    'quantity' => $quantity,
                    'unit_cost' => (string) $product->cost_price,
                    'reason' => 'Opening balance (demo)',
                    'moved_at' => $this->today()->subDays(30)->setTime(9, 0),
                ]);
            }
        });
    }

    private function partners(): void
    {
        $numberer = app(DocumentNumberer::class);

        $this->as('admin', function () use ($numberer): void {
            foreach (['Bluebird Retail Co.', 'Harbor Office Supplies'] as $name) {
                Customer::query()->create([
                    'code' => $numberer->next('customer', yearly: false, prefix: 'CUST', padding: 5),
                    'name' => $name,
                    'type' => 'company',
                    'email' => 'accounts@'.Str::slug($name, '').'.example',
                    'phone' => $this->phone(5),
                    'currency' => 'USD',
                    'payment_terms_days' => 30,
                    'billing_city' => 'New York',
                    'billing_country' => 'US',
                    'notes' => 'Fictional demo customer.',
                    'is_active' => true,
                ]);
            }

            foreach (['Atlas Electronics Wholesale', 'Summit Paper Mills'] as $name) {
                Supplier::query()->create([
                    'code' => $numberer->next('supplier', yearly: false, prefix: 'SUP', padding: 5),
                    'name' => $name,
                    'type' => 'company',
                    'email' => 'orders@'.Str::slug($name, '').'.example',
                    'phone' => $this->phone(3),
                    'currency' => 'USD',
                    'payment_terms_days' => 30,
                    'city' => 'New York',
                    'country' => 'US',
                    'notes' => 'Fictional demo supplier.',
                    'is_active' => true,
                ]);
            }
        });
    }
}
