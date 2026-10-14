<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\ClosePeriodAction;
use App\Actions\Finance\ReopenPeriodAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Finance\FinancialPeriodResource;
use App\Models\Finance\FinancialPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class FinancialPeriodController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', FinancialPeriod::class);

        $query = FinancialPeriod::query()->with(['financialYear:id,name', 'closedBy:id,name']);
        $this->applyFilters($query, $request, ['status', 'financial_year_id']);
        $this->applyDateRange($query, $request, 'start_date');
        $this->applySort($query, $request, ['start_date', 'name', 'status'], 'start_date');

        return $this->paginated($query->paginate($this->perPage($request)), FinancialPeriodResource::class);
    }

    public function show(FinancialPeriod $financialPeriod): FinancialPeriodResource
    {
        Gate::authorize('view', $financialPeriod);

        return new FinancialPeriodResource($financialPeriod->load(['financialYear:id,name', 'closedBy:id,name']));
    }

    public function close(Request $request, FinancialPeriod $financialPeriod, ClosePeriodAction $close): FinancialPeriodResource
    {
        Gate::authorize('close', $financialPeriod);

        $period = $close->handle($financialPeriod, $request->user()?->id);

        return new FinancialPeriodResource($period->load(['financialYear:id,name', 'closedBy:id,name']));
    }

    public function reopen(Request $request, FinancialPeriod $financialPeriod, ReopenPeriodAction $reopen): FinancialPeriodResource
    {
        Gate::authorize('reopen', $financialPeriod);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $period = $reopen->handle($financialPeriod, $validated['reason'] ?? null);

        return new FinancialPeriodResource($period->load(['financialYear:id,name', 'closedBy:id,name']));
    }
}
