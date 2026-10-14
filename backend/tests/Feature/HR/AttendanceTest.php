<?php

namespace Tests\Feature\HR;

use App\Models\Company;
use App\Models\HR\AttendanceRecord;
use App\Models\HR\Employee;
use App\Models\HR\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Feature\HR\Concerns\SwitchesUsers;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use SwitchesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-14 09:25:00'));
    }

    public function test_self_check_in_computes_late_minutes_against_the_shift(): void
    {
        $company = Company::factory()->create();
        Shift::factory()->for($company)->default()->create(['start_time' => '09:00:00', 'end_time' => '18:00:00', 'break_minutes' => 60]);
        $user = User::factory()->inCompany($company)->create();
        $employee = Employee::factory()->for($company)->forUser($user)->create();

        $this->switchTo($user, $company)->postJson('/api/v1/attendance/check-in')
            ->assertCreated()
            ->assertJsonPath('data.late_minutes', 25)
            ->assertJsonPath('data.status', 'late')
            ->assertJsonPath('data.source', 'self');

        $this->postJson('/api/v1/attendance/check-in')->assertStatus(409);

        $this->travelTo(Carbon::parse('2026-10-14 18:25:00'));

        $this->postJson('/api/v1/attendance/check-out')
            ->assertOk()
            ->assertJsonPath('data.worked_minutes', 480);

        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $employee->id, 'company_id' => $company->id, 'late_minutes' => 25, 'worked_minutes' => 480,
        ]);
    }

    public function test_check_in_without_employee_record_returns_422(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->inCompany($company)->create();

        $this->switchTo($user, $company)->postJson('/api/v1/attendance/check-in')
            ->assertUnprocessable()->assertJsonValidationErrors(['employee']);
    }

    public function test_store_upserts_one_record_per_employee_per_day(): void
    {
        [$clerk, $company] = $this->userWith(['attendance.view', 'attendance.manage']);
        Shift::factory()->for($company)->default()->create();
        $employee = Employee::factory()->for($company)->create();

        $this->switchTo($clerk, $company)->postJson('/api/v1/attendance', [
            'employee_id' => $employee->id, 'date' => '2026-10-13', 'status' => 'present',
            'check_in' => '2026-10-13T09:10:00Z', 'check_out' => '2026-10-13T18:00:00Z',
        ])->assertCreated()
            ->assertJsonPath('data.late_minutes', 10)
            ->assertJsonPath('data.worked_minutes', 470);

        $this->postJson('/api/v1/attendance', [
            'employee_id' => $employee->id, 'date' => '2026-10-13', 'status' => 'absent', 'check_in' => null, 'check_out' => null,
        ])->assertOk()->assertJsonPath('data.status', 'absent')->assertJsonPath('data.worked_minutes', 0);

        $this->assertSame(1, AttendanceRecord::withoutTenancy()->where('employee_id', $employee->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.recorded']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.updated']);
    }

    public function test_bulk_upserts_records_for_a_date(): void
    {
        [$clerk, $company] = $this->userWith(['attendance.view', 'attendance.manage']);
        [$a, $b] = Employee::factory()->for($company)->count(2)->create()->all();
        AttendanceRecord::factory()->create(['company_id' => $company->id, 'employee_id' => $a->id, 'date' => '2026-10-13', 'status' => 'absent']);

        $this->switchTo($clerk, $company)->postJson('/api/v1/attendance/bulk', [
            'date' => '2026-10-13',
            'records' => [
                ['employee_id' => $a->id, 'status' => 'present'],
                ['employee_id' => $b->id, 'status' => 'half_day'],
            ],
        ])->assertOk()->assertJsonPath('data.written', 2);

        $this->assertSame(2, AttendanceRecord::withoutTenancy()->whereDate('date', '2026-10-13')->count());
        $this->assertDatabaseHas('attendance_records', ['employee_id' => $a->id, 'status' => 'present']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.bulk_recorded']);
    }

    public function test_empty_payload_returns_422(): void
    {
        [$clerk, $company] = $this->userWith(['attendance.manage']);

        $this->switchTo($clerk, $company)->postJson('/api/v1/attendance', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['employee_id', 'date', 'status']);

        $this->postJson('/api/v1/attendance/bulk', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['date', 'records']);
    }

    public function test_viewer_without_manage_gets_403_and_nothing_is_written(): void
    {
        [$viewer, $company] = $this->userWith(['attendance.view']);
        $employee = Employee::factory()->for($company)->create();

        $this->switchTo($viewer, $company)->postJson('/api/v1/attendance', [
            'employee_id' => $employee->id, 'date' => '2026-10-13', 'status' => 'present',
        ])->assertForbidden();

        $this->postJson('/api/v1/attendance/bulk', [
            'date' => '2026-10-13', 'records' => [['employee_id' => $employee->id, 'status' => 'present']],
        ])->assertForbidden();

        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_cross_tenant_attendance_is_404(): void
    {
        [$clerk, $company] = $this->userWith(['attendance.view', 'attendance.manage']);
        $foreign = AttendanceRecord::factory()->create(['status' => 'present']);

        $this->switchTo($clerk, $company)->getJson("/api/v1/attendance/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/attendance/{$foreign->id}", ['status' => 'absent'])->assertNotFound();
        $this->deleteJson("/api/v1/attendance/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseHas('attendance_records', ['id' => $foreign->id, 'status' => 'present']);
    }

    public function test_summary_aggregates_per_employee(): void
    {
        [$viewer, $company] = $this->userWith(['attendance.view']);
        $employee = Employee::factory()->for($company)->create();
        AttendanceRecord::factory()->create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-10-12', 'worked_minutes' => 480]);
        AttendanceRecord::factory()->create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-10-13', 'status' => 'late', 'worked_minutes' => 450, 'late_minutes' => 30]);
        AttendanceRecord::factory()->absent()->create(['company_id' => $company->id, 'employee_id' => $employee->id, 'date' => '2026-10-14']);

        $this->switchTo($viewer, $company)->getJson('/api/v1/attendance/summary?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.present_days', 1)
            ->assertJsonPath('data.0.late_days', 1)
            ->assertJsonPath('data.0.absent_days', 1)
            ->assertJsonPath('data.0.worked_minutes', 930)
            ->assertJsonPath('data.0.late_minutes', 30);
    }
}
