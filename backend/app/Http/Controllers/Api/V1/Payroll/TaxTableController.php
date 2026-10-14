<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Actions\Payroll\SaveTaxTableAction;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Payroll\StoreTaxTableRequest;
use App\Http\Resources\Payroll\TaxTableResource;
use App\Models\Payroll\TaxTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Configurable withholding tables. The rules are customer-entered and
 * jurisdiction-specific compliance is the customer's responsibility.
 */
class TaxTableController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TaxTable::class);

        $query = TaxTable::query()->withCount('brackets');
        $this->applySearch($query, $request, ['name']);
        $this->applyFilters($query, $request, ['is_active', 'period']);
        $this->applySort($query, $request, ['name', 'effective_from', 'created_at'], '-effective_from');

        return $this->paginated($query->paginate($this->perPage($request)), TaxTableResource::class);
    }

    public function store(StoreTaxTableRequest $request, SaveTaxTableAction $save): JsonResponse
    {
        Gate::authorize('create', TaxTable::class);

        return (new TaxTableResource($save->handle($request->validated())))->response()->setStatusCode(201);
    }

    public function show(TaxTable $taxTable): TaxTableResource
    {
        Gate::authorize('view', $taxTable);

        return new TaxTableResource($taxTable->load('brackets'));
    }

    public function update(StoreTaxTableRequest $request, TaxTable $taxTable, SaveTaxTableAction $save): TaxTableResource
    {
        Gate::authorize('update', $taxTable);

        return new TaxTableResource($save->handle($request->validated(), $taxTable));
    }

    public function destroy(TaxTable $taxTable): JsonResponse
    {
        Gate::authorize('delete', $taxTable);

        $taxTable->delete();

        return $this->noContent();
    }
}
