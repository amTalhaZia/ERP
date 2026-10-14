<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Reports\ExportReportRequest;
use App\Http\Resources\Reports\ExportJobResource;
use App\Models\Reports\ExportJob;
use App\Services\Reports\Definitions\ReportDefinition;
use App\Services\Reports\ReportContext;
use App\Services\Reports\ReportRegistry;
use App\Services\Reports\ReportRunner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Report catalogue, JSON preview and export. Every definition queries the
 * active tenant only; the report's own permission is checked on top of the
 * route's reports.view / reports.export gate.
 */
class ReportController extends ApiController
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly ReportRunner $runner,
        private readonly TenantContext $tenant,
    ) {}

    /** GET /reports — reports this user may run. */
    public function index(Request $request): JsonResponse
    {
        $reports = array_map(fn (ReportDefinition $definition) => $definition->describe(), $this->registry->availableTo($request->user()));

        return response()->json(['data' => $reports]);
    }

    /** GET /reports/{key} — paginated JSON preview (first 500 rows). */
    public function show(Request $request, string $key): JsonResponse
    {
        $definition = $this->definition($key);
        $context = $this->context($request);
        $params = $this->runner->params($definition, $context, $request->except(['page', 'per_page']));

        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ReportRunner::PREVIEW_MAX_ROWS],
        ]);

        $perPage = max(1, min($request->integer('per_page', 50), ReportRunner::PREVIEW_MAX_ROWS));

        return response()->json($this->runner->preview($definition, $context, $params, max(1, $request->integer('page', 1)), $perPage));
    }

    /** POST /reports/{key}/export — streamed file (small) or 202 + export job (large). */
    public function export(ExportReportRequest $request, string $key): Response
    {
        $definition = $this->definition($key);
        $context = $this->context($request);
        $params = $this->runner->params($definition, $context, $request->only('params'), 'params.');

        $result = $this->runner->export($definition, $context, $params, $request->validated('format'));

        if ($result instanceof ExportJob) {
            return (new ExportJobResource($result))->response()->setStatusCode(202);
        }

        return $result;
    }

    private function definition(string $key): ReportDefinition
    {
        $definition = $this->registry->find($key);
        abort_if($definition === null, 404);

        Gate::authorize('runReport', $definition);

        return $definition;
    }

    private function context(Request $request): ReportContext
    {
        return new ReportContext($this->tenant->company(), $request->user(), $this->tenant->allowedBranchIds());
    }
}
