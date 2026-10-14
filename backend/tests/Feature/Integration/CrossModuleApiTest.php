<?php

namespace Tests\Feature\Integration;

use App\Enums\Finance\BillStatus;
use App\Enums\Finance\InvoiceStatus;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Finance\Bill;
use App\Models\Finance\Invoice;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\Supplier;
use App\Models\Sales\Customer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cross-module API behaviour: tenant-checked references, partner balances,
 * pickers and inventory gap endpoints. Runs against ALL migrations.
 */
class CrossModuleApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 09:00:00');
    }

    public function test_quotation_line_with_another_companys_product_is_rejected(): void
    {
        [$user, $company] = $this->userWith(['quotations.view', 'quotations.manage']);
        $customer = Customer::factory()->for($company)->create();
        $foreignProduct = Product::factory()->create();
        $ownProduct = Product::factory()->create(['company_id' => $company->id]);
        $line = ['description' => 'Widget', 'quantity' => 1, 'unit_price' => '10'];

        $this->actingAsIn($user, $company)->postJson('/api/v1/quotations', [
            'customer_id' => $customer->id,
            'lines' => [$line + ['product_id' => $foreignProduct->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.product_id']);

        $this->assertDatabaseCount('quotations', 0);

        $this->postJson('/api/v1/quotations', [
            'customer_id' => $customer->id,
            'lines' => [$line + ['product_id' => $ownProduct->id]],
        ])->assertCreated()->assertJsonPath('data.lines.0.product.id', $ownProduct->id);
    }

    public function test_sales_order_with_another_companys_warehouse_is_rejected(): void
    {
        [$user, $company] = $this->userWith(['sales_orders.view', 'sales_orders.manage']);
        $customer = Customer::factory()->for($company)->create();

        $this->actingAsIn($user, $company)->postJson('/api/v1/sales-orders', [
            'customer_id' => $customer->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'lines' => [['description' => 'Widget', 'quantity' => 1, 'unit_price' => '10']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['warehouse_id']);
    }

    public function test_customer_summary_reports_open_invoice_balances(): void
    {
        [$user, $company] = $this->userWith(['customers.view']);
        $customer = Customer::factory()->for($company)->create();
        $other = Customer::factory()->for($company)->create();
        $invoice = fn (array $attributes) => Invoice::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id] + $attributes);

        $invoice(['status' => InvoiceStatus::Approved, 'balance_due' => '100.0000', 'due_date' => '2026-10-01']);
        $invoice(['status' => InvoiceStatus::PartiallyPaid, 'balance_due' => '40.5000', 'due_date' => '2026-11-01']);
        $invoice(['status' => InvoiceStatus::Draft, 'balance_due' => '999.0000']);
        $invoice(['status' => InvoiceStatus::Paid, 'balance_due' => '0.0000']);
        Invoice::factory()->create(['company_id' => $company->id, 'customer_id' => $other->id, 'status' => InvoiceStatus::Approved, 'balance_due' => '7.0000']);

        $this->actingAsIn($user, $company)->getJson("/api/v1/customers/{$customer->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.outstanding_balance', '140.5000')
            ->assertJsonPath('data.overdue_balance', '100.0000')
            ->assertJsonPath('data.open_invoices_count', 2)
            ->assertJsonMissingPath('data.outstanding_balance_note');
    }

    public function test_supplier_summary_reports_open_bill_balances(): void
    {
        [$user, $company] = $this->userWith(['suppliers.view']);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        Bill::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'status' => BillStatus::Approved, 'balance_due' => '250.0000', 'due_date' => '2026-10-30']);
        Bill::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'status' => BillStatus::Draft, 'balance_due' => '80.0000']);

        $this->actingAsIn($user, $company)->getJson("/api/v1/suppliers/{$supplier->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.outstanding_payable', '250.0000')
            ->assertJsonPath('data.overdue_payable', '0.0000');
    }

    public function test_member_picker_needs_no_users_permission_and_filters_by_permission(): void
    {
        [$user, $company] = $this->userWith([]);
        $approver = User::factory()->inCompany($company, ['invoices.approve'])->create(['name' => 'Ada Approver']);
        $inactive = User::factory()->inCompany($company, ['invoices.approve'])->create(['name' => 'Ina Inactive']);
        CompanyUser::query()->where('company_id', $company->id)->where('user_id', $inactive->id)->update(['is_active' => false]);
        $outsider = User::factory()->inCompany(Company::factory()->create(), ['invoices.approve'])->create(['name' => 'Otto Outsider']);

        $this->actingAsIn($user, $company)->getJson('/api/v1/members')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['id' => $inactive->id])
            ->assertJsonMissing(['id' => $outsider->id])
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'avatar_url']]]);

        $this->getJson('/api/v1/members?permission=invoices.approve')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approver->id);

        $this->getJson('/api/v1/members?search=ada')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/members?permission=Bad Slug!')->assertUnprocessable();
    }

    public function test_warehouse_picker_lists_active_warehouses_of_the_tenant(): void
    {
        [$user, $company] = $this->userWith(['warehouses.view']);
        $active = Warehouse::factory()->create(['company_id' => $company->id, 'name' => 'Alpha', 'is_active' => true]);
        Warehouse::factory()->create(['company_id' => $company->id, 'name' => 'Closed', 'is_active' => false]);
        Warehouse::factory()->create(['name' => 'Foreign']);
        $expected = Warehouse::withoutTenancy()->where('company_id', $company->id)->where('is_active', true)->count();

        $this->actingAsIn($user, $company)->getJson('/api/v1/warehouses/all')
            ->assertOk()
            ->assertJsonCount($expected, 'data')
            ->assertJsonFragment(['id' => $active->id, 'name' => 'Alpha', 'code' => $active->code, 'is_default' => (bool) $active->is_default])
            ->assertJsonMissing(['name' => 'Closed'])
            ->assertJsonMissing(['name' => 'Foreign']);

        [$stranger] = $this->userWith([], $company);
        $this->actingAsIn($stranger, $company)->getJson('/api/v1/warehouses/all')->assertForbidden();
    }

    public function test_product_image_can_be_removed(): void
    {
        Storage::fake('public');
        [$user, $company] = $this->userWith(['products.view', 'products.manage']);
        $product = Product::factory()->create(['company_id' => $company->id]);
        $this->actingAsIn($user, $company)
            ->post("/api/v1/products/{$product->id}/image", ['image' => UploadedFile::fake()->image('p.png')], ['Accept' => 'application/json'])
            ->assertOk();
        $path = $product->refresh()->image_path;
        Storage::disk('public')->assertExists($path);

        $this->deleteJson("/api/v1/products/{$product->id}/image")->assertOk()->assertJsonPath('data.id', $product->id);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($product->refresh()->image_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'products.image_removed', 'entity_id' => $product->id]);

        [$viewer] = $this->userWith(['products.view'], $company);
        $this->actingAsIn($viewer, $company)->deleteJson("/api/v1/products/{$product->id}/image")->assertForbidden();
    }

    public function test_low_stock_honours_type_and_active_filters_and_category_tree_can_include_inactive(): void
    {
        [$user, $company] = $this->userWith(['products.view']);
        Product::factory()->reorderAt('5')->create(['company_id' => $company->id, 'sku' => 'ACTIVE-LOW']);
        Product::factory()->reorderAt('5')->inactive()->create(['company_id' => $company->id, 'sku' => 'INACTIVE-LOW']);
        $this->actingAsIn($user, $company);

        $this->getJson('/api/v1/products/low-stock')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.sku', 'ACTIVE-LOW');
        $this->getJson('/api/v1/products/low-stock?filter[is_active]=false')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.sku', 'INACTIVE-LOW');
        $this->getJson('/api/v1/products/low-stock?filter[type]=service')->assertOk()->assertJsonCount(0, 'data');

        ProductCategory::factory()->create(['company_id' => $company->id, 'name' => 'Retired', 'is_active' => false]);
        $retired = fn (array $tree): int => collect($tree)->where('name', 'Retired')->count();

        $this->assertSame(0, $retired($this->getJson('/api/v1/product-categories/tree')->assertOk()->json('data')));
        $this->assertSame(1, $retired($this->getJson('/api/v1/product-categories/tree?include_inactive=1')->assertOk()->json('data')));
    }
}
