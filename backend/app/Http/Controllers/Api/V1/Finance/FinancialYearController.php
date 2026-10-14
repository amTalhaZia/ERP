<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Actions\Finance\GeneratePeriodsAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Finance\StoreFinancialYearRequest;
use App\Http\Resources\Finance\FinancialYearResource;
use App\Models\Finance\FinancialYear;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class FinancialYearController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', FinancialYear::class);

        $query = FinancialYear::query()->with('periods');
        $this->applyFilters($query, $request, ['status']);
        $this->applySort($query, $request, ['start_date', 'name', 'created_at'], '-start_date');

        return $this->paginated($query->paginate($this->perPage($request)), FinancialYearResource::class);
    }

    /** Creates a 12-month year with its monthly periods. */
    public function store(StoreFinancialYearRequest $request, GeneratePeriodsAction $generate): JsonResponse
    {
        Gate::authorize('create', FinancialYear::class);

        $year = $generate->handle(
            app(TenantContext::class)->company(),
            $request->validated('start_date'),
            $request->validated('name'),
        );

        return (new FinancialYearResource($year))->response()->setStatusCode(201);
    }

    public function show(FinancialYear $financialYear): FinancialYearResource
    {
        Gate::authorize('view', $financialYear);

        return new FinancialYearResource($financialYear->load('periods'));
    }
}
