<?php

namespace App\Jobs\Reports;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Reports\ExportJob;
use App\Models\Reports\ExportJobStatus;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\Reports\ReportContext;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportRegistry;
use App\Services\Reports\ReportRunner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Generates a large report export on the queue: re-checks the requester's
 * membership and permissions, runs the report inside that tenant's context,
 * stores the file on the private `local` disk, audits, and notifies the user.
 */
class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly string $exportJobId) {}

    public function handle(TenantContext $tenant, ReportRegistry $registry, ReportExporter $exporter, ReportRunner $runner): void
    {
        $job = ExportJob::withoutTenancy()->find($this->exportJobId);

        if ($job === null || $job->status !== ExportJobStatus::Queued) {
            return;
        }

        $previousCompany = $tenant->company();
        $previousMembership = $tenant->membership();
        $stream = null;

        try {
            $company = Company::query()->findOrFail($job->company_id);
            $user = User::query()->findOrFail($job->user_id);
            $membership = CompanyUser::query()
                ->where('company_id', $company->id)->where('user_id', $user->id)->where('is_active', true)
                ->first() ?? throw new RuntimeException('The requesting user is no longer a member of this company.');

            $tenant->set($company, $membership);
            $user->forgetPermissionCache();

            $definition = $registry->find($job->report_key) ?? throw new RuntimeException('Unknown report.');

            if (! $user->hasPermission('reports.export', $company->id) || ! $definition->authorizedFor($user)) {
                throw new RuntimeException('The requesting user may no longer export this report.');
            }

            $job->forceFill(['status' => ExportJobStatus::Running, 'started_at' => now()])->save();

            $context = new ReportContext($company, $user, $membership->branch_ids);
            $params = (array) ($job->params ?? []);

            $stream = fopen('php://temp', 'w+b');
            $rows = $exporter->write($job->format, $definition, $context, $params, $stream);
            rewind($stream);

            $path = sprintf('exports/%d/%s.%s', $company->id, $job->id, $job->format);
            Storage::disk(ExportJob::DISK)->writeStream($path, $stream);

            $job->forceFill([
                'status' => ExportJobStatus::Completed,
                'file_path' => $path,
                'row_count' => $rows,
                'finished_at' => now(),
            ])->save();

            $runner->audit($definition, $context, $job->format, $params, $rows, $job->id);

            $user->notify(new GenericNotification(
                'report.ready',
                'Your report is ready',
                sprintf('%s (%s, %d rows) is ready to download.', $definition->name(), strtoupper($job->format), $rows),
                '/reports/exports',
                ['export_job_id' => $job->id, 'report' => $definition->key(), 'format' => $job->format],
                companyId: $company->id,
            ));
        } catch (Throwable $e) {
            $job->forceFill([
                'status' => ExportJobStatus::Failed,
                'error' => Str::limit($e->getMessage(), 490),
                'finished_at' => now(),
            ])->save();

            report($e);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($previousCompany !== null) {
                $tenant->set($previousCompany, $previousMembership);
            } else {
                $tenant->clear();
            }
        }
    }
}
