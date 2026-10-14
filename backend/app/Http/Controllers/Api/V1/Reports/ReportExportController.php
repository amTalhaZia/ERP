<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Reports\ExportJobResource;
use App\Models\Reports\ExportJob;
use App\Models\Reports\ExportJobStatus;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportRegistry;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The current user's queued exports. Another user's (or tenant's) job id is
 * a 404 so its existence is not disclosed.
 */
class ReportExportController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ExportJob::class);

        $query = ExportJob::query()->where('user_id', $request->user()->id)->orderByDesc('created_at')->orderByDesc('id');

        return $this->paginated($query->paginate($this->perPage($request)), ExportJobResource::class);
    }

    public function show(Request $request, string $exportJob): ExportJobResource
    {
        $job = $this->find($request, $exportJob);
        Gate::authorize('view', $job);

        return new ExportJobResource($job);
    }

    public function download(Request $request, string $exportJob, ReportExporter $exporter, ReportRegistry $registry, AuditLogger $audit): StreamedResponse
    {
        $job = $this->find($request, $exportJob);
        Gate::authorize('download', $job);

        abort_if($job->status !== ExportJobStatus::Completed || $job->file_path === null, 409, 'This export is not ready yet.');
        abort_if($job->isExpired(), 410, 'This export has expired. Please run it again.');

        $disk = Storage::disk(ExportJob::DISK);
        abort_unless($disk->exists($job->file_path), 410, 'This export file is no longer available.');

        $audit->log('reports.export_downloaded', context: ['export_job_id' => $job->id, 'report' => $job->report_key, 'format' => $job->format], entityLabel: $registry->find($job->report_key)?->name());

        $definition = $registry->find($job->report_key);
        $filename = $definition !== null
            ? $exporter->filename($definition, $job->format, $job->finished_at)
            : 'report-'.$job->id.'.'.$job->format;

        return $disk->download($job->file_path, $filename, [
            'Content-Type' => $exporter->contentType($job->format),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function find(Request $request, string $id): ExportJob
    {
        return ExportJob::query()->whereKey($id)->where('user_id', $request->user()->id)->firstOrFail();
    }
}
