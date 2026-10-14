<?php

namespace Tests\Feature\HR;

use App\Actions\Company\CreateCompanyAction;
use App\Models\Company;
use App\Models\Department;
use App\Models\HR\AttendanceRecord;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\HR\PerformanceReview;
use App\Models\HR\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Feature\HR\Concerns\SwitchesUsers;
use Tests\TestCase;

/**
 * Provisioning defaults, reports, designations and performance reviews.
 */
class HrModuleTest extends TestCase
{
    use SwitchesUsers;

    public function test_company_provisioning_seeds_default_leave_types_and_shift(): void
    {
        $company = app(CreateCompanyAction::class)->handle(['name' => 'Provisioned Co', 'currency' => 'USD']);

        $codes = LeaveType::forCompany($company)->orderBy('code')->pluck('code')->all();
        $this->assertSame(['ANNUAL', 'MATERNITY', 'PATERNITY', 'SICK', 'UNPAID'], $codes);
        $this->assertFalse((bool) LeaveType::forCompany($company)->where('code', 'UNPAID')->value('is_paid'));

        $shift = Shift::forCompany($company)->firstOrFail();
        $this->assertTrue($shift->is_default);
        $this->assertSame([1, 2, 3, 4, 5], $shift->days_of_week);
        $this->assertSame(60, $shift->break_minutes);
    }

    public function test_headcount_report_aggregates_by_department_and_status(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 10:00:00'));
        [$viewer, $company] = $this->userWith(['employees.view']);
        $department = Department::factory()->for($company)->create();
        Employee::factory()->for($company)->count(2)->create(['department_id' => $department->id, 'hired_at' => '2026-10-05']);
        Employee::factory()->for($company)->create(['hired_at' => '2025-01-01']);
        Employee::factory()->for($company)->terminated()->create(['hired_at' => '2024-01-01', 'terminated_at' => '2026-10-10']);
        Employee::factory()->count(3)->create(); // other company

        $this->switchTo($viewer, $company)->getJson('/api/v1/hr/reports/headcount?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.by_department.0.id', $department->id)
            ->assertJsonPath('data.by_department.0.total', 2)
            ->assertJsonPath('data.joiners', 2)
            ->assertJsonPath('data.leavers', 1);
    }

    public function test_attendance_and_leave_reports_require_a_range_and_aggregate(): void
    {
        [$viewer, $company] = $this->userWith(['employees.view']);
        $employee = Employee::factory()->for($company)->create();
        AttendanceRecord::factory()->create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-10-12', 'worked_minutes' => 480]);
        $type = LeaveType::factory()->for($company)->create();
        LeaveRequest::factory()->approved()->create([
            'company_id' => $company->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id,
            'start_date' => '2026-10-20', 'end_date' => '2026-10-21', 'days' => '2.0',
        ]);

        $this->switchTo($viewer, $company)->getJson('/api/v1/hr/reports/attendance')
            ->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);

        $this->getJson('/api/v1/hr/reports/attendance?from=2026-10-01&to=2026-10-31')
            ->assertOk()->assertJsonPath('data.totals.present', 1)->assertJsonPath('data.totals.worked_hours', 8);

        $this->getJson('/api/v1/hr/reports/leave?from=2026-10-01&to=2026-10-31')
            ->assertOk()->assertJsonPath('data.approved_by_type.0.days', '2.0');
    }

    public function test_reports_require_employees_view(): void
    {
        [$user, $company] = $this->userWith(['leave.request']);

        $this->switchTo($user, $company)->getJson('/api/v1/hr/reports/headcount')->assertForbidden();
    }

    public function test_designation_writes_require_employees_update(): void
    {
        [$viewer, $company] = $this->userWith(['employees.view']);
        $editor = User::factory()->inCompany($company, ['employees.view', 'employees.update'])->create();

        $this->switchTo($viewer, $company)->postJson('/api/v1/designations', ['title' => 'Engineer', 'code' => 'ENG'])->assertForbidden();
        $this->assertDatabaseMissing('designations', ['code' => 'ENG']);

        $this->switchTo($editor, $company)->postJson('/api/v1/designations', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'code']);
        $this->postJson('/api/v1/designations', ['title' => 'Engineer', 'code' => 'ENG', 'level' => 3])
            ->assertCreated()->assertJsonPath('data.level', 3);
    }

    public function test_performance_review_submit_and_acknowledge_by_the_employee(): void
    {
        [$manager, $company] = $this->userWith(['performance.view', 'performance.manage']);
        $user = User::factory()->inCompany($company)->create();
        $employee = Employee::factory()->for($company)->forUser($user)->create();
        $review = PerformanceReview::factory()->create([
            'company_id' => $company->id, 'employee_id' => $employee->id, 'reviewer_id' => $manager->id,
        ]);

        $this->switchTo($user, $company)->postJson("/api/v1/performance-reviews/{$review->id}/acknowledge")->assertStatus(409);

        $this->switchTo($manager, $company)->postJson("/api/v1/performance-reviews/{$review->id}/submit")
            ->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->postJson("/api/v1/performance-reviews/{$review->id}/acknowledge")->assertForbidden();

        $this->switchTo($user, $company)->postJson("/api/v1/performance-reviews/{$review->id}/acknowledge")
            ->assertOk()->assertJsonPath('data.status', 'acknowledged');

        $this->getJson('/api/v1/performance-reviews')->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseHas('audit_logs', ['action' => 'performance.reviews.acknowledged', 'entity_id' => $review->id]);
    }

    public function test_cross_tenant_performance_review_is_404(): void
    {
        [$manager, $company] = $this->userWith(['performance.view', 'performance.manage']);
        $foreign = PerformanceReview::factory()->create();

        $this->switchTo($manager, $company)->getJson("/api/v1/performance-reviews/{$foreign->id}")->assertNotFound();
        $this->deleteJson("/api/v1/performance-reviews/{$foreign->id}")->assertNotFound();
        $this->assertDatabaseHas('performance_reviews', ['id' => $foreign->id]);
    }
}
