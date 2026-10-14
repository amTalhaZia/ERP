<?php

namespace Tests\Feature\Sales;

use App\Models\Company;
use App\Models\Sales\Customer;
use App\Models\Sales\CustomerContact;
use App\Models\Sales\Quotation;
use App\Models\User;
use Tests\Feature\Sales\Concerns\ArrangesSales;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use ArrangesSales;

    public function test_creates_customer_with_auto_code_and_audit(): void
    {
        [$user, $company] = $this->userWith(['customers.view', 'customers.manage']);

        $response = $this->actAs($user, $company)->postJson('/api/v1/customers', [
            'name' => 'Acme Ltd',
            'type' => 'company',
            'email' => 'buyer@acme.test',
            'credit_limit' => '2500.5',
            'tags' => ['vip'],
            'sales_rep_id' => $user->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'CUST-00001')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.payment_terms_days', 30)
            ->assertJsonPath('data.credit_limit', '2500.5000')
            ->assertJsonPath('data.sales_rep.id', $user->id);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('customers', ['id' => $id, 'company_id' => $company->id, 'code' => 'CUST-00001']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customers.created', 'entity_id' => $id]);
    }

    public function test_returns_422_for_empty_payload(): void
    {
        [$user, $company] = $this->userWith(['customers.view', 'customers.manage']);

        $this->actAs($user, $company)->postJson('/api/v1/customers', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_rejects_sales_rep_who_is_not_a_member_of_the_company(): void
    {
        [$user, $company] = $this->userWith(['customers.view', 'customers.manage']);
        $outsider = User::factory()->inCompany(Company::factory()->create())->create();

        $this->actAs($user, $company)
            ->postJson('/api/v1/customers', ['name' => 'X', 'sales_rep_id' => $outsider->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sales_rep_id']);
    }

    public function test_returns_403_without_manage_permission_and_changes_nothing(): void
    {
        [$user, $company] = $this->userWith(['customers.view']);

        $this->actAs($user, $company)->postJson('/api/v1/customers', ['name' => 'Nope'])->assertForbidden();
        $this->assertDatabaseMissing('customers', ['name' => 'Nope']);
    }

    public function test_returns_403_listing_without_view_permission(): void
    {
        [$user, $company] = $this->userWith(['leads.view']);

        $this->actAs($user, $company)->getJson('/api/v1/customers')->assertForbidden();
    }

    public function test_cross_tenant_customer_is_404_for_read_update_delete_and_absent_from_lists(): void
    {
        [$user, $company, $own] = $this->salesUser();
        $foreign = Customer::factory()->create(['name' => 'Foreign Co']);

        $this->actAs($user, $company);
        $this->getJson("/api/v1/customers/{$foreign->id}")->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->putJson("/api/v1/customers/{$foreign->id}", ['name' => 'Hijack'])->assertNotFound();
        $this->deleteJson("/api/v1/customers/{$foreign->id}")->assertNotFound();
        $this->getJson("/api/v1/customers/{$foreign->id}/summary")->assertNotFound();
        $this->postJson("/api/v1/customers/{$foreign->id}/contacts", ['name' => 'Spy'])->assertNotFound();

        $this->getJson('/api/v1/customers')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson('/api/v1/customers/all')->assertOk()->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('customers', ['id' => $foreign->id, 'name' => 'Foreign Co', 'deleted_at' => null]);
        $this->assertDatabaseCount('customer_contacts', 0);
    }

    public function test_contacts_are_managed_and_scoped_to_their_customer(): void
    {
        [$user, $company, $customer] = $this->salesUser();
        $other = Customer::factory()->for($company)->create();
        $otherContact = CustomerContact::factory()->for($other)->create(['company_id' => $company->id]);

        $this->actAs($user, $company);

        $first = $this->postJson("/api/v1/customers/{$customer->id}/contacts", ['name' => 'Ann', 'is_primary' => true])
            ->assertCreated()->json('data.id');
        $second = $this->postJson("/api/v1/customers/{$customer->id}/contacts", ['name' => 'Bob', 'is_primary' => true])
            ->assertCreated()->json('data.id');

        $this->assertDatabaseHas('customer_contacts', ['id' => $first, 'is_primary' => false]);
        $this->assertDatabaseHas('customer_contacts', ['id' => $second, 'is_primary' => true]);

        $this->putJson("/api/v1/customers/{$customer->id}/contacts/{$first}", ['title' => 'CFO'])
            ->assertOk()->assertJsonPath('data.title', 'CFO');

        // A contact of another customer cannot be reached through this customer.
        $this->putJson("/api/v1/customers/{$customer->id}/contacts/{$otherContact->id}", ['name' => 'X'])->assertNotFound();

        $this->deleteJson("/api/v1/customers/{$customer->id}/contacts/{$first}")->assertNoContent();
        $this->assertDatabaseMissing('customer_contacts', ['id' => $first]);

        $this->getJson("/api/v1/customers/{$customer->id}")->assertOk()->assertJsonCount(1, 'data.contacts');
    }

    public function test_communications_require_manage_to_write_and_view_to_list(): void
    {
        [$manager, $company, $customer] = $this->salesUser(['customers.view', 'customers.manage']);
        [$viewer] = $this->userWith(['customers.view'], $company);

        $this->actAs($manager, $company)
            ->postJson("/api/v1/customers/{$customer->id}/communications", [
                'type' => 'call', 'subject' => 'Intro call', 'body' => 'Discussed pricing', 'follow_up_at' => '2026-11-01T10:00:00Z',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'call')
            ->assertJsonPath('data.user.id', $manager->id);

        $this->actAs($viewer, $company)
            ->getJson("/api/v1/customers/{$customer->id}/communications")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/customers/{$customer->id}/communications", ['type' => 'note', 'subject' => 'x'])->assertForbidden();
        $this->assertDatabaseCount('customer_communications', 1);

        $this->actAs($manager, $company)
            ->postJson("/api/v1/customers/{$customer->id}/communications", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['type', 'subject']);
    }

    public function test_summary_counts_documents_and_reports_zero_outstanding_balance_without_invoices(): void
    {
        [$user, $company, $customer] = $this->salesUser();
        Quotation::factory()->for($company)->create(['customer_id' => $customer->id, 'total' => '100']);
        Quotation::factory()->for($company)->sent()->create(['customer_id' => $customer->id, 'total' => '50.5']);

        $this->actAs($user, $company)->getJson("/api/v1/customers/{$customer->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.quotations.count', 2)
            ->assertJsonPath('data.quotations.total', '150.5000')
            ->assertJsonPath('data.quotations.by_status.sent.count', 1)
            ->assertJsonPath('data.sales_orders.count', 0)
            ->assertJsonPath('data.outstanding_balance', '0.0000')
            ->assertJsonPath('data.overdue_balance', '0.0000');
    }

    public function test_map_lists_only_active_customers_with_coordinates(): void
    {
        [$user, $company, $customer] = $this->salesUser();
        $located = Customer::factory()->for($company)->located()->create();
        Customer::factory()->for($company)->located()->inactive()->create();

        $this->actAs($user, $company)->getJson('/api/v1/customers/map')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $located->id);
    }
}
