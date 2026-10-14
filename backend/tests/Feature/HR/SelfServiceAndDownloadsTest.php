<?php

namespace Tests\Feature\HR;

use App\Models\Company;
use App\Models\Department;
use App\Models\HR\AttendanceRecord;
use App\Models\HR\Employee;
use App\Models\HR\EmploymentContract;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\HR\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HR\Concerns\SwitchesUsers;
use Tests\TestCase;

class SelfServiceAndDownloadsTest extends TestCase
{
    use SwitchesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-14 10:00:00'));
    }

    public function test_employees_me_returns_own_record_without_any_permission(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->inCompany($company)->create();
        $employee = Employee::factory()->for($company)->forUser($user)->create(['notes' => 'Internal HR note']);

        $this->switchTo($user, $company)->getJson('/api/v1/employees/me')
            ->assertOk()
            ->assertJsonPath('data.id', $employee->id)
            ->assertJsonPath('data.employee_code', $employee->employee_code)
            ->assertJsonMissingPath('data.notes')
            ->assertJsonMissingPath('data.sensitive');

        $stranger = User::factory()->inCompany($company)->create();
        $this->switchTo($stranger, $company)->getJson('/api/v1/employees/me')
            ->assertNotFound()
            ->assertJsonPath('code', 'hr.no_employee_record');
    }

    public function test_attendance_today_returns_own_record_and_shift_in_company_timezone(): void
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Karachi']);
        Shift::factory()->for($company)->default()->create(['name' => 'Day', 'start_time' => '09:00:00', 'end_time' => '18:00:00']);
        $user = User::factory()->inCompany($company)->create();
        $employee = Employee::factory()->for($company)->forUser($user)->create();
        // 21:30 UTC on the 14th is already the 15th in Karachi (UTC+5).
        $this->travelTo(Carbon::parse('2026-10-14 21:30:00'));

        $this->switchTo($user, $company)->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-15')
            ->assertJsonPath('data.record', null)
            ->assertJsonPath('data.shift.name', 'Day')
            ->assertJsonPath('data.can_check_in', true);

        $record = AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'date' => '2026-10-15', 'check_out' => null]);

        $this->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.record.id', $record->id)
            ->assertJsonPath('data.can_check_in', false)
            ->assertJsonPath('data.can_check_out', true);
    }

    public function test_leave_attachment_download_is_limited_to_owner_and_leave_viewers_and_audited(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $owner = User::factory()->inCompany($company, ['leave.request'])->create();
        $employee = Employee::factory()->for($company)->forUser($owner)->create();
        Storage::disk('local')->put('companies/x/leave/note.pdf', '%PDF-1.4 test');
        $request = LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => LeaveType::factory()->for($company)->create()->id,
            'attachment_path' => 'companies/x/leave/note.pdf',
        ]);
        $url = "/api/v1/leave-requests/{$request->id}/attachment";

        $this->switchTo($owner, $company)->get($url)->assertOk()->assertDownload("leave-request-{$request->id}.pdf");
        $this->assertDatabaseHas('audit_logs', ['action' => 'leave.requests.attachment_downloaded', 'entity_id' => $request->id]);

        $approver = User::factory()->inCompany($company, ['leave.approve'])->create();
        $this->switchTo($approver, $company)->get($url)->assertOk();

        $colleague = User::factory()->inCompany($company, ['leave.request'])->create();
        $this->switchTo($colleague, $company)->getJson($url)->assertForbidden();

        $outsiderCompany = Company::factory()->create();
        $outsider = User::factory()->inCompany($outsiderCompany, ['leave.view'])->create();
        $this->switchTo($outsider, $outsiderCompany)->getJson($url)->assertNotFound();
    }

    public function test_contract_document_requires_employee_documents_permission(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $employee = Employee::factory()->for($company)->create();
        Storage::disk('local')->put('companies/x/contracts/c.pdf', '%PDF-1.4 contract');
        $contract = EmploymentContract::factory()->create(['employee_id' => $employee->id, 'document_path' => 'companies/x/contracts/c.pdf']);
        $url = "/api/v1/employment-contracts/{$contract->id}/document";

        $viewer = User::factory()->inCompany($company, ['employees.view'])->create();
        $this->switchTo($viewer, $company)->getJson($url)->assertForbidden();

        $hr = User::factory()->inCompany($company, ['employees.view', 'employees.documents.view'])->create();
        $this->switchTo($hr, $company)->get($url)->assertOk()->assertDownload("employment-contract-{$contract->id}.pdf");
        $this->assertDatabaseHas('audit_logs', ['action' => 'employment_contracts.document_downloaded', 'entity_id' => $contract->id]);

        $contract->forceFill(['document_path' => null])->save();
        $this->get($url)->assertNotFound();
    }

    public function test_attendance_and_leave_lists_accept_filter_department_id(): void
    {
        $company = Company::factory()->create();
        $sales = Department::factory()->create(['company_id' => $company->id]);
        $ops = Department::factory()->create(['company_id' => $company->id]);
        $inSales = Employee::factory()->for($company)->create(['department_id' => $sales->id]);
        $inOps = Employee::factory()->for($company)->create(['department_id' => $ops->id]);
        AttendanceRecord::factory()->create(['employee_id' => $inSales->id]);
        AttendanceRecord::factory()->create(['employee_id' => $inOps->id]);
        $type = LeaveType::factory()->for($company)->create();
        LeaveRequest::factory()->create(['employee_id' => $inSales->id, 'leave_type_id' => $type->id]);
        LeaveRequest::factory()->create(['employee_id' => $inOps->id, 'leave_type_id' => $type->id]);
        $hr = User::factory()->inCompany($company, ['attendance.view', 'leave.view'])->create();
        $this->switchTo($hr, $company);

        foreach (['/api/v1/attendance', '/api/v1/leave-requests'] as $list) {
            $this->getJson("{$list}?filter[department_id]={$sales->id}")
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.employee_id', $inSales->id);
            $this->getJson("{$list}?department_id={$ops->id}")
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.employee_id', $inOps->id);
        }
    }
}
