<?php

namespace Tests\Feature\Procurement;

use App\Enums\Procurement\RequisitionStatus;
use App\Models\Company;
use App\Models\Department;
use App\Models\Inventory\Product;
use App\Models\Procurement\PurchaseRequisition;
use App\Models\Procurement\Supplier;
use App\Models\User;
use App\Notifications\Procurement\RequisitionDecided;
use App\Notifications\Procurement\RequisitionSubmitted;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PurchaseRequisitionTest extends TestCase
{
    private ?int $productId = null;

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'priority' => 'high',
            'needed_by' => '2026-11-01',
            'justification' => 'Replace worn tooling',
            'lines' => [
                ['description' => 'Drill bits 8mm', 'quantity' => 10, 'uom' => 'pcs', 'estimated_unit_price' => '4.25'],
                ['description' => 'Safety gloves', 'quantity' => '2.5', 'uom' => 'box', 'estimated_unit_price' => 12, 'product_id' => $this->productId],
            ],
        ];
    }

    public function test_requester_creates_a_numbered_draft_with_lines_and_totals(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 14));
        [$user, $company] = $this->userWith(['requisitions.create']);
        $department = Department::factory()->create(['company_id' => $company->id]);
        $this->productId = Product::factory()->create(['company_id' => $company->id])->id;

        $response = $this->actingAsIn($user, $company)->postJson('/api/v1/requisitions', $this->payload(['department_id' => $department->id]));

        $response->assertCreated()
            ->assertJsonPath('data.number', 'PR-2026-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.requester.id', $user->id)
            ->assertJsonPath('data.department.id', $department->id)
            ->assertJsonPath('data.estimated_total', '72.5000')
            ->assertJsonPath('data.lines.1.line_no', 2)
            ->assertJsonPath('data.lines.1.product_id', $this->productId)
            ->assertJsonPath('data.lines.1.line_total', '30.0000');

        $this->assertDatabaseCount('purchase_requisition_lines', 2);
        $this->assertDatabaseHas('purchase_requisitions', ['id' => $response->json('data.id'), 'company_id' => $company->id, 'requester_id' => $user->id]);
    }

    public function test_returns_422_for_empty_payload(): void
    {
        [$user, $company] = $this->userWith(['requisitions.create']);

        $this->actingAsIn($user, $company)->postJson('/api/v1/requisitions', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['lines']);

        $this->postJson('/api/v1/requisitions', ['lines' => [['quantity' => 0]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['lines.0.description', 'lines.0.quantity']);
    }

    public function test_department_from_another_company_is_rejected(): void
    {
        [$user, $company] = $this->userWith(['requisitions.create']);
        $foreign = Department::factory()->create();

        $this->actingAsIn($user, $company)->postJson('/api/v1/requisitions', $this->payload(['department_id' => $foreign->id]))
            ->assertUnprocessable()->assertJsonValidationErrors(['department_id']);
    }

    public function test_returns_403_without_create_permission(): void
    {
        [$user, $company] = $this->userWith(['requisitions.view']);

        $this->actingAsIn($user, $company)->postJson('/api/v1/requisitions', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('purchase_requisitions', 0);
    }

    public function test_requester_only_sees_own_requisitions_while_viewer_sees_all(): void
    {
        $company = Company::factory()->create();
        $requester = User::factory()->inCompany($company, ['requisitions.create'])->create();
        $viewer = User::factory()->inCompany($company, ['requisitions.view'])->create();
        $own = PurchaseRequisition::factory()->create(['company_id' => $company->id, 'requester_id' => $requester->id]);
        $someoneElses = PurchaseRequisition::factory()->create(['company_id' => $company->id, 'requester_id' => $viewer->id]);

        $this->actingAsIn($requester, $company)->getJson('/api/v1/requisitions')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson("/api/v1/requisitions/{$someoneElses->id}")->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAsIn($viewer, $company)->getJson('/api/v1/requisitions')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_returns_404_for_another_companys_requisition(): void
    {
        [$user, $company] = $this->userWith(['requisitions.view', 'requisitions.create', 'requisitions.approve']);
        $foreign = PurchaseRequisition::factory()->submitted()->withLines()->create();

        $this->actingAsIn($user, $company);
        $this->getJson("/api/v1/requisitions/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/requisitions/{$foreign->id}", $this->payload())->assertNotFound();
        $this->deleteJson("/api/v1/requisitions/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/requisitions/{$foreign->id}/approve")->assertNotFound();

        $this->assertDatabaseHas('purchase_requisitions', ['id' => $foreign->id, 'status' => 'submitted']);
    }

    public function test_only_the_requester_can_edit_their_draft(): void
    {
        $company = Company::factory()->create();
        $requester = User::factory()->inCompany($company, ['requisitions.create'])->create();
        $colleague = User::factory()->inCompany($company, ['requisitions.create'])->create();
        $draft = PurchaseRequisition::factory()->withLines()->create(['company_id' => $company->id, 'requester_id' => $requester->id]);

        $this->actingAsIn($colleague, $company)->putJson("/api/v1/requisitions/{$draft->id}", $this->payload())->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->actingAsIn($requester, $company)->putJson("/api/v1/requisitions/{$draft->id}", $this->payload(['lines' => [
            ['description' => 'Only line', 'quantity' => 1, 'estimated_unit_price' => 9],
        ]]))->assertOk()->assertJsonCount(1, 'data.lines')->assertJsonPath('data.estimated_total', '9.0000');
    }

    public function test_submitting_notifies_every_approver_in_the_company_except_the_requester(): void
    {
        Notification::fake();
        $company = Company::factory()->create();
        $requester = User::factory()->inCompany($company, ['requisitions.create', 'requisitions.approve'])->create();
        $approver = User::factory()->inCompany($company, ['requisitions.approve'])->create();
        $bystander = User::factory()->inCompany($company, ['requisitions.view'])->create();
        $otherCompanyApprover = User::factory()->inCompany(Company::factory()->create(), ['requisitions.approve'])->create();
        $draft = PurchaseRequisition::factory()->withLines()->create(['company_id' => $company->id, 'requester_id' => $requester->id]);

        $this->actingAsIn($requester, $company)->postJson("/api/v1/requisitions/{$draft->id}/submit")
            ->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->assertNotNull($draft->fresh()->submitted_at);
        Notification::assertSentTo($approver, RequisitionSubmitted::class, function (RequisitionSubmitted $notification) use ($approver, $draft, $company) {
            $data = $notification->toDatabase($approver);

            return $notification->via($approver) === ['database']
                && $data['type'] === 'requisition.submitted'
                && $data['url'] === "/procurement/requisitions/{$draft->id}"
                && $data['company_id'] === $company->id
                && str_contains($data['title'], $draft->number);
        });
        Notification::assertNotSentTo([$requester, $bystander, $otherCompanyApprover], RequisitionSubmitted::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase.requisitions.submitted', 'entity_id' => $draft->id]);

        $this->postJson("/api/v1/requisitions/{$draft->id}/submit")->assertStatus(409);
        $this->putJson("/api/v1/requisitions/{$draft->id}", $this->payload())->assertStatus(409)->assertJsonPath('code', 'conflict');
    }

    public function test_approver_cannot_approve_their_own_requisition(): void
    {
        [$user, $company] = $this->userWith(['requisitions.create', 'requisitions.approve']);
        $own = PurchaseRequisition::factory()->submitted()->withLines()->create(['company_id' => $company->id, 'requester_id' => $user->id]);

        $this->actingAsIn($user, $company)->postJson("/api/v1/requisitions/{$own->id}/approve")->assertForbidden();
        $this->postJson("/api/v1/requisitions/{$own->id}/reject", ['reason' => 'x'])->assertForbidden();

        $this->assertDatabaseHas('purchase_requisitions', ['id' => $own->id, 'status' => 'submitted', 'approver_id' => null]);
    }

    public function test_approve_requires_the_approve_permission(): void
    {
        $company = Company::factory()->create();
        $requester = User::factory()->inCompany($company, ['requisitions.create'])->create();
        $viewer = User::factory()->inCompany($company, ['requisitions.view'])->create();
        $requisition = PurchaseRequisition::factory()->submitted()->withLines()->create(['company_id' => $company->id, 'requester_id' => $requester->id]);

        $this->actingAsIn($viewer, $company)->postJson("/api/v1/requisitions/{$requisition->id}/approve")
            ->assertForbidden()->assertJsonPath('code', 'auth.forbidden');
        $this->assertDatabaseHas('purchase_requisitions', ['id' => $requisition->id, 'status' => 'submitted']);
    }

    public function test_approving_sets_approver_fields_audits_and_notifies_the_requester(): void
    {
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(9, 0));
        $company = Company::factory()->create();
        $requester = User::factory()->inCompany($company, ['requisitions.create'])->create();
        $approver = User::factory()->inCompany($company, ['requisitions.approve'])->create();
        $requisition = PurchaseRequisition::factory()->submitted()->withLines()->create(['company_id' => $company->id, 'requester_id' => $requester->id]);

        $this->actingAsIn($approver, $company)->postJson("/api/v1/requisitions/{$requisition->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approver.id', $approver->id)
            ->assertJsonPath('data.approved_at', now()->toJSON());

        $this->assertDatabaseHas('purchase_requisitions', ['id' => $requisition->id, 'status' => 'approved', 'approver_id' => $approver->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase.requisitions.approved', 'entity_id' => $requisition->id, 'actor_id' => $approver->id]);
        Notification::assertSentTo($requester, RequisitionDecided::class, fn (RequisitionDecided $n) => $n->toDatabase($requester)['type'] === 'requisition.approved');

        $this->postJson("/api/v1/requisitions/{$requisition->id}/approve")->assertStatus(409);
    }

    public function test_rejecting_requires_a_reason_and_stores_it(): void
    {
        Notification::fake();
        $company = Company::factory()->create();
        $requester = User::factory()->inCompany($company, ['requisitions.create'])->create();
        $approver = User::factory()->inCompany($company, ['requisitions.approve'])->create();
        $requisition = PurchaseRequisition::factory()->submitted()->withLines()->create(['company_id' => $company->id, 'requester_id' => $requester->id]);

        $this->actingAsIn($approver, $company)->postJson("/api/v1/requisitions/{$requisition->id}/reject", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/v1/requisitions/{$requisition->id}/reject", ['reason' => 'Over budget'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Over budget');

        $this->assertDatabaseHas('purchase_requisitions', ['id' => $requisition->id, 'status' => 'rejected', 'approver_id' => $approver->id, 'approved_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase.requisitions.rejected', 'entity_id' => $requisition->id]);
        Notification::assertSentTo($requester, RequisitionDecided::class, fn (RequisitionDecided $n) => $n->toDatabase($requester)['type'] === 'requisition.rejected');
    }

    public function test_requester_can_cancel_a_submitted_requisition_but_not_an_approved_one(): void
    {
        [$user, $company] = $this->userWith(['requisitions.create']);
        $submitted = PurchaseRequisition::factory()->submitted()->create(['company_id' => $company->id, 'requester_id' => $user->id]);
        $approved = PurchaseRequisition::factory()->approved()->create(['company_id' => $company->id, 'requester_id' => $user->id]);

        $this->actingAsIn($user, $company)->postJson("/api/v1/requisitions/{$submitted->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/requisitions/{$approved->id}/cancel")->assertStatus(409);
    }

    public function test_approved_requisition_converts_into_a_draft_purchase_order(): void
    {
        $company = Company::factory()->create();
        $buyer = User::factory()->inCompany($company, ['purchase_orders.manage', 'requisitions.view'])->create();
        $supplier = Supplier::factory()->create(['company_id' => $company->id, 'currency' => 'GBP', 'payment_terms_days' => 45]);
        $requisition = PurchaseRequisition::factory()->approved()->withLines(2)->create(['company_id' => $company->id]);

        $response = $this->actingAsIn($buyer, $company)->postJson("/api/v1/requisitions/{$requisition->id}/create-purchase-order", ['supplier_id' => $supplier->id]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.requisition_id', $requisition->id)
            ->assertJsonPath('data.supplier.id', $supplier->id)
            ->assertJsonPath('data.currency', 'GBP')
            ->assertJsonPath('data.payment_terms_days', 45)
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.total', (string) $requisition->fresh()->estimated_total);

        $this->assertSame(RequisitionStatus::Ordered, $requisition->fresh()->status);
        $this->postJson("/api/v1/requisitions/{$requisition->id}/create-purchase-order", ['supplier_id' => $supplier->id])->assertStatus(409);
    }

    public function test_converting_with_a_supplier_from_another_company_is_rejected(): void
    {
        [$buyer, $company] = $this->userWith(['purchase_orders.manage']);
        $foreignSupplier = Supplier::factory()->create();
        $requisition = PurchaseRequisition::factory()->approved()->withLines()->create(['company_id' => $company->id]);

        $this->actingAsIn($buyer, $company)->postJson("/api/v1/requisitions/{$requisition->id}/create-purchase-order", ['supplier_id' => $foreignSupplier->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['supplier_id']);

        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertSame(RequisitionStatus::Approved, $requisition->fresh()->status);
    }
}
