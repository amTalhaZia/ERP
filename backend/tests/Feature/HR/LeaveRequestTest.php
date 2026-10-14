<?php

namespace Tests\Feature\HR;

use App\Models\Company;
use App\Models\HR\Employee;
use App\Models\HR\Holiday;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\User;
use App\Notifications\HR\LeaveRequestDecided;
use App\Notifications\HR\LeaveRequestSubmitted;
use Illuminate\Support\Carbon;
use Tests\Feature\HR\Concerns\SwitchesUsers;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use SwitchesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 14 Oct 2026; the requested week is Mon 19 – Fri 23 Oct.
        $this->travelTo(Carbon::parse('2026-10-14 10:00:00'));
    }

    /**
     * @return array{0: User, 1: Company, 2: Employee, 3: LeaveType, 4: User}
     */
    private function arrange(string $entitled = '20.0'): array
    {
        $company = Company::factory()->create();
        $requester = User::factory()->inCompany($company, ['leave.request'])->create();
        $employee = Employee::factory()->for($company)->forUser($requester)->create();
        $type = LeaveType::factory()->for($company)->create();
        LeaveBalance::factory()->create([
            'company_id' => $company->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'year' => 2026, 'entitled' => $entitled,
        ]);
        $approver = User::factory()->inCompany($company, ['leave.view', 'leave.approve'])->create();

        return [$requester, $company, $employee, $type, $approver];
    }

    public function test_submits_a_request_excluding_weekends_and_holidays_and_notifies_approvers(): void
    {
        [$requester, $company, $employee, $type, $approver] = $this->arrange();
        Holiday::factory()->for($company)->create(['date' => '2026-10-21']);

        $response = $this->switchTo($requester, $company)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-25', 'reason' => 'Family trip',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.days', '4.0')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.employee_id', $employee->id);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'company_id' => $company->id, 'status' => 'pending']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'leave.requests.submitted', 'entity_id' => $id]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $approver->id, 'type' => LeaveRequestSubmitted::class,
        ]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $requester->id]);
    }

    public function test_empty_payload_returns_422(): void
    {
        [$requester, $company] = $this->arrange();

        $this->switchTo($requester, $company)->postJson('/api/v1/leave-requests', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['leave_type_id', 'start_date', 'end_date']);
    }

    public function test_user_without_an_employee_record_gets_422(): void
    {
        [, $company, , $type] = $this->arrange();
        $stranger = User::factory()->inCompany($company, ['leave.request'])->create();

        $this->switchTo($stranger, $company)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-20',
        ])->assertUnprocessable()->assertJsonValidationErrors(['employee']);
    }

    public function test_user_without_leave_request_permission_gets_403(): void
    {
        [, $company, , $type] = $this->arrange();
        $user = User::factory()->inCompany($company, ['employees.view'])->create();
        Employee::factory()->for($company)->forUser($user)->create();

        $this->switchTo($user, $company)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-20',
        ])->assertForbidden();

        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_insufficient_balance_returns_422(): void
    {
        [$requester, $company, , $type] = $this->arrange('2.0');

        $this->switchTo($requester, $company)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-23',
        ])->assertUnprocessable()->assertJsonValidationErrors(['leave_type_id']);

        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_unpaid_leave_skips_the_balance_check(): void
    {
        [$requester, $company] = $this->arrange();
        $unpaid = LeaveType::factory()->for($company)->unpaid()->create();

        $this->switchTo($requester, $company)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $unpaid->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-23',
        ])->assertCreated()->assertJsonPath('data.days', '5.0');
    }

    public function test_overlapping_request_is_rejected(): void
    {
        [$requester, $company, $employee, $type] = $this->arrange();
        LeaveRequest::factory()->create([
            'company_id' => $company->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-10-20', 'end_date' => '2026-10-21', 'days' => '2.0',
        ]);

        $this->switchTo($requester, $company)->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-23',
        ])->assertUnprocessable()->assertJsonValidationErrors(['start_date']);

        $this->assertDatabaseCount('leave_requests', 1);
    }

    public function test_approval_deducts_the_balance_and_notifies_the_requester(): void
    {
        [$requester, $company, $employee, $type, $approver] = $this->arrange();
        $request = LeaveRequest::factory()->create([
            'company_id' => $company->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-10-19', 'end_date' => '2026-10-23', 'days' => '5.0',
        ]);

        $this->switchTo($approver, $company)->postJson("/api/v1/leave-requests/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approver_id', $approver->id);

        $this->assertDatabaseHas('leave_balances', ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'used' => '5.0']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'leave.requests.approved', 'entity_id' => $request->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $requester->id, 'type' => LeaveRequestDecided::class]);

        $this->postJson("/api/v1/leave-requests/{$request->id}/approve")->assertStatus(409);
        $this->assertDatabaseHas('leave_balances', ['employee_id' => $employee->id, 'used' => '5.0']);
    }

    public function test_approver_cannot_approve_their_own_request(): void
    {
        [, $company, , $type] = $this->arrange();
        $manager = User::factory()->inCompany($company, ['leave.request', 'leave.approve'])->create();
        $managerEmployee = Employee::factory()->for($company)->forUser($manager)->create();
        $own = LeaveRequest::factory()->create([
            'company_id' => $company->id, 'employee_id' => $managerEmployee->id, 'leave_type_id' => $type->id,
        ]);

        $this->switchTo($manager, $company)->postJson("/api/v1/leave-requests/{$own->id}/approve")->assertForbidden();

        $this->assertDatabaseHas('leave_requests', ['id' => $own->id, 'status' => 'pending']);
    }

    public function test_approve_without_leave_approve_returns_403(): void
    {
        [$requester, $company, $employee, $type] = $this->arrange();
        $request = LeaveRequest::factory()->create(['company_id' => $company->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id]);

        $this->switchTo($requester, $company)->postJson("/api/v1/leave-requests/{$request->id}/approve")->assertForbidden();
        $this->assertDatabaseHas('leave_requests', ['id' => $request->id, 'status' => 'pending']);
    }

    public function test_reject_requires_reason_and_records_it(): void
    {
        [$requester, $company, $employee, $type, $approver] = $this->arrange();
        $request = LeaveRequest::factory()->create(['company_id' => $company->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id]);

        $this->switchTo($approver, $company)->postJson("/api/v1/leave-requests/{$request->id}/reject")
            ->assertUnprocessable()->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/v1/leave-requests/{$request->id}/reject", ['reason' => 'Peak season'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('leave_requests', ['id' => $request->id, 'rejection_reason' => 'Peak season']);
        $this->assertDatabaseHas('leave_balances', ['employee_id' => $employee->id, 'used' => '0.0']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $requester->id, 'type' => LeaveRequestDecided::class]);
    }

    public function test_requester_can_cancel_their_pending_request(): void
    {
        [$requester, $company, $employee, $type] = $this->arrange();
        $request = LeaveRequest::factory()->create(['company_id' => $company->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id]);

        $this->switchTo($requester, $company)->postJson("/api/v1/leave-requests/{$request->id}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_requesters_only_see_their_own_requests_and_cross_tenant_is_404(): void
    {
        [$requester, $company, $employee, $type, $approver] = $this->arrange();
        $mine = LeaveRequest::factory()->create(['company_id' => $company->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id]);
        $colleague = Employee::factory()->for($company)->create();
        $theirs = LeaveRequest::factory()->create(['company_id' => $company->id, 'employee_id' => $colleague->id, 'leave_type_id' => $type->id]);
        $foreign = LeaveRequest::factory()->create();

        $this->switchTo($requester, $company)->getJson('/api/v1/leave-requests')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->getJson("/api/v1/leave-requests/{$theirs->id}")->assertForbidden();

        $this->switchTo($approver, $company)->getJson('/api/v1/leave-requests')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/leave-requests/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/leave-requests/{$foreign->id}/approve")->assertNotFound();
        $this->assertDatabaseHas('leave_requests', ['id' => $foreign->id, 'status' => 'pending']);
    }

    public function test_generate_balances_creates_rows_for_active_employees(): void
    {
        $company = Company::factory()->create();
        $manager = User::factory()->inCompany($company, ['leave.manage', 'leave.view'])->create();
        Employee::factory()->for($company)->count(2)->create();
        Employee::factory()->for($company)->terminated()->create();
        LeaveType::factory()->for($company)->count(2)->create();

        $this->switchTo($manager, $company)->postJson('/api/v1/leave-balances/generate', ['year' => 2027])
            ->assertOk()->assertJsonPath('data.created', 4);

        $this->postJson('/api/v1/leave-balances/generate', ['year' => 2027])->assertOk()->assertJsonPath('data.created', 0);
        $this->assertDatabaseCount('leave_balances', 4);
    }

    public function test_mine_returns_the_users_balances(): void
    {
        [$requester, $company] = $this->arrange();

        $this->switchTo($requester, $company)->getJson('/api/v1/leave-balances/mine')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.remaining', '20.0');
    }
}
