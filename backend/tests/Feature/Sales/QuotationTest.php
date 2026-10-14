<?php

namespace Tests\Feature\Sales;

use App\Models\Sales\Customer;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationLine;
use Tests\Feature\Sales\Concerns\ArrangesSales;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use ArrangesSales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 09:00:00');
    }

    public function test_creates_draft_with_exact_server_side_totals(): void
    {
        [$user, $company, $customer] = $this->salesUser();

        $response = $this->actAs($user, $company)
            ->postJson('/api/v1/quotations', $this->documentPayload($customer) + ['subtotal' => '1', 'total' => '1']);

        $response->assertCreated()
            ->assertJsonPath('data.number', 'QT-2026-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.date', '2026-10-14')
            ->assertJsonPath('data.subtotal', '180.0000')
            ->assertJsonPath('data.discount_total', '20.0000')
            ->assertJsonPath('data.tax_total', '27.0000')
            ->assertJsonPath('data.total', '207.0000')
            ->assertJsonPath('data.lines.0.line_no', 1)
            ->assertJsonPath('data.lines.0.product_id', $this->catalogueProduct($customer)->id)
            ->assertJsonPath('data.lines.0.line_total', '207.0000');

        $this->assertDatabaseHas('quotations', ['id' => $response->json('data.id'), 'total' => '207.0000', 'created_by' => $user->id]);
        $this->assertDatabaseHas('quotation_lines', ['quotation_id' => $response->json('data.id'), 'tax_amount' => '27.0000', 'company_id' => $company->id]);
    }

    public function test_line_numbers_follow_input_order_when_only_some_lines_have_optional_keys(): void
    {
        [$user, $company, $customer] = $this->salesUser();

        $this->actAs($user, $company)
            ->postJson('/api/v1/quotations', $this->documentPayload($customer, [
                ['description' => 'Free text first', 'quantity' => 1, 'unit_price' => '10'],
                ['description' => 'Catalogue item', 'quantity' => 1, 'unit_price' => '20', 'product_id' => $this->catalogueProduct($customer)->id, 'uom' => 'box'],
                ['description' => 'Free text last', 'quantity' => 1, 'unit_price' => '30'],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.lines.0.line_no', 1)
            ->assertJsonPath('data.lines.0.description', 'Free text first')
            ->assertJsonPath('data.lines.0.product_id', null)
            ->assertJsonPath('data.lines.1.line_no', 2)
            ->assertJsonPath('data.lines.1.product_id', $this->catalogueProduct($customer)->id)
            ->assertJsonPath('data.lines.2.description', 'Free text last')
            ->assertJsonPath('data.total', '60.0000');
    }

    public function test_returns_422_for_empty_payload(): void
    {
        [$user, $company] = $this->salesUser();

        $this->actAs($user, $company)->postJson('/api/v1/quotations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'lines']);
    }

    public function test_customer_from_another_company_is_rejected_with_422(): void
    {
        [$user, $company] = $this->salesUser();
        $foreign = Customer::factory()->create();

        $this->actAs($user, $company)
            ->postJson('/api/v1/quotations', $this->documentPayload($foreign))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);

        $this->assertDatabaseCount('quotations', 0);
    }

    public function test_cross_tenant_quotation_is_404(): void
    {
        [$user, $company] = $this->salesUser();
        $foreign = Quotation::factory()->create();

        $this->actAs($user, $company);
        $this->getJson("/api/v1/quotations/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/quotations/{$foreign->id}", ['notes' => 'x'])->assertNotFound();
        $this->deleteJson("/api/v1/quotations/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/quotations/{$foreign->id}/send")->assertNotFound();
        $this->getJson('/api/v1/quotations')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_returns_403_without_manage_permission(): void
    {
        [$user, $company, $customer] = $this->salesUser(['quotations.view']);

        $this->actAs($user, $company)->postJson('/api/v1/quotations', $this->documentPayload($customer))->assertForbidden();
        $this->assertDatabaseCount('quotations', 0);
    }

    public function test_update_replaces_lines_and_recomputes_while_draft(): void
    {
        [$user, $company, $customer] = $this->salesUser();
        $this->actAs($user, $company);
        $id = $this->postJson('/api/v1/quotations', $this->documentPayload($customer))->json('data.id');

        $this->putJson("/api/v1/quotations/{$id}", ['lines' => [
            ['description' => 'Service', 'quantity' => '1.5', 'unit_price' => '40'],
        ]])->assertOk()->assertJsonPath('data.total', '60.0000')->assertJsonCount(1, 'data.lines');

        $this->assertSame(1, QuotationLine::query()->where('quotation_id', $id)->count());
    }

    public function test_non_draft_quotation_cannot_be_edited_or_deleted(): void
    {
        [$user, $company, $customer] = $this->salesUser();
        $this->actAs($user, $company);
        $id = $this->postJson('/api/v1/quotations', $this->documentPayload($customer))->json('data.id');

        $this->postJson("/api/v1/quotations/{$id}/send")->assertOk()->assertJsonPath('data.status', 'sent');
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotations.sent', 'entity_id' => $id]);

        $this->putJson("/api/v1/quotations/{$id}", ['notes' => 'late change'])->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->deleteJson("/api/v1/quotations/{$id}")->assertStatus(409);
        $this->postJson("/api/v1/quotations/{$id}/send")->assertStatus(409);
    }

    public function test_accept_and_reject_transitions(): void
    {
        [$user, $company] = $this->salesUser();
        $toAccept = Quotation::factory()->for($company)->sent()->create();
        $toReject = Quotation::factory()->for($company)->sent()->create();
        $draft = Quotation::factory()->for($company)->create();

        $this->actAs($user, $company);
        $this->postJson("/api/v1/quotations/{$toAccept->id}/accept")->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->postJson("/api/v1/quotations/{$toReject->id}/reject", ['reason' => 'Too expensive'])
            ->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.rejection_reason', 'Too expensive');
        $this->postJson("/api/v1/quotations/{$draft->id}/accept")->assertStatus(409);
    }

    public function test_convert_creates_sales_order_copying_lines_and_links_both(): void
    {
        [$user, $company, $customer] = $this->salesUser();
        $this->actAs($user, $company);
        $id = $this->postJson('/api/v1/quotations', $this->documentPayload($customer, [
            ['description' => 'Widget', 'quantity' => 2, 'unit_price' => '100', 'discount_percent' => 10, 'tax_rate' => 15, 'uom' => 'pcs', 'product_id' => $this->catalogueProduct($customer)->id],
            ['description' => 'Install', 'quantity' => 1, 'unit_price' => '50'],
        ]))->json('data.id');
        $this->postJson("/api/v1/quotations/{$id}/send")->assertOk();
        $this->postJson("/api/v1/quotations/{$id}/accept")->assertOk();

        $order = $this->postJson("/api/v1/quotations/{$id}/convert")
            ->assertCreated()
            ->assertJsonPath('data.number', 'SO-2026-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.quotation_id', $id)
            ->assertJsonPath('data.total', '257.0000')
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.lines.0.product_id', $this->catalogueProduct($customer)->id)
            ->assertJsonPath('data.lines.0.uom', 'pcs')
            ->assertJsonPath('data.lines.1.description', 'Install')
            ->json('data');

        $this->assertDatabaseHas('quotations', ['id' => $id, 'status' => 'converted', 'converted_sales_order_id' => $order['id']]);
        $this->assertDatabaseHas('sales_orders', ['id' => $order['id'], 'shipping_address_line1' => $customer->shipping_address_line1]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotations.converted', 'entity_id' => $id]);

        $this->postJson("/api/v1/quotations/{$id}/convert")->assertStatus(409);
    }

    public function test_sent_quotations_past_validity_are_expired_lazily_on_listing(): void
    {
        [$user, $company] = $this->salesUser();
        $stale = Quotation::factory()->for($company)->sent()->create(['valid_until' => '2026-10-13']);
        $fresh = Quotation::factory()->for($company)->sent()->create(['valid_until' => '2026-10-14']);
        $draft = Quotation::factory()->for($company)->create(['valid_until' => '2026-10-01']);

        $this->actAs($user, $company)->getJson('/api/v1/quotations?filter[status]=expired')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $stale->id);

        $this->assertDatabaseHas('quotations', ['id' => $fresh->id, 'status' => 'sent']);
        $this->assertDatabaseHas('quotations', ['id' => $draft->id, 'status' => 'draft']);
    }
}
