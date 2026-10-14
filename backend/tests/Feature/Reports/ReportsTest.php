<?php

namespace Tests\Feature\Reports;

use App\Jobs\Reports\GenerateReportExport;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Reports\ExportJob;
use App\Models\Reports\ExportJobStatus;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\Reports\ReportExporter;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Dashboard\Concerns\BuildsInsightsData;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use BuildsInsightsData;

    private const EXPORTER = ['reports.view', 'reports.export', 'employees.view'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeClock();
    }

    /**
     * Three employees in the user's company, one in another company.
     *
     * @return array{0: User, 1: Company}
     */
    private function directory(array $permissions = self::EXPORTER): array
    {
        [$user, $company] = $this->userWith($permissions);

        $this->employee($company, ['first_name' => 'Alice', 'last_name' => 'Anders', 'employee_code' => 'E-001']);
        $this->employee($company, ['first_name' => 'Bob', 'last_name' => 'Brown', 'employee_code' => 'E-002']);
        $this->employee($company, ['first_name' => '=HYPERLINK("http://evil")', 'last_name' => 'Cell', 'employee_code' => 'E-003']);
        $this->employee(Company::factory()->create(), ['first_name' => 'Mallory', 'last_name' => 'Outsider', 'employee_code' => 'X-999']);

        return [$user, $company];
    }

    // -- Catalogue --------------------------------------------------------------

    public function test_catalogue_lists_only_reports_the_user_may_run(): void
    {
        [$user, $company] = $this->userWith(['reports.view', 'employees.view', 'attendance.view']);

        $response = $this->actAs($user, $company)->getJson('/api/v1/reports')->assertOk();
        $keys = collect($response->json('data'))->pluck('key')->all();

        $this->assertEqualsCanonicalizing(['hr.headcount', 'hr.employee_directory', 'hr.attendance_summary'], $keys);

        $headcount = collect($response->json('data'))->firstWhere('key', 'hr.headcount');
        $this->assertSame('hr', $headcount['module']);
        $this->assertSame(['csv', 'xlsx', 'pdf'], $headcount['formats']);
        $this->assertSame(['as_of', 'group_by', 'branch_id'], array_column($headcount['params'], 'name'));
        $this->assertSame('select', $headcount['params'][1]['type']);
        $this->assertNotEmpty($headcount['params'][1]['options']);
    }

    public function test_full_access_user_sees_all_eighteen_reports(): void
    {
        [$user, $company] = $this->userWith([
            'reports.view', 'reports.financial.view', 'sales_orders.view', 'purchase_orders.view', 'inventory.view',
            'employees.view', 'attendance.view', 'leave.view', 'projects.view', 'audit.view',
        ]);

        $this->actAs($user, $company)->getJson('/api/v1/reports')->assertOk()->assertJsonCount(18, 'data');
    }

    public function test_catalogue_requires_reports_view(): void
    {
        [$user, $company] = $this->userWith(['employees.view']);

        $this->actAs($user, $company)->getJson('/api/v1/reports')->assertForbidden();
    }

    // -- Preview ---------------------------------------------------------------

    public function test_preview_returns_columns_and_paginated_rows_for_the_current_tenant_only(): void
    {
        [$user, $company] = $this->directory();

        $response = $this->actAs($user, $company)->getJson('/api/v1/reports/hr.employee_directory?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.report.key', 'hr.employee_directory')
            ->assertJsonPath('data.columns.0', ['key' => 'employee_code', 'label' => 'Code', 'type' => 'text'])
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonPath('meta.total_rows', 3)
            ->assertJsonPath('meta.truncated', false)
            ->assertJsonCount(2, 'data.rows');

        $this->assertStringNotContainsString('Mallory', $response->getContent());

        $second = $this->getJson('/api/v1/reports/hr.employee_directory?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data.rows');
        $this->assertEqualsCanonicalizing(
            ['Alice Anders', 'Bob Brown', '=HYPERLINK("http://evil") Cell'],
            [...array_column($response->json('data.rows'), 'name'), ...array_column($second->json('data.rows'), 'name')],
        );
    }

    public function test_profit_and_loss_preview_matches_the_ledger(): void
    {
        [$user, $company] = $this->userWith(['reports.view', 'reports.financial.view']);
        $this->postEntry($company, '2026-10-05', '1010', '4000', '1500.00');
        $this->postEntry($company, '2026-10-06', '6100', '1010', '400.00');
        $this->postEntry(Company::factory()->create(), '2026-10-05', '1010', '4000', '777.00');

        $response = $this->actAs($user, $company)->getJson('/api/v1/reports/finance.profit_and_loss?from=2026-10-01&to=2026-10-15')
            ->assertOk()
            ->assertJsonPath('data.totals', ['name' => 'Net profit', 'amount' => '1100.0000']);

        $rows = collect($response->json('data.rows'));
        $this->assertSame('1500.0000', $rows->firstWhere('code', '4000')['amount']);
        $this->assertSame('400.0000', $rows->firstWhere('code', '6100')['amount']);
        $this->assertSame('1500.0000', $rows->firstWhere('name', 'Gross profit')['amount']);
    }

    public function test_preview_rejects_invalid_params(): void
    {
        [$user, $company] = $this->userWith(['reports.view', 'reports.financial.view']);
        $foreignBranch = Branch::factory()->create();
        $this->actAs($user, $company);

        $this->getJson('/api/v1/reports/finance.profit_and_loss?from=2026-10-10&to=2026-10-01')
            ->assertStatus(422)->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/reports/finance.trial_balance?branch_id='.$foreignBranch->id)
            ->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    public function test_preview_requires_the_reports_own_permission(): void
    {
        [$user, $company] = $this->userWith(['reports.view', 'employees.view']);
        $this->actAs($user, $company);

        $this->getJson('/api/v1/reports/finance.trial_balance')->assertForbidden();
        $this->getJson('/api/v1/reports/audit.log')->assertForbidden();
        $this->getJson('/api/v1/reports/nope.missing')->assertNotFound();
    }

    // -- Synchronous exports ------------------------------------------------------

    public function test_csv_export_streams_bom_header_rows_and_audits(): void
    {
        [$user, $company] = $this->directory();

        $response = $this->actAs($user, $company)->postJson('/api/v1/reports/hr.employee_directory/export', ['format' => 'csv'])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('attachment; filename=hr-employee_directory-20261015-120000.csv', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = preg_split('/\r?\n/', trim(substr($csv, 3)));
        $this->assertSame('Code,Name,Email,Phone,Department,Designation,Branch,"Employment type",Status,Hired', $lines[0]);
        $this->assertCount(4, $lines);
        $this->assertStringNotContainsString('Mallory', $csv);

        $audit = AuditLog::query()->where('action', 'reports.exported')->firstOrFail();
        $this->assertSame($company->id, $audit->company_id);
        $this->assertSame($user->id, $audit->actor_id);
        $this->assertEquals(['report' => 'hr.employee_directory', 'format' => 'csv', 'params' => [], 'rows' => 3], $audit->context);
    }

    public function test_csv_cells_that_look_like_formulas_are_escaped(): void
    {
        [$user, $company] = $this->directory();

        $csv = $this->actAs($user, $company)->postJson('/api/v1/reports/hr.employee_directory/export', ['format' => 'csv'])
            ->assertOk()->streamedContent();

        $this->assertStringContainsString("\"'=HYPERLINK(\"\"http://evil\"\") Cell\"", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);

        $this->assertSame("'=1+1", ReportExporter::sanitizeCell('=1+1'));
        $this->assertSame("'+cmd", ReportExporter::sanitizeCell('+cmd'));
        $this->assertSame("'-2+3", ReportExporter::sanitizeCell('-2+3'));
        $this->assertSame("'@SUM(A1)", ReportExporter::sanitizeCell('@SUM(A1)'));
        $this->assertSame('-150.0000', ReportExporter::sanitizeCell('-150.0000'), 'negative amounts stay numeric');
        $this->assertSame('plain', ReportExporter::sanitizeCell('plain'));
    }

    public function test_xlsx_and_pdf_exports_have_the_right_content_types(): void
    {
        [$user, $company] = $this->directory();
        $this->actAs($user, $company);

        $xlsx = $this->postJson('/api/v1/reports/hr.employee_directory/export', ['format' => 'xlsx'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());

        $pdf = $this->postJson('/api/v1/reports/hr.headcount/export', ['format' => 'pdf', 'params' => ['group_by' => 'department']])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());

        $this->assertSame(2, AuditLog::query()->where('action', 'reports.exported')->count());
    }

    public function test_export_validates_format_and_params(): void
    {
        [$user, $company] = $this->directory();
        $this->actAs($user, $company);

        $this->postJson('/api/v1/reports/hr.employee_directory/export', ['format' => 'docx'])
            ->assertStatus(422)->assertJsonValidationErrors('format');
        $this->postJson('/api/v1/reports/hr.employee_directory/export', ['format' => 'csv', 'params' => ['status' => 'bogus']])
            ->assertStatus(422)->assertJsonValidationErrors('params.status');
    }

    public function test_export_requires_reports_export_and_the_report_permission(): void
    {
        [$viewer, $company] = $this->directory(['reports.view', 'employees.view']);
        $this->actAs($viewer, $company)->postJson('/api/v1/reports/hr.employee_directory/export', ['format' => 'csv'])->assertForbidden();

        [$exporter] = $this->userWith(['reports.export', 'reports.view'], $company);
        $this->actAs($exporter, $company)->postJson('/api/v1/reports/hr.employee_directory/export', ['format' => 'csv'])->assertForbidden();

        $this->assertSame(0, AuditLog::query()->where('action', 'reports.exported')->count());
    }

    // -- Queued exports -------------------------------------------------------------

    public function test_large_export_is_queued_generated_notified_and_downloadable_only_by_its_owner(): void
    {
        config(['erp.reports.sync_row_limit' => 2]);
        Queue::fake();
        Storage::fake('local');
        Notification::fake();

        [$user, $company] = $this->directory();

        $response = $this->actAs($user, $company)->postJson('/api/v1/reports/hr.employee_directory/export', ['format' => 'csv', 'params' => ['status' => 'active']])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.report_key', 'hr.employee_directory')
            ->assertJsonPath('data.download_url', null);

        $id = $response->json('data.id');
        Queue::assertPushed(GenerateReportExport::class, fn (GenerateReportExport $job) => $job->exportJobId === $id);
        $this->assertSame(0, AuditLog::query()->where('action', 'reports.exported')->count());

        $this->app->call([new GenerateReportExport($id), 'handle']);

        $job = ExportJob::withoutTenancy()->findOrFail($id);
        $this->assertSame(ExportJobStatus::Completed, $job->status);
        $this->assertSame(3, $job->row_count);
        $this->assertSame(['status' => 'active'], $job->params);
        $this->assertTrue($job->expires_at->equalTo(now()->addDays(config('erp.retention_days.export_files'))));
        Storage::disk('local')->assertExists($job->file_path);

        $contents = Storage::disk('local')->get($job->file_path);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contents);
        $this->assertStringContainsString('Alice Anders', $contents);
        $this->assertStringNotContainsString('Mallory', $contents);

        Notification::assertSentTo($user, GenericNotification::class, fn (GenericNotification $n) => $n->type() === 'report.ready' && str_ends_with((string) $n->url(), '/reports/exports'));

        $audit = AuditLog::query()->where('action', 'reports.exported')->firstOrFail();
        $this->assertSame($user->id, $audit->actor_id);
        $this->assertSame(3, $audit->context['rows']);
        $this->assertSame($id, $audit->context['export_job_id']);

        // Owner: listed and downloadable.
        $this->actAs($user, $company)->getJson('/api/v1/report-exports')
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.download_url', '/api/v1/report-exports/'.$id.'/download');
        $download = $this->get('/api/v1/report-exports/'.$id.'/download')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Alice Anders', $download->streamedContent());

        // Colleague in the same company: not found.
        [$colleague] = $this->userWith(self::EXPORTER, $company);
        $this->actAs($colleague, $company)->get('/api/v1/report-exports/'.$id.'/download')->assertNotFound();
        $this->getJson('/api/v1/report-exports')->assertOk()->assertJsonCount(0, 'data');

        // The owner acting in another company: not found either.
        $other = Company::factory()->create();
        $this->grantInCompany($user, $other, self::EXPORTER);
        $this->actAs($user, $other)->get('/api/v1/report-exports/'.$id.'/download')->assertNotFound();
    }

    public function test_expired_or_unfinished_exports_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        [$user, $company] = $this->userWith(self::EXPORTER);

        $queued = ExportJob::withoutTenancy()->create([
            'company_id' => $company->id, 'user_id' => $user->id, 'report_key' => 'hr.headcount', 'format' => 'csv',
            'status' => ExportJobStatus::Queued, 'expires_at' => now()->addDay(),
        ]);
        Storage::disk('local')->put('exports/old.csv', 'x');
        $expired = ExportJob::withoutTenancy()->create([
            'company_id' => $company->id, 'user_id' => $user->id, 'report_key' => 'hr.headcount', 'format' => 'csv',
            'status' => ExportJobStatus::Completed, 'file_path' => 'exports/old.csv', 'expires_at' => now()->subMinute(),
        ]);

        $this->actAs($user, $company);
        $this->get('/api/v1/report-exports/'.$queued->id.'/download')->assertStatus(409);
        $this->get('/api/v1/report-exports/'.$expired->id.'/download')->assertStatus(410);
    }

    public function test_queued_job_fails_cleanly_when_the_user_lost_the_permission(): void
    {
        Storage::fake('local');
        [$user, $company] = $this->userWith(['reports.view', 'employees.view']);

        $job = ExportJob::withoutTenancy()->create([
            'company_id' => $company->id, 'user_id' => $user->id, 'report_key' => 'hr.employee_directory', 'format' => 'csv',
            'status' => ExportJobStatus::Queued, 'expires_at' => now()->addDay(),
        ]);

        $this->app->call([new GenerateReportExport($job->id), 'handle']);

        $job->refresh();
        $this->assertSame(ExportJobStatus::Failed, $job->status);
        $this->assertNull($job->file_path);
        $this->assertSame(0, AuditLog::query()->where('action', 'reports.exported')->count());
    }

    // -- Every definition runs ----------------------------------------------------------

    public function test_every_report_previews_without_errors_on_an_empty_company(): void
    {
        [$user, $company] = $this->userWith([
            'reports.view', 'reports.financial.view', 'sales_orders.view', 'purchase_orders.view', 'inventory.view',
            'employees.view', 'attendance.view', 'leave.view', 'projects.view', 'audit.view',
        ]);
        $this->withLedger($company);
        $this->actAs($user, $company);

        foreach ($this->getJson('/api/v1/reports')->json('data') as $report) {
            $this->getJson('/api/v1/reports/'.$report['key'])
                ->assertOk()
                ->assertJsonStructure(['data' => ['report', 'columns', 'rows', 'totals'], 'meta' => ['pagination', 'total_rows']]);
        }
    }
}
