<?php

namespace App\Services\Payroll;

use App\Enums\Payroll\PayComponentKind;
use App\Models\Payroll\PayrollRun;
use App\Models\Payroll\Payslip;
use App\Models\Payroll\PayslipLine;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Payslip PDF and run exports (CSV / XLSX). Every download is audited.
 * Spreadsheet text cells are guarded against formula injection.
 */
class PayslipDocuments
{
    /** @var array<int, string> */
    public const EXPORT_COLUMNS = [
        'Payslip', 'Employee code', 'Employee', 'Department', 'Designation', 'Currency', 'Working days', 'Paid days',
        'Base salary', 'Earned base', 'Gross', 'Taxable', 'Tax', 'Total deductions', 'Employer contributions', 'Net',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenant,
    ) {}

    public function pdf(Payslip $payslip): Response
    {
        $payslip->loadMissing(['lines', 'run']);
        $company = $this->tenant->company();
        $logo = null;

        if ($company?->logo_path && Storage::disk('public')->exists($company->logo_path)) {
            $mime = Storage::disk('public')->mimeType($company->logo_path) ?: 'image/png';
            $logo = 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk('public')->get($company->logo_path));
        }

        $this->audit->log('payroll.payslip_downloaded', $payslip, null, null, ['format' => 'pdf'], entityLabel: $payslip->number ?? $payslip->employee_code);

        $lines = $payslip->lines;

        return Pdf::loadView('pdf.payroll.payslip', [
            'payslip' => $payslip,
            'run' => $payslip->run,
            'company' => $company,
            'logo' => $logo,
            'earnings' => $lines->filter(fn (PayslipLine $l) => $l->kind === PayComponentKind::Earning)->values(),
            'deductions' => $lines->filter(fn (PayslipLine $l) => $l->kind === PayComponentKind::Deduction)->values(),
            'employer' => $lines->filter(fn (PayslipLine $l) => $l->kind === PayComponentKind::EmployerContribution)->values(),
        ])->setPaper('a4')->download(($payslip->number ?? 'payslip-'.$payslip->id).'.pdf');
    }

    public function export(PayrollRun $run, string $format): StreamedResponse
    {
        $rows = $this->rows($run);
        $this->audit->log('payroll.exported', $run, null, null, ['format' => $format, 'rows' => count($rows)]);
        $filename = "{$run->number}.{$format}";

        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($rows): void {
                $sheet = new Spreadsheet;
                $sheet->getActiveSheet()->setTitle('Payroll')->fromArray([self::EXPORT_COLUMNS, ...$rows], null, 'A1', true);
                (new Xlsx($sheet))->save('php://output');
            }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, self::EXPORT_COLUMNS, ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<list<string|null>>
     */
    public function rows(PayrollRun $run): array
    {
        return $run->payslips()->orderBy('employee_code')->orderBy('id')->get()
            ->map(fn (Payslip $p) => [
                $p->number,
                $this->text($p->employee_code),
                $this->text($p->employee_name),
                $this->text($p->department_name),
                $this->text($p->designation_title),
                $p->currency,
                (string) $p->working_days,
                (string) $p->paid_days,
                (string) $p->base_salary,
                (string) $p->earned_base,
                (string) $p->gross,
                (string) $p->taxable,
                (string) $p->tax,
                (string) $p->total_deductions,
                (string) $p->employer_contributions,
                (string) $p->net,
            ])->values()->all();
    }

    /** Neutralise spreadsheet formula injection in free-text cells. */
    private function text(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
