<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Enums\Finance\PaymentPartnerType;
use App\Http\Controllers\Api\V1\ApiController;
use App\Services\Finance\Receivables\PartnerDirectory;
use App\Services\Finance\Receivables\ReceivablesReportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * AR/AP reports. Aging and summary require reports.financial.view (route
 * middleware + gate); customer statements additionally accept invoices.view.
 */
class ReceivablesReportController extends ApiController
{
    public function __construct(
        private readonly ReceivablesReportService $reports,
        private readonly TenantContext $tenant,
    ) {}

    public function arAging(Request $request): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        return response()->json(['data' => $this->reports->aging((int) $this->tenant->id(), PaymentPartnerType::Customer, $this->asOf($request))]);
    }

    public function apAging(Request $request): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        return response()->json(['data' => $this->reports->aging((int) $this->tenant->id(), PaymentPartnerType::Supplier, $this->asOf($request))]);
    }

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        return response()->json(['data' => $this->reports->summary((int) $this->tenant->id(), $this->asOf($request))]);
    }

    public function customerStatement(Request $request, int $customer, PartnerDirectory $partners): JsonResponse
    {
        Gate::authorize('receivables.statements.view');

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        abort_unless($partners->exists('customers', $customer), 404);

        $from = $validated['from'] ?? now()->startOfYear()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        return response()->json(['data' => $this->reports->customerStatement((int) $this->tenant->id(), $customer, $from, $to)]);
    }

    private function asOf(Request $request): string
    {
        $validated = $request->validate(['as_of' => ['nullable', 'date_format:Y-m-d']]);

        return $validated['as_of'] ?? now()->toDateString();
    }
}
