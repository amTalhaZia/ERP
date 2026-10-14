<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Services\Reports\Definitions\ReportDefinition;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders a report to CSV / XLSX / PDF into any writable stream (php://output
 * for synchronous downloads, php://temp for queued exports).
 *
 *  - CSV: UTF-8 BOM, header row, rows, optional totals row; every text cell
 *    beginning with = + - @ (or tab / CR) is prefixed with `'` so spreadsheet
 *    apps never evaluate it as a formula (numeric values are left intact).
 *  - XLSX: typed cells (money/number numeric, everything else explicit
 *    strings — never formulas).
 *  - PDF: a clean table with company, report, params and generated at/by.
 */
class ReportExporter
{
    public const PDF_MAX_ROWS = 5000;

    private const CONTENT_TYPES = [
        'csv' => 'text/csv; charset=UTF-8',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf',
    ];

    public function contentType(string $format): string
    {
        return self::CONTENT_TYPES[$format];
    }

    public function filename(ReportDefinition $definition, string $format, ?Carbon $at = null): string
    {
        return str_replace('.', '-', $definition->key()).'-'.($at ?? now())->format('Ymd-His').'.'.$format;
    }

    /**
     * Write the report to $handle; returns the number of data rows written.
     *
     * @param  resource  $handle
     * @param  array<string, mixed>  $params
     */
    public function write(string $format, ReportDefinition $definition, ReportContext $context, array $params, $handle): int
    {
        return match ($format) {
            'csv' => $this->csv($definition, $context, $params, $handle),
            'xlsx' => $this->xlsx($definition, $context, $params, $handle),
            'pdf' => $this->pdf($definition, $context, $params, $handle),
        };
    }

    /**
     * Neutralise spreadsheet formula injection in a CSV cell.
     */
    public static function sanitizeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $string = (string) $value;

        if ($string !== '' && in_array($string[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($string)) {
            return "'".$string;
        }

        return $string;
    }

    /**
     * @param  resource  $handle
     * @param  array<string, mixed>  $params
     */
    private function csv(ReportDefinition $definition, ReportContext $context, array $params, $handle): int
    {
        $columns = $definition->columns($params);
        $keys = array_column($columns, 'key');

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_map(self::sanitizeCell(...), array_column($columns, 'label')), escape: '');

        $count = 0;
        foreach ($definition->cursor($context, $params) as $row) {
            fputcsv($handle, array_map(fn (string $key) => self::sanitizeCell($row[$key] ?? null), $keys), escape: '');
            $count++;
        }

        if ($totals = $definition->totals($context, $params)) {
            fputcsv($handle, array_map(fn (string $key) => self::sanitizeCell($totals[$key] ?? null), $keys), escape: '');
        }

        return $count;
    }

    /**
     * @param  resource  $handle
     * @param  array<string, mixed>  $params
     */
    private function xlsx(ReportDefinition $definition, ReportContext $context, array $params, $handle): int
    {
        $columns = $definition->columns($params);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator((string) $context->user->name)
            ->setTitle($definition->name())
            ->setCompany((string) $context->company->name);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $definition->name()) ?? 'Report', 0, 31));

        foreach ($columns as $i => $column) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1).'1', $column['label'], DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(max(1, count($columns))).'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $line = 2;
        $count = 0;
        $put = function (array $row, int $line) use ($sheet, $columns): void {
            foreach ($columns as $i => $column) {
                $value = $row[$column['key']] ?? null;
                if ($value === null) {
                    continue;
                }

                $coordinate = Coordinate::stringFromColumnIndex($i + 1).$line;
                if (in_array($column['type'], ['money', 'number'], true) && is_numeric($value)) {
                    $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_NUMERIC);
                    if ($column['type'] === 'money') {
                        $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                } else {
                    $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
                }
            }
        };

        foreach ($definition->cursor($context, $params) as $row) {
            $put($row, $line++);
            $count++;
        }

        if ($totals = $definition->totals($context, $params)) {
            $put($totals, $line);
            $sheet->getStyle('A'.$line.':'.Coordinate::stringFromColumnIndex(max(1, count($columns))).$line)->getFont()->setBold(true);
        }

        foreach (range(1, max(1, count($columns))) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        (new Xlsx($spreadsheet))->save($handle);
        $spreadsheet->disconnectWorksheets();

        return $count;
    }

    /**
     * @param  resource  $handle
     * @param  array<string, mixed>  $params
     */
    private function pdf(ReportDefinition $definition, ReportContext $context, array $params, $handle): int
    {
        $columns = $definition->columns($params);
        $rows = [];
        $truncated = false;

        foreach ($definition->cursor($context, $params) as $row) {
            if (count($rows) >= self::PDF_MAX_ROWS) {
                $truncated = true;
                break;
            }
            $rows[] = $row;
        }

        $tz = $context->company->timezone ?: 'UTC';

        $pdf = Pdf::loadView('pdf.reports.table', [
            'company' => $context->company,
            'report' => $definition,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $definition->totals($context, $params),
            'params' => $this->describeParams($definition, $params),
            'generatedAt' => now()->setTimezone($tz)->format('Y-m-d H:i T'),
            'generatedBy' => $context->user->name,
            'truncated' => $truncated,
            'maxRows' => self::PDF_MAX_ROWS,
        ])->setPaper('a4', count($columns) > 6 ? 'landscape' : 'portrait');

        fwrite($handle, $pdf->output());

        return count($rows);
    }

    /**
     * Human-readable param list for headers (labels instead of keys).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     */
    public function describeParams(ReportDefinition $definition, array $params): array
    {
        $out = [];

        foreach ($definition->params() as $param) {
            $value = $params[$param['name']] ?? null;
            if ($value === null) {
                continue;
            }

            if ($param['type'] === 'select') {
                $option = collect($param['options'] ?? [])->firstWhere('value', (string) $value);
                $value = $option['label'] ?? $value;
            } elseif ($param['type'] === 'branch') {
                $value = Branch::withoutTenancy()->whereKey($value)->value('name') ?? $value;
            }

            $out[$param['label']] = (string) $value;
        }

        return $out;
    }
}
