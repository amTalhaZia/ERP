<?php

namespace Tests\Feature\Inventory;

use App\Events\CompanyProvisioned;
use App\Listeners\Inventory\SeedInventoryDefaults;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Inventory\Concerns\InteractsWithInventory;
use Tests\TestCase;

class WarehouseAndDefaultsTest extends TestCase
{
    use InteractsWithInventory;

    public function test_seed_listener_is_registered_for_company_provisioned(): void
    {
        Event::fake();

        Event::assertListening(CompanyProvisioned::class, SeedInventoryDefaults::class);
    }

    public function test_provisioning_a_company_seeds_units_and_main_warehouse(): void
    {
        $company = Company::factory()->create();
        Branch::factory()->for($company)->headOffice()->create();

        $listener = app(SeedInventoryDefaults::class);
        $listener->handle(new CompanyProvisioned($company));
        $listener->handle(new CompanyProvisioned($company)); // idempotent

        $this->assertSame(6, DB::table('units_of_measure')->where('company_id', $company->id)->count());
        $this->assertSame(1, DB::table('warehouses')->where('company_id', $company->id)->count());

        foreach (['pcs' => 0, 'kg' => 3, 'L' => 3, 'm' => 2, 'box' => 0, 'hr' => 2] as $symbol => $precision) {
            $this->assertDatabaseHas('units_of_measure', ['company_id' => $company->id, 'symbol' => $symbol, 'precision' => $precision]);
        }

        $headOffice = Branch::withoutTenancy()->where('company_id', $company->id)->where('is_head_office', true)->firstOrFail();
        $this->assertDatabaseHas('warehouses', [
            'company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main Warehouse',
            'is_default' => true, 'branch_id' => $headOffice->id,
        ]);
    }

    public function test_manager_creates_warehouse_with_locations_and_single_default(): void
    {
        [$user, $company] = $this->userWith(['warehouses.view', 'warehouses.manage']);
        $old = Warehouse::factory()->default()->create(['company_id' => $company->id]);
        $branch = Branch::factory()->for($company)->create();

        $this->actingAsIn($user, $company);
        $id = $this->postJson('/api/v1/warehouses', [
            'name' => 'North DC', 'code' => 'NDC', 'branch_id' => $branch->id, 'is_default' => true,
            'latitude' => '53.4808', 'longitude' => '-2.2426',
        ])->assertCreated()->assertJsonPath('data.branch.id', $branch->id)->json('data.id');

        $this->assertDatabaseHas('warehouses', ['id' => $old->id, 'is_default' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'warehouses.created', 'entity_id' => $id]);

        $zone = $this->postJson("/api/v1/warehouses/{$id}/locations", ['code' => 'Z1', 'name' => 'Zone 1', 'type' => 'zone'])
            ->assertCreated()->json('data.id');
        $this->postJson("/api/v1/warehouses/{$id}/locations", ['code' => 'Z1-B1', 'name' => 'Bin 1', 'type' => 'bin', 'parent_id' => $zone])
            ->assertCreated()->assertJsonPath('data.parent.id', $zone);
        $this->postJson("/api/v1/warehouses/{$id}/locations", ['code' => 'Z1', 'name' => 'Dup'])
            ->assertUnprocessable()->assertJsonValidationErrors(['code']);

        $this->getJson("/api/v1/warehouses/{$id}/locations")->assertOk()->assertJsonCount(2, 'data');
        $this->deleteJson("/api/v1/locations/{$zone}")->assertStatus(409);

        $this->deleteJson("/api/v1/warehouses/{$id}")->assertForbidden(); // default warehouse
    }

    public function test_warehouse_holding_stock_cannot_be_deleted(): void
    {
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper();

        $this->actingAsIn($user, $company);
        $this->receive($warehouse, $product, '1', '1');

        $this->deleteJson("/api/v1/warehouses/{$warehouse->id}")->assertStatus(409);
        $this->deleteJson("/api/v1/products/{$product->id}")->assertStatus(409);
    }
}
