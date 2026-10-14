<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Finance\ReportRequest;
use App\Models\Company;
use App\Models\Finance\Account;
use App\Services\Finance\ReportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Financial statements. `compare=true` adds a `comparison` block for the
 * previous period of equal length (range reports) or the same date one year
 * earlier (point-in-time reports).
 */
class ReportController extends ApiController
{
    public function __construct(private readonly ReportService $reports) {}

    public function trialBalance(ReportRequest $request): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        $includeZero = $request->boolean('include_zero');
        $report = fn (string $asOf) => $this->reports->trialBalance($this->company(), $asOf, $request->branchId(), $includeZero);

        return $this->pointInTime($request, $report);
    }

    public function profitAndLoss(ReportRequest $request): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        return $this->range($request, fn (string $from, string $to) => $this->reports->profitAndLoss($this->company(), $from, $to, $request->branchId()));
    }

    public function balanceSheet(ReportRequest $request): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        return $this->pointInTime($request, fn (string $asOf) => $this->reports->balanceSheet($this->company(), $asOf, $request->branchId()));
    }

    public function cashFlow(ReportRequest $request): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        return $this->range($request, fn (string $from, string $to) => $this->reports->cashFlow($this->company(), $from, $to, $request->branchId()));
    }

    public function accountBalances(ReportRequest $request): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        return $this->pointInTime($request, fn (string $asOf) => $this->reports->accountBalances($this->company(), $asOf, $request->branchId()));
    }

    public function generalLedger(ReportRequest $request): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        $accountId = $request->validated('account_id');

        if ($accountId === null) {
            return response()->json([
                'message' => 'Please correct the highlighted fields.',
                'code' => 'validation.failed',
                'errors' => ['account_id' => ['The account id field is required.']],
            ], 422);
        }

        $account = Account::query()->findOrFail($accountId);

        $ledger = $this->reports->ledger(
            $this->company(),
            $account,
            $request->validated('from'),
            $request->validated('to'),
            $request->branchId(),
            max(1, $request->integer('page', 1)),
            $this->perPage($request),
        );

        $paginator = $ledger['lines'];

        return response()->json([
            'data' => [
                'account' => $ledger['account'],
                'from' => $ledger['from'],
                'to' => $ledger['to'],
                'opening_balance' => $ledger['opening_balance'],
                'closing_balance' => $ledger['closing_balance'],
                'lines' => $paginator->items(),
            ],
            'meta' => [
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    // -- helpers --------------------------------------------------------------

    private function company(): Company
    {
        return app(TenantContext::class)->company();
    }

    /**
     * @param  callable(string, string): array<string, mixed>  $report
     */
    private function range(ReportRequest $request, callable $report): JsonResponse
    {
        $payload = ['data' => $report($request->from(), $request->to())];

        if ($request->compare()) {
            $previous = ReportService::previousRange($request->from(), $request->to());
            $payload['comparison'] = $report($previous['from'], $previous['to']);
        }

        return response()->json($payload);
    }

    /**
     * @param  callable(string): array<string, mixed>  $report
     */
    private function pointInTime(ReportRequest $request, callable $report): JsonResponse
    {
        $payload = ['data' => $report($request->asOf())];

        if ($request->compare()) {
            $payload['comparison'] = $report(Carbon::parse($request->asOf())->subYear()->toDateString());
        }

        return response()->json($payload);
    }
}
