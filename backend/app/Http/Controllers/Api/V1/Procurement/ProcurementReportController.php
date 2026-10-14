<?php

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Procurement\ReportFilterRequest;
use App\Services\Procurement\ProcurementReportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Read-only aggregates. Guarded by `permission:reports.view` at the router
 * and re-checked here because there is no model for a policy to attach to.
 */
class ProcurementReportController extends ApiController
{
    public function __construct(
        private readonly ProcurementReportService $reports,
        private readonly TenantContext $tenant,
    ) {}

    public function spend(ReportFilterRequest $request): JsonResponse
    {
        $this->authorizeReports($request);

        $from = $request->validated('from');
        $to = $request->validated('to');
        $groupBy = $request->validated('group_by') ?? 'supplier';

        return response()->json([
            'data' => $this->reports->spend((int) $this->tenant->id(), $from, $to, $groupBy),
            'meta' => ['from' => $from, 'to' => $to, 'group_by' => $groupBy, 'currency' => $this->tenant->company()?->currency],
        ]);
    }

    public function statusSummary(ReportFilterRequest $request): JsonResponse
    {
        $this->authorizeReports($request);

        $from = $request->validated('from');
        $to = $request->validated('to');

        return response()->json([
            'data' => $this->reports->statusSummary((int) $this->tenant->id(), $from, $to),
            'meta' => ['from' => $from, 'to' => $to, 'currency' => $this->tenant->company()?->currency],
        ]);
    }

    public function supplierPerformance(ReportFilterRequest $request): JsonResponse
    {
        $this->authorizeReports($request);

        $from = $request->validated('from');
        $to = $request->validated('to');

        return response()->json([
            'data' => $this->reports->supplierPerformance((int) $this->tenant->id(), $from, $to),
            'meta' => ['from' => $from, 'to' => $to, 'currency' => $this->tenant->company()?->currency],
        ]);
    }

    private function authorizeReports(ReportFilterRequest $request): void
    {
        abort_unless($request->user()?->hasPermission('reports.view'), 403);
    }
}
