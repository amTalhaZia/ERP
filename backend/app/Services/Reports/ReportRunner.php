<?php

namespace App\Services\Reports;

use App\Jobs\Reports\GenerateReportExport;
use App\Models\AuditLog;
use App\Models\Reports\ExportJob;
use App\Models\Reports\ExportJobStatus;
use App\Services\Reports\Definitions\ReportDefinition;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Orchestrates previews and exports: param validation, the sync-vs-queued
 * decision (≤ sync limit rows → streamed download, otherwise an ExportJob),
 * and the `reports.exported` audit entry written for every export.
 */
class ReportRunner
{
    public const PREVIEW_MAX_ROWS = 500;

    public function __construct(
        private readonly ReportExporter $exporter,
        private readonly AuditLogger $audit,
    ) {}

    public static function syncRowLimit(): int
    {
        return (int) config('erp.reports.sync_row_limit', 2000);
    }

    /**
     * Validate raw input against the definition's schema and apply defaults.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function params(ReportDefinition $definition, ReportContext $context, array $input, string $prefix = ''): array
    {
        $rules = [];
        foreach ($definition->rules() as $name => $fieldRules) {
            $rules[$prefix.$name] = array_map(
                fn ($rule) => is_string($rule) && str_starts_with($rule, 'after_or_equal:') ? 'after_or_equal:'.$prefix.Str::after($rule, ':') : $rule,
                $fieldRules,
            );
        }

        $validated = Validator::make($input, $rules)->validate();
        $values = $prefix === '' ? $validated : (array) data_get($validated, rtrim($prefix, '.'), []);

        return $definition->resolveParams($context, $values);
    }

    /**
     * Paged preview capped at PREVIEW_MAX_ROWS rows.
     *
     * @param  array<string, mixed>  $params
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function preview(ReportDefinition $definition, ReportContext $context, array $params, int $page, int $perPage): array
    {
        $total = $definition->count($context, $params);
        $visible = min($total, self::PREVIEW_MAX_ROWS);
        $offset = ($page - 1) * $perPage;
        $limit = max(0, min($perPage, $visible - $offset));

        return [
            'data' => [
                'report' => ['key' => $definition->key(), 'name' => $definition->name(), 'module' => $definition->module()],
                'params' => $params,
                'currency' => $context->currency(),
                'columns' => $definition->columns($params),
                'rows' => $limit > 0 ? $definition->slice($context, $params, $offset, $limit) : [],
                'totals' => $definition->totals($context, $params),
            ],
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $visible,
                    'last_page' => max(1, (int) ceil($visible / $perPage)),
                ],
                'total_rows' => $total,
                'truncated' => $total > self::PREVIEW_MAX_ROWS,
            ],
        ];
    }

    /**
     * Small result → streamed download now; large → queued ExportJob.
     *
     * @param  array<string, mixed>  $params
     */
    public function export(ReportDefinition $definition, ReportContext $context, array $params, string $format): StreamedResponse|ExportJob
    {
        $estimated = $definition->count($context, $params);

        if ($estimated > self::syncRowLimit()) {
            $job = ExportJob::query()->create([
                'company_id' => $context->companyId(),
                'user_id' => $context->user->id,
                'report_key' => $definition->key(),
                'format' => $format,
                'params' => $params,
                'status' => ExportJobStatus::Queued,
                'row_count' => $estimated,
                'expires_at' => now()->addDays((int) config('erp.retention_days.export_files', 7)),
            ]);

            GenerateReportExport::dispatch($job->id);

            return $job;
        }

        $this->audit($definition, $context, $format, $params, $estimated);

        $exporter = $this->exporter;

        return response()->streamDownload(function () use ($exporter, $format, $definition, $context, $params): void {
            $out = fopen('php://output', 'wb');
            $exporter->write($format, $definition, $context, $params, $out);
            fclose($out);
        }, $this->exporter->filename($definition, $format), [
            'Content-Type' => $this->exporter->contentType($format),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * `reports.exported` audit row. Written directly (not via the request
     * user) so queued exports still record the requesting user as actor.
     *
     * @param  array<string, mixed>  $params
     */
    public function audit(ReportDefinition $definition, ReportContext $context, string $format, array $params, int $rows, ?string $exportJobId = null): AuditLog
    {
        $request = request();

        return AuditLog::query()->create([
            'company_id' => $context->companyId(),
            'actor_id' => $context->user->id,
            'actor_name' => $context->user->name,
            'action' => 'reports.exported',
            'entity_type' => $exportJobId ? 'export_job' : null,
            'entity_id' => null,
            'entity_label' => $definition->name(),
            'context' => $this->audit->sanitize(array_filter([
                'report' => $definition->key(),
                'format' => $format,
                'params' => $params,
                'rows' => $rows,
                'export_job_id' => $exportJobId,
            ], fn ($value) => $value !== null)),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);
    }
}
