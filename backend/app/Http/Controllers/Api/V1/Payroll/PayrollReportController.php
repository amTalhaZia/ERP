<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Payroll\PayrollRun;
use App\Services\Payroll\PayrollReportService;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Payroll reports (payroll.view). Aggregation is done in SQL; only released
 * (Finalized / Paid) runs count towards the summary.
 */
class PayrollReportController extends ApiController
{
    public function __construct(private readonly PayrollReportService $reports) {}

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PayrollRun::class);

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return response()->json(['data' => $this->reports->summary($data['from'], $data['to'])]);
    }

    public function components(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PayrollRun::class);

        $data = $request->validate(['run_id' => ['required', 'integer', new TenantExists(PayrollRun::class)]]);
        $run = PayrollRun::query()->findOrFail($data['run_id']);
        Gate::authorize('view', $run);

        return response()->json(['data' => $this->reports->components($run)]);
    }
}
