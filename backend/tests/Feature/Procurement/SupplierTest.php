<?php

namespace Tests\Feature\Procurement;

use App\Models\Company;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\Supplier;
use App\Models\Procurement\SupplierContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    private const MANAGE = ['suppliers.view', 'suppliers.manage'];

    public function test_creating_a_supplier_auto_numbers_the_code_and_encrypts_the_bank_account(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);

        $response = $this->actingAsIn($user, $company)->postJson('/api/v1/suppliers', [
            'name' => 'Acme Metals',
            'type' => 'company',
            'currency' => 'eur',
            'bank_name' => 'First Bank',
            'bank_account_number' => '1234-5678-9012',
            'categories' => ['steel', 'fasteners'],
            'rating' => 4,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'SUP-00001')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.payment_terms_days', 30)
            ->assertJsonPath('data.bank_account_last4', '9012')
            ->assertJsonPath('data.is_approved', false)
            ->assertJsonPath('data.categories', ['steel', 'fasteners'])
            ->assertJsonMissingPath('data.bank_account_number');

        $id = $response->json('data.id');
        $raw = DB::table('suppliers')->where('id', $id)->value('bank_account_number');
        $this->assertNotSame('1234-5678-9012', $raw);
        $this->assertSame('1234-5678-9012', Supplier::withoutTenancy()->find($id)->bank_account_number);
        $this->assertDatabaseHas('audit_logs', ['action' => 'suppliers.created', 'entity_id' => $id]);

        $this->postJson('/api/v1/suppliers', ['name' => 'Second'])->assertCreated()->assertJsonPath('data.code', 'SUP-00002');
    }

    public function test_returns_422_for_empty_payload(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);

        $this->actingAsIn($user, $company)->postJson('/api/v1/suppliers', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_returns_422_for_invalid_rating_and_coordinates(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);

        $this->actingAsIn($user, $company)->postJson('/api/v1/suppliers', ['name' => 'X', 'rating' => 6, 'latitude' => 120, 'type' => 'alien'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating', 'latitude', 'type']);
    }

    public function test_returns_403_and_creates_nothing_without_manage_permission(): void
    {
        [$user, $company] = $this->userWith(['suppliers.view']);

        $this->actingAsIn($user, $company)->postJson('/api/v1/suppliers', ['name' => 'Nope'])
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');

        $this->assertDatabaseMissing('suppliers', ['name' => 'Nope']);
    }

    public function test_returns_403_when_listing_without_view_permission(): void
    {
        [$user, $company] = $this->userWith(['requisitions.create']);

        $this->actingAsIn($user, $company)->getJson('/api/v1/suppliers')->assertForbidden();
    }

    public function test_listing_only_returns_own_company_suppliers(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);
        $mine = Supplier::factory()->create(['company_id' => $company->id]);
        $other = Supplier::factory()->create();

        $this->actingAsIn($user, $company)->getJson('/api/v1/suppliers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonMissing(['id' => $other->id]);
    }

    public function test_returns_404_for_another_companys_supplier_on_read_update_delete_and_summary(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);
        $other = Supplier::factory()->create(['name' => 'Foreign']);

        $this->actingAsIn($user, $company);
        $this->getJson("/api/v1/suppliers/{$other->id}")->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->putJson("/api/v1/suppliers/{$other->id}", ['name' => 'Hijacked'])->assertNotFound();
        $this->deleteJson("/api/v1/suppliers/{$other->id}")->assertNotFound();
        $this->getJson("/api/v1/suppliers/{$other->id}/summary")->assertNotFound();
        $this->postJson("/api/v1/suppliers/{$other->id}/approve")->assertNotFound();
        $this->getJson("/api/v1/suppliers/{$other->id}/contacts")->assertNotFound();

        $this->assertDatabaseHas('suppliers', ['id' => $other->id, 'name' => 'Foreign', 'deleted_at' => null, 'is_approved' => false]);
    }

    public function test_update_and_soft_delete(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->putJson("/api/v1/suppliers/{$supplier->id}", ['name' => 'Renamed', 'lead_time_days' => 9])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.lead_time_days', 9);

        $this->deleteJson("/api/v1/suppliers/{$supplier->id}")->assertNoContent();
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_approving_a_supplier_records_the_approver_and_an_audit_row(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->postJson("/api/v1/suppliers/{$supplier->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.is_approved', true)
            ->assertJsonPath('data.approver.id', $user->id);

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'is_approved' => true, 'approved_by' => $user->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'suppliers.approved', 'entity_id' => $supplier->id, 'actor_id' => $user->id, 'company_id' => $company->id,
        ]);

        $this->postJson("/api/v1/suppliers/{$supplier->id}/approve")->assertStatus(409)->assertJsonPath('code', 'conflict');
    }

    public function test_approving_requires_manage_permission(): void
    {
        [$user, $company] = $this->userWith(['suppliers.view']);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->postJson("/api/v1/suppliers/{$supplier->id}/approve")->assertForbidden();
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'is_approved' => false]);
    }

    public function test_all_and_map_endpoints_return_active_suppliers(): void
    {
        [$user, $company] = $this->userWith(['suppliers.view']);
        Supplier::factory()->create(['company_id' => $company->id, 'name' => 'A Geo', 'latitude' => 51.5, 'longitude' => -0.12]);
        Supplier::factory()->create(['company_id' => $company->id, 'name' => 'B NoGeo', 'latitude' => null, 'longitude' => null]);
        Supplier::factory()->inactive()->create(['company_id' => $company->id, 'name' => 'C Inactive']);

        $this->actingAsIn($user, $company)->getJson('/api/v1/suppliers/all')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.name', 'A Geo');

        $this->getJson('/api/v1/suppliers/map')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.latitude', '51.5000000');
    }

    public function test_summary_reports_order_counts_and_actual_lead_time(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 14)->startOfDay());
        [$user, $company] = $this->userWith(['suppliers.view']);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->approved()->withLines([['quantity' => '2', 'unit_price' => '100']])
            ->create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'order_date' => '2026-10-01']);
        PurchaseOrder::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);
        GoodsReceipt::factory()->confirmed()->create([
            'company_id' => $company->id, 'purchase_order_id' => $order->id, 'received_date' => '2026-10-05',
        ]);

        $this->actingAsIn($user, $company)->getJson("/api/v1/suppliers/{$supplier->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.purchase_orders.total_count', 2)
            ->assertJsonPath('data.purchase_orders.committed_count', 1)
            ->assertJsonPath('data.purchase_orders.committed_total', '200.0000')
            ->assertJsonPath('data.goods_receipts_count', 1)
            ->assertJsonPath('data.avg_lead_time_days', 4)
            ->assertJsonPath('data.outstanding_payable', '0.0000');
    }

    public function test_contacts_can_be_managed_and_only_one_is_primary(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $existing = SupplierContact::factory()->primary()->create(['supplier_id' => $supplier->id]);

        $id = $this->actingAsIn($user, $company)->postJson("/api/v1/suppliers/{$supplier->id}/contacts", ['name' => 'Jane Buyer', 'is_primary' => true])
            ->assertCreated()->assertJsonPath('data.is_primary', true)->json('data.id');

        $this->assertDatabaseHas('supplier_contacts', ['id' => $existing->id, 'is_primary' => false]);
        $this->getJson("/api/v1/suppliers/{$supplier->id}/contacts")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $id);
        $this->putJson("/api/v1/suppliers/{$supplier->id}/contacts/{$id}", ['title' => 'Head of Sales'])->assertOk()->assertJsonPath('data.title', 'Head of Sales');
        $this->deleteJson("/api/v1/suppliers/{$supplier->id}/contacts/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('supplier_contacts', ['id' => $id]);
    }

    public function test_contact_of_another_supplier_cannot_be_reached_through_a_different_supplier(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);
        $supplierA = Supplier::factory()->create(['company_id' => $company->id]);
        $contactOfB = SupplierContact::factory()->create([
            'supplier_id' => Supplier::factory()->create(['company_id' => $company->id])->id,
        ]);

        $this->actingAsIn($user, $company)->putJson("/api/v1/suppliers/{$supplierA->id}/contacts/{$contactOfB->id}", ['name' => 'X'])
            ->assertNotFound();
    }

    public function test_duplicate_code_is_rejected_within_company(): void
    {
        [$user, $company] = $this->userWith(self::MANAGE);
        Supplier::factory()->create(['company_id' => $company->id, 'code' => 'DUP1']);
        Supplier::factory()->create(['company_id' => Company::factory()->create()->id, 'code' => 'DUP2']);

        $this->actingAsIn($user, $company)->postJson('/api/v1/suppliers', ['name' => 'X', 'code' => 'DUP1'])
            ->assertUnprocessable()->assertJsonValidationErrors(['code']);
        $this->postJson('/api/v1/suppliers', ['name' => 'Y', 'code' => 'DUP2'])->assertCreated();
    }

    public function test_user_from_another_company_header_is_forbidden(): void
    {
        [$user] = $this->userWith(self::MANAGE);
        $otherCompany = Company::factory()->create();
        User::factory()->inCompany($otherCompany)->create();

        $this->actingAsIn($user, $otherCompany)->getJson('/api/v1/suppliers')->assertForbidden()->assertJsonPath('code', 'tenant.forbidden');
    }
}
