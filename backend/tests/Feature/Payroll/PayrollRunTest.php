<?php

namespace Tests\Feature\Payroll;

use App\Models\Finance\Account;
use App\Models\Finance\BankAccount;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalLine;
use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveType;
use App\Models\Payroll\PayrollRun;
use App\Models\Payroll\Payslip;
use App\Models\Payroll\TaxTable;
use App\Notifications\Payroll\PayslipAvailable;
use App\Services\Finance\ReportService;
use App\Support\Money\Money;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Payroll\Concerns\InteractsWithPayroll;
use Tests\TestCase;

class PayrollRunTest extends TestCase
{
    use InteractsWithPayroll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPayroll();
    }

    public function test_calculates_the_reference_case_exactly(): void
    {
        [$user, $company] = $this->payrollUser();
        $employee = $this->referenceEmployee($company);

        $id = $this->calculatedRun($user, $company);

        $this->getJson("/api/v1/payroll-runs/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'calculated')
            ->assertJsonPath('data.totals.gross', '5500.0000')
            ->assertJsonPath('data.totals.tax', '500.0000')
            ->assertJsonPath('data.totals.deductions', '1000.0000')
            ->assertJsonPath('data.totals.employer_contributions', '250.0000')
            ->assertJsonPath('data.totals.net', '4500.0000')
            ->assertJsonPath('data.totals.employee_count', 1);

        $payslip = Payslip::query()->where('employee_id', $employee->id)->firstOrFail();

        $this->getJson("/api/v1/payslips/{$payslip->id}")
            ->assertOk()
            ->assertJsonPath('data.working_days', '22.0')
            ->assertJsonPath('data.paid_days', '22.0')
            ->assertJsonPath('data.earned_base', '5000.0000')
            ->assertJsonPath('data.gross', '5500.0000')
            ->assertJsonPath('data.taxable', '5500.0000')
            ->assertJsonPath('data.tax', '500.0000')
            ->assertJsonPath('data.total_deductions', '1000.0000')
            ->assertJsonPath('data.employer_contributions', '250.0000')
            ->assertJsonPath('data.net', '4500.0000')
            ->assertJsonPath('data.warnings', [])
            ->assertJsonPath('data.lines.0.code', 'BASE')
            ->assertJsonPath('data.lines.1.code', 'HOUSING')
            ->assertJsonPath('data.lines.1.amount', '500.0000')
            ->assertJsonPath('data.lines.2.code', 'PENSION')
            ->assertJsonPath('data.lines.2.amount', '500.0000')
            ->assertJsonPath('data.lines.3.code', 'PENSION_ER')
            ->assertJsonPath('data.lines.3.amount', '250.0000')
            ->assertJsonPath('data.lines.4.code', 'TAX')
            ->assertJsonPath('data.lines.4.amount', '500.0000');
    }

    public function test_recalculation_is_deterministic_and_picks_up_salary_changes(): void
    {
        [$user, $company] = $this->payrollUser();
        $employee = $this->referenceEmployee($company);
        $id = $this->calculatedRun($user, $company);

        $this->postJson("/api/v1/payroll-runs/{$id}/calculate")->assertOk()->assertJsonPath('data.totals.net', '4500.0000');
        $this->assertSame(5, Payslip::query()->where('employee_id', $employee->id)->firstOrFail()->lines()->count());

        // A raise effective inside the period replaces the earlier salary.
        $this->postJson("/api/v1/employees/{$employee->id}/salaries", [
            'base_salary' => '6000', 'currency' => 'USD', 'effective_from' => '2026-09-01',
        ])->assertCreated();

        // The new salary row has no component overrides: gross = taxable = 6000; tax 0 + 400 + 200 = 600; net 5400.
        $this->postJson("/api/v1/payroll-runs/{$id}/calculate")
            ->assertOk()
            ->assertJsonPath('data.totals.gross', '6000.0000')
            ->assertJsonPath('data.totals.tax', '600.0000')
            ->assertJsonPath('data.totals.net', '5400.0000');

        $this->assertDatabaseHas('employee_salaries', ['employee_id' => $employee->id, 'effective_from' => '2026-01-01', 'effective_to' => '2026-08-31']);
    }

    public function test_annual_tax_tables_are_annualised(): void
    {
        [$user, $company] = $this->payrollUser();
        $components = $this->referenceComponents($company);
        $this->salary($this->employee($company), '5000', [[$components['allowance']]]);
        TaxTable::factory()
            ->withBrackets([['0', '12000', '0'], ['12000', '60000', '10'], ['60000', null, '20']])
            ->create(['company_id' => $company->id, 'period' => 'annual', 'effective_from' => '2026-01-01']);

        // 5500 × 12 = 66000 → 4800 + 1200 = 6000 / 12 = 500.
        $id = $this->calculatedRun($user, $company);
        $this->getJson("/api/v1/payroll-runs/{$id}")->assertJsonPath('data.totals.tax', '500.0000');
    }

    public function test_unpaid_leave_prorates_the_base_salary(): void
    {
        [$user, $company] = $this->payrollUser();
        $employee = $this->employee($company);
        $this->salary($employee, '5000');
        $unpaid = LeaveType::factory()->unpaid()->create(['company_id' => $company->id]);
        $paid = LeaveType::factory()->create(['company_id' => $company->id]);

        LeaveRequest::factory()->approved()->create([
            'company_id' => $company->id, 'employee_id' => $employee->id, 'leave_type_id' => $unpaid->id,
            'start_date' => '2026-09-14', 'end_date' => '2026-09-15', 'days' => '2.0',
        ]);
        // Paid leave and pending unpaid leave do not reduce pay.
        LeaveRequest::factory()->approved()->create([
            'company_id' => $company->id, 'employee_id' => $employee->id, 'leave_type_id' => $paid->id,
            'start_date' => '2026-09-21', 'end_date' => '2026-09-21', 'days' => '1.0',
        ]);
        LeaveRequest::factory()->create([
            'company_id' => $company->id, 'employee_id' => $employee->id, 'leave_type_id' => $unpaid->id,
            'start_date' => '2026-09-22', 'end_date' => '2026-09-22', 'days' => '1.0',
        ]);

        $id = $this->calculatedRun($user, $company);
        $payslip = Payslip::query()->where('payroll_run_id', $id)->firstOrFail();

        // Sept 2026 has 22 weekdays; 2 unpaid → 5000 × 20 / 22 = 4545.4545… → 4545.45
        $this->getJson("/api/v1/payslips/{$payslip->id}")
            ->assertOk()
            ->assertJsonPath('data.working_days', '22.0')
            ->assertJsonPath('data.unpaid_days', '2.0')
            ->assertJsonPath('data.paid_days', '20.0')
            ->assertJsonPath('data.earned_base', '4545.4500')
            ->assertJsonPath('data.gross', '4545.4500')
            ->assertJsonPath('data.tax', '0.0000')
            ->assertJsonPath('data.net', '4545.4500')
            ->assertJsonPath('data.warnings', ['no_tax_table']);
    }

    public function test_employees_without_a_salary_are_skipped_and_reported(): void
    {
        [$user, $company] = $this->payrollUser();
        $paid = $this->employee($company);
        $this->salary($paid);
        $noSalary = $this->employee($company, ['employee_code' => 'EMP-NOSAL']);
        $this->employee($company, ['status' => 'terminated', 'terminated_at' => '2026-08-15']);

        $this->actAs($user, $company)->postJson('/api/v1/payroll-runs', $this->runPayload())
            ->assertCreated()
            ->assertJsonPath('data.number', 'PAYRUN-2026-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.totals.employee_count', 1)
            ->assertJsonCount(1, 'meta.skipped')
            ->assertJsonPath('meta.skipped.0.employee_id', $noSalary->id)
            ->assertJsonPath('meta.skipped.0.reason', 'no_salary');

        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'payroll.run_created']);
    }

    public function test_only_one_live_run_per_period_and_scope(): void
    {
        [$user, $company] = $this->payrollUser();
        $id = $this->createRun($user, $company);

        $this->postJson('/api/v1/payroll-runs', $this->runPayload())->assertStatus(409)->assertJsonPath('code', 'payroll.duplicate_run');

        $this->postJson("/api/v1/payroll-runs/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson('/api/v1/payroll-runs', $this->runPayload())->assertCreated();
    }

    public function test_only_full_calendar_months_are_accepted(): void
    {
        [$user, $company] = $this->payrollUser();

        $this->actAs($user, $company)->postJson('/api/v1/payroll-runs', $this->runPayload('2026-09', ['period_end' => '2026-09-15']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_end']);
    }

    public function test_empty_run_payload_is_rejected(): void
    {
        [$user, $company] = $this->payrollUser();

        $this->actAs($user, $company)->postJson('/api/v1/payroll-runs', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'period_start', 'period_end', 'payment_date']);

        $this->assertDatabaseCount('payroll_runs', 0);
    }

    public function test_creator_cannot_approve_their_own_run(): void
    {
        [$user, $company] = $this->payrollUser();
        $this->referenceEmployee($company);
        $id = $this->calculatedRun($user, $company);

        $this->postJson("/api/v1/payroll-runs/{$id}/approve")
            ->assertForbidden()
            ->assertJsonPath('code', 'payroll.self_approval');

        $this->assertSame('calculated', PayrollRun::query()->findOrFail($id)->status->value);
    }

    public function test_processing_requires_payroll_process_permission(): void
    {
        [$user, $company] = $this->payrollUser(['payroll.view', 'payroll.manage']);

        $this->actAs($user, $company)->postJson('/api/v1/payroll-runs', $this->runPayload())->assertForbidden();
        $this->assertDatabaseCount('payroll_runs', 0);
    }

    public function test_finalize_posts_a_balanced_summarised_journal_and_notifies_without_amounts(): void
    {
        Notification::fake();
        [$user, $company] = $this->payrollUser();
        $employeeUser = $this->colleague($company, ['payslips.view.own']);
        $employee = $this->referenceEmployee($company, ['user_id' => $employeeUser->id, 'email' => $employeeUser->email]);

        $id = $this->finalizedRun($user, $company);
        $run = PayrollRun::query()->findOrFail($id);

        $this->assertSame('finalized', $run->status->value);
        $this->assertNotNull($run->journal_entry_id);

        $entry = JournalEntry::forCompany($company->id)->findOrFail($run->journal_entry_id);
        $this->assertSame('payroll_run', $entry->source_type);
        $this->assertSame('posted', $entry->status->value);
        $this->assertSame('2026-09-30', $entry->entry_date->toDateString());

        $this->assertSame(
            ['1:6000' => '5500.0000', '1:6010' => '250.0000', '2:2100' => '500.0000', '2:2200' => '4500.0000', '2:2300' => '750.0000'],
            $this->journalSummary($entry->id),
        );

        $trial = app(ReportService::class)->trialBalance($company, '2026-12-31');
        $this->assertTrue($trial['totals']['balanced']);

        $payslip = Payslip::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame('SLIP-2026-000001', $payslip->number);
        $this->assertSame('finalized', $payslip->status->value);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'payroll.run_approved']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'payroll.run_finalized']);

        Notification::assertSentTo($employeeUser, PayslipAvailable::class, function (PayslipAvailable $notification) use ($employeeUser, $payslip) {
            $payload = $notification->toDatabase($employeeUser);
            $encoded = json_encode($payload);

            return $payload['type'] === 'payslip.available'
                && $payload['url'] === "/payroll/my-payslips/{$payslip->id}"
                && ! str_contains($encoded, '4500') && ! str_contains($encoded, '4,500') && ! str_contains($encoded, '5500');
        });
        Notification::assertSentTimes(PayslipAvailable::class, 1);
    }

    public function test_finalized_run_is_immutable(): void
    {
        [$user, $company] = $this->payrollUser();
        $this->referenceEmployee($company);
        $id = $this->finalizedRun($user, $company);

        $this->postJson("/api/v1/payroll-runs/{$id}/calculate")->assertStatus(409)->assertJsonPath('code', 'payroll.invalid_state');
        $this->postJson("/api/v1/payroll-runs/{$id}/approve")->assertStatus(409);
        $this->postJson("/api/v1/payroll-runs/{$id}/finalize")->assertStatus(409);
        $this->postJson("/api/v1/payroll-runs/{$id}/cancel")->assertStatus(409);
        $this->deleteJson("/api/v1/payroll-runs/{$id}")->assertStatus(409);

        $this->assertSame(1, JournalEntry::forCompany($company->id)->where('source_type', 'payroll_run')->count());
    }

    public function test_cannot_finalize_before_approval(): void
    {
        [$user, $company] = $this->payrollUser();
        $this->referenceEmployee($company);
        $id = $this->calculatedRun($user, $company);

        $this->postJson("/api/v1/payroll-runs/{$id}/finalize")->assertStatus(409)->assertJsonPath('code', 'payroll.invalid_state');
        $this->postJson("/api/v1/payroll-runs/{$id}/pay", ['bank_account_id' => 999999, 'paid_at' => '2026-09-30'])->assertUnprocessable();
    }

    public function test_mark_paid_posts_accrued_payroll_against_the_bank(): void
    {
        [$user, $company] = $this->payrollUser();
        $this->referenceEmployee($company);
        $bank = BankAccount::factory()->create([
            'company_id' => $company->id,
            'account_id' => Account::forCompany($company->id)->where('code', '1010')->value('id'),
        ]);
        $id = $this->finalizedRun($user, $company);

        $this->postJson("/api/v1/payroll-runs/{$id}/pay", [])->assertUnprocessable()->assertJsonValidationErrors(['bank_account_id', 'paid_at']);

        $this->postJson("/api/v1/payroll-runs/{$id}/pay", ['bank_account_id' => $bank->id, 'paid_at' => '2026-10-01'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.bank_account_id', $bank->id);

        $run = PayrollRun::query()->findOrFail($id);
        $this->assertSame(['1:2200' => '4500.0000', '2:1010' => '4500.0000'], $this->journalSummary($run->payment_journal_entry_id));
        $this->assertSame('paid', Payslip::query()->where('payroll_run_id', $id)->firstOrFail()->status->value);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'action' => 'payroll.run_paid']);

        // Accrued payroll is fully cleared.
        $balance = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.company_id', $company->id)->where('accounts.code', '2200')
            ->selectRaw('SUM(journal_lines.credit) - SUM(journal_lines.debit) AS balance')->value('balance');
        $this->assertTrue(Money::isZero((string) $balance));

        $this->postJson("/api/v1/payroll-runs/{$id}/pay", ['bank_account_id' => $bank->id, 'paid_at' => '2026-10-01'])->assertStatus(409);
    }

    public function test_only_draft_runs_can_be_deleted(): void
    {
        [$user, $company] = $this->payrollUser();
        $this->referenceEmployee($company);
        $draft = $this->createRun($user, $company);
        $calculated = $this->calculatedRun($user, $company, '2026-08');

        $this->deleteJson("/api/v1/payroll-runs/{$calculated}")->assertStatus(409);
        $this->deleteJson("/api/v1/payroll-runs/{$draft}")->assertNoContent();
        $this->assertDatabaseMissing('payroll_runs', ['id' => $draft]);
        $this->assertDatabaseMissing('payslips', ['payroll_run_id' => $draft]);
    }

    public function test_another_companys_run_is_not_found(): void
    {
        [$user, $company] = $this->payrollUser();
        [$otherUser, $other] = $this->payrollUser();
        $this->referenceEmployee($other);
        $foreignId = $this->calculatedRun($otherUser, $other);
        $foreignPayslip = Payslip::forCompany($other->id)->where('payroll_run_id', $foreignId)->value('id');

        $this->actAs($user, $company)->getJson("/api/v1/payroll-runs/{$foreignId}")->assertNotFound();
        $this->getJson("/api/v1/payroll-runs/{$foreignId}/payslips")->assertNotFound();
        $this->postJson("/api/v1/payroll-runs/{$foreignId}/calculate")->assertNotFound();
        $this->postJson("/api/v1/payroll-runs/{$foreignId}/cancel")->assertNotFound();
        $this->deleteJson("/api/v1/payroll-runs/{$foreignId}")->assertNotFound();
        $this->getJson("/api/v1/payroll-runs/{$foreignId}/export")->assertNotFound();
        $this->getJson("/api/v1/payslips/{$foreignPayslip}")->assertNotFound();
        $this->getJson("/api/v1/payslips/{$foreignPayslip}/pdf")->assertNotFound();
        $this->getJson('/api/v1/payroll-runs')->assertOk()->assertJsonCount(0, 'data');

        $this->assertSame('calculated', PayrollRun::forCompany($other->id)->findOrFail($foreignId)->status->value);
    }

    /**
     * @return array<string, string> "1:code" debit / "2:code" credit → amount
     */
    private function journalSummary(int $entryId): array
    {
        $out = [];

        JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.journal_entry_id', $entryId)
            ->get(['accounts.code', 'journal_lines.debit', 'journal_lines.credit'])
            ->each(function ($line) use (&$out): void {
                $isDebit = Money::compare((string) $line->debit, '0') > 0;
                $key = ($isDebit ? '1:' : '2:').$line->code;
                $out[$key] = Money::add($out[$key] ?? Money::zero(), Money::normalize((string) ($isDebit ? $line->debit : $line->credit)));
            });

        ksort($out);

        return $out;
    }
}
