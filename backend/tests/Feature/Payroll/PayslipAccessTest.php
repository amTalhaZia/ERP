<?php

namespace Tests\Feature\Payroll;

use App\Models\AuditLog;
use App\Models\Payroll\Payslip;
use Tests\Feature\Payroll\Concerns\InteractsWithPayroll;
use Tests\TestCase;

class PayslipAccessTest extends TestCase
{
    use InteractsWithPayroll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPayroll();
    }

    public function test_reading_payslips_and_runs_requires_payroll_view(): void
    {
        [$user, $company] = $this->payrollUser();
        [$nobody] = $this->payrollUser(['employees.view', 'payroll.manage'], $company);
        $this->referenceEmployee($company);
        $id = $this->calculatedRun($user, $company);
        $payslip = Payslip::query()->where('payroll_run_id', $id)->value('id');

        $this->actAs($nobody, $company)->getJson("/api/v1/payslips/{$payslip}")->assertForbidden();
        $this->getJson("/api/v1/payslips/{$payslip}/pdf")->assertForbidden();
        $this->getJson('/api/v1/payroll-runs')->assertForbidden();
        $this->getJson("/api/v1/payroll-runs/{$id}")->assertForbidden();
        $this->getJson("/api/v1/payroll-runs/{$id}/payslips")->assertForbidden();
        $this->getJson("/api/v1/payroll-runs/{$id}/export")->assertForbidden();
        $this->getJson('/api/v1/payroll/reports/summary?from=2026-01-01&to=2026-12-31')->assertForbidden();
    }

    public function test_employees_see_only_their_own_released_payslips(): void
    {
        [$user, $company] = $this->payrollUser();
        $me = $this->colleague($company, ['payslips.view.own']);
        $components = $this->referenceComponents($company);
        $mine = $this->employee($company, ['user_id' => $me->id, 'email' => $me->email]);
        $theirs = $this->employee($company);
        $this->salary($mine, '5000', [[$components['allowance']]]);
        $this->salary($theirs, '7000');

        $this->finalizedRun($user, $company);                 // September: released
        $this->calculatedRun($user, $company, '2026-10');      // October: not released yet

        $myReleased = Payslip::query()->where('employee_id', $mine->id)->whereNotNull('number')->value('id');
        $myDraft = Payslip::query()->where('employee_id', $mine->id)->whereNull('number')->value('id');
        $theirReleased = Payslip::query()->where('employee_id', $theirs->id)->whereNotNull('number')->value('id');

        $this->actAs($me, $company)->getJson('/api/v1/my-payslips')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $myReleased)
            ->assertJsonPath('data.0.net', '5500.0000')
            ->assertJsonPath('data.0.run.period_start', '2026-09-01');

        $this->getJson("/api/v1/my-payslips/{$myReleased}")->assertOk()->assertJsonPath('data.lines.1.code', 'HOUSING');
        $this->getJson("/api/v1/my-payslips/{$myDraft}")->assertNotFound();
        $this->getJson("/api/v1/my-payslips/{$theirReleased}")->assertNotFound();
        $this->getJson("/api/v1/my-payslips/{$theirReleased}/pdf")->assertNotFound();

        // The generic endpoint allows the owner (released only) and refuses anyone else's.
        $this->getJson("/api/v1/payslips/{$myReleased}")->assertOk();
        $this->getJson("/api/v1/payslips/{$myDraft}")->assertForbidden();
        $this->getJson("/api/v1/payslips/{$theirReleased}")->assertForbidden();
        $this->getJson("/api/v1/payslips/{$theirReleased}/pdf")->assertForbidden();

        $this->getJson('/api/v1/payroll-runs')->assertForbidden();
    }

    public function test_my_payslips_requires_the_self_service_permission(): void
    {
        [$user, $company] = $this->payrollUser(['employees.view']);

        $this->actAs($user, $company)->getJson('/api/v1/my-payslips')->assertForbidden();
    }

    public function test_payslip_pdf_downloads_are_audited(): void
    {
        [$user, $company] = $this->payrollUser();
        $me = $this->colleague($company, ['payslips.view.own']);
        $this->referenceEmployee($company, ['user_id' => $me->id, 'email' => $me->email]);
        $this->finalizedRun($user, $company);
        $payslip = Payslip::query()->firstOrFail();

        $this->actAs($user, $company)->get("/api/v1/payslips/{$payslip->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actAs($me, $company)->get("/api/v1/my-payslips/{$payslip->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame(2, AuditLog::query()
            ->where('company_id', $company->id)->where('action', 'payroll.payslip_downloaded')->where('entity_id', $payslip->id)->count());
    }

    public function test_run_export_csv_and_xlsx_are_audited(): void
    {
        [$user, $company] = $this->payrollUser();
        $this->referenceEmployee($company, ['first_name' => '=cmd', 'last_name' => 'Smith']);
        $id = $this->finalizedRun($user, $company);

        $response = $this->actAs($user, $company)->get("/api/v1/payroll-runs/{$id}/export?format=csv")->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=PAYRUN-2026-000001.csv', (string) $response->headers->get('Content-Disposition'));

        $lines = preg_split('/\R/', trim($response->streamedContent()));
        $this->assertSame('Payslip,"Employee code",Employee,Department,Designation,Currency,"Working days","Paid days","Base salary","Earned base",Gross,Taxable,Tax,"Total deductions","Employer contributions",Net', $lines[0]);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString("'=cmd Smith", $lines[1]);
        $this->assertStringContainsString('4500.0000', $lines[1]);

        $xlsx = $this->get("/api/v1/payroll-runs/{$id}/export?format=xlsx")->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $xlsx->headers->get('Content-Type'));
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());

        $this->getJson("/api/v1/payroll-runs/{$id}/export?format=pdf")->assertUnprocessable();

        $this->assertSame(2, AuditLog::query()->where('company_id', $company->id)->where('action', 'payroll.exported')->count());
    }

    public function test_reports_aggregate_released_runs(): void
    {
        [$user, $company] = $this->payrollUser();
        $this->referenceEmployee($company);
        $second = $this->employee($company);
        $this->salary($second, '3000');
        $id = $this->finalizedRun($user, $company);
        $this->calculatedRun($user, $company, '2026-10'); // not released → excluded

        // Reference: net 4500. Second: base 3000, taxable 3000 → tax 200, pension n/a → net 2800.
        $this->getJson('/api/v1/payroll/reports/summary?from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonCount(1, 'data.runs')
            ->assertJsonPath('data.runs.0.id', $id)
            ->assertJsonPath('data.totals.headcount_paid', 2)
            ->assertJsonPath('data.totals.gross', '8500.0000')
            ->assertJsonPath('data.totals.net', '7300.0000')
            ->assertJsonPath('data.totals.average_net', '3650.0000')
            ->assertJsonCount(1, 'data.departments');

        $this->getJson('/api/v1/payroll/reports/summary')->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);

        $this->getJson("/api/v1/payroll/reports/components?run_id={$id}")
            ->assertOk()
            ->assertJsonPath('data.components.0.code', 'BASE')
            ->assertJsonPath('data.components.0.total', '8000.0000')
            ->assertJsonPath('data.components.0.payslips', 2);
    }
}
