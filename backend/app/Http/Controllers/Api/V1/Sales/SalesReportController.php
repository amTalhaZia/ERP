<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Sales\PerformanceReportRequest;
use App\Services\Sales\SalesReports;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class SalesReportController extends ApiController
{
    public function __construct(private readonly SalesReports $reports) {}

    /** GET /sales/reports/performance?from&to&group_by=rep|customer|month */
    public function performance(PerformanceReportRequest $request): JsonResponse
    {
        Gate::authorize('sales.reports.view');

        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : today();
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : $to->copy()->subMonths(12)->startOfMonth();

        return response()->json([
            'data' => $this->reports->performance($from, $to, (string) $request->input('group_by', 'rep')),
        ]);
    }

    /** GET /sales/reports/pipeline */
    public function pipeline(): JsonResponse
    {
        Gate::authorize('sales.reports.view');

        return response()->json(['data' => $this->reports->pipelineSummary()]);
    }
}
