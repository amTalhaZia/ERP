<?php

namespace Tests\Feature\Approvals;

use App\Enums\Finance\ExpenseClaimStatus;
use App\Enums\Finance\InvoiceStatus;
use App\Models\Company;
use App\Models\Finance\ExpenseClaim;
use App\Models\Finance\Invoice;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequisition;
use App\Models\Sales\Customer;
use App\Models\User;
use Tests\Feature\Dashboard\Concerns\BuildsInsightsData;
use Tests\TestCase;

class ApprovalInboxTest extends TestCase
{
    use BuildsInsightsData;

    private const APPROVER = ['approvals.view', 'leave.approve', 'requisitions.approve', 'invoices.approve', 'expenses.approve'];

    /**
     * Pending work in one company: some raised by colleagues, some by the
     * approver themselves, plus a pending invoice in another company.
     *
     * @return array{0: User, 1: Company, 2: array<string, int>}
     */
    private function scenario(array $extra = []): array
    {
        [$approver, $company] = $this->userWith([...self::APPROVER, ...$extra]);
        $colleague = User::factory()->inCompany($company)->create(['name' => 'Cara Colleague']);
        $leaveType = LeaveType::factory()->create(['company_id' => $company->id, 'name' => 'Annual Leave']);

        $colleagueEmployee = $this->employee($company, ['first_name' => 'Cara', 'last_name' => 'Colleague', 'user_id' => $colleague->id]);
        $ownEmployee = $this->employee($company, ['user_id' => $approver->id]);

        $leave = LeaveRequest::factory()->create(['company_id' => $company->id, 'employee_id' => $colleagueEmployee->id, 'leave_type_id' => $leaveType->id, 'days' => '3.0']);
        LeaveRequest::factory()->create(['company_id' => $company->id, 'employee_id' => $ownEmployee->id, 'leave_type_id' => $leaveType->id]);
        LeaveRequest::factory()->approved()->create(['company_id' => $company->id, 'employee_id' => $colleagueEmployee->id, 'leave_type_id' => $leaveType->id]);

        $requisition = PurchaseRequisition::factory()->submitted()->create(['company_id' => $company->id, 'requester_id' => $colleague->id, 'estimated_total' => '250.0000']);
        PurchaseRequisition::factory()->submitted()->create(['company_id' => $company->id, 'requester_id' => $approver->id]);
        PurchaseRequisition::factory()->create(['company_id' => $company->id, 'requester_id' => $colleague->id]);

        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Globex']);
        $invoice = Invoice::factory()->pendingApproval()->create(['company_id' => $company->id, 'customer_id' => $customer->id, 'created_by' => $colleague->id, 'total' => '1150.0000', 'currency' => 'EUR']);
        Invoice::factory()->pendingApproval()->create(['company_id' => $company->id, 'customer_id' => $customer->id, 'created_by' => $approver->id]);

        $claim = ExpenseClaim::factory()->create(['company_id' => $company->id, 'user_id' => $colleague->id, 'status' => ExpenseClaimStatus::Submitted, 'submitted_at' => now()]);
        ExpenseClaim::factory()->create(['company_id' => $company->id, 'user_id' => $approver->id, 'status' => ExpenseClaimStatus::Submitted]);

        // No purchase_orders.approve → never listed.
        PurchaseOrder::factory()->submitted()->create(['company_id' => $company->id]);

        // Another tenant's pending invoice.
        $other = Company::factory()->create();
        $otherCustomer = Customer::factory()->create(['company_id' => $other->id]);
        Invoice::factory()->create(['company_id' => $other->id, 'customer_id' => $otherCustomer->id, 'status' => InvoiceStatus::PendingApproval]);

        return [$approver, $company, [
            'leave_request' => $leave->id,
            'purchase_requisition' => $requisition->id,
            'invoice' => $invoice->id,
            'expense_claim' => $claim->id,
        ]];
    }

    public function test_lists_only_items_the_user_can_approve_excluding_their_own(): void
    {
        [$approver, $company, $ids] = $this->scenario();

        $response = $this->actAs($approver, $company)->getJson('/api/v1/approvals')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 4)
            ->assertJsonStructure(['data' => [['type', 'id', 'number', 'title', 'requested_by', 'amount', 'currency', 'created_at', 'url']]]);

        $items = collect($response->json('data'));
        $this->assertEqualsCanonicalizing(
            ['leave_request:'.$ids['leave_request'], 'purchase_requisition:'.$ids['purchase_requisition'], 'invoice:'.$ids['invoice'], 'expense_claim:'.$ids['expense_claim']],
            $items->map(fn (array $i) => $i['type'].':'.$i['id'])->all(),
        );

        $invoice = $items->firstWhere('type', 'invoice');
        $this->assertSame('1150.0000', $invoice['amount']);
        $this->assertSame('EUR', $invoice['currency']);
        $this->assertSame('Cara Colleague', $invoice['requested_by']);
        $this->assertSame('/finance/invoices/'.$ids['invoice'], $invoice['url']);
        $this->assertStringContainsString('Globex', $invoice['title']);

        $leave = $items->firstWhere('type', 'leave_request');
        $this->assertSame('/hr/leave?request='.$ids['leave_request'], $leave['url']);
        $this->assertNull($leave['amount']);
        $this->assertStringContainsString('Annual Leave', $leave['title']);
        $this->assertSame('/procurement/requisitions/'.$ids['purchase_requisition'], $items->firstWhere('type', 'purchase_requisition')['url']);
        $this->assertSame('/finance/expenses/'.$ids['expense_claim'], $items->firstWhere('type', 'expense_claim')['url']);
    }

    public function test_counts_per_type_and_total(): void
    {
        [$approver, $company] = $this->scenario();

        $this->actAs($approver, $company)->getJson('/api/v1/approvals/counts')
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.by_type', ['leave_request' => 1, 'purchase_requisition' => 1, 'invoice' => 1, 'expense_claim' => 1]);
    }

    public function test_filters_by_type_and_paginates(): void
    {
        [$approver, $company, $ids] = $this->scenario();
        $this->actAs($approver, $company);

        $this->getJson('/api/v1/approvals?filter[type]=invoice')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ids['invoice']);

        $this->getJson('/api/v1/approvals?per_page=3&page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.pagination.last_page', 2);

        $this->getJson('/api/v1/approvals?filter[type]=bogus')->assertStatus(422);
    }

    public function test_purchase_orders_appear_for_purchase_order_approvers(): void
    {
        [$approver, $company] = $this->userWith(['approvals.view', 'purchase_orders.approve']);
        $order = PurchaseOrder::factory()->submitted()->create(['company_id' => $company->id, 'total' => '99.5000']);
        PurchaseOrder::factory()->submitted()->create(['company_id' => $company->id, 'created_by' => $approver->id]);

        $this->actAs($approver, $company)->getJson('/api/v1/approvals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'purchase_order')
            ->assertJsonPath('data.0.url', '/procurement/orders/'.$order->id)
            ->assertJsonPath('data.0.amount', '99.5000');
    }

    public function test_user_without_approve_permissions_gets_an_empty_inbox(): void
    {
        [, $company] = $this->scenario();
        [$viewer] = $this->userWith(['approvals.view'], $company);

        $this->actAs($viewer, $company)->getJson('/api/v1/approvals')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/approvals/counts')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_requires_approvals_view(): void
    {
        [$user, $company] = $this->userWith(['invoices.approve']);

        $this->actAs($user, $company)->getJson('/api/v1/approvals')->assertForbidden();
        $this->getJson('/api/v1/approvals/counts')->assertForbidden();
    }

    public function test_dashboard_pending_approvals_use_the_inbox(): void
    {
        [$approver, $company] = $this->scenario(['dashboard.view']);

        $this->actAs($approver, $company)->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.metrics.pending_approvals.current', 4)
            ->assertJsonCount(4, 'data.pending_approvals');
    }
}
