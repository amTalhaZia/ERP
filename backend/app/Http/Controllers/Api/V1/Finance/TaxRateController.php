<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Finance\StoreTaxRateRequest;
use App\Http\Resources\Finance\TaxRateResource;
use App\Models\Finance\JournalLine;
use App\Models\Finance\TaxRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaxRateController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', TaxRate::class);

        $query = TaxRate::query()->with('account:id,code,name');
        $this->applySearch($query, $request, ['name', 'code']);
        $this->applyFilters($query, $request, ['type', 'is_active']);
        $this->applySort($query, $request, ['code', 'name', 'rate', 'created_at'], 'code');

        return $this->paginated($query->paginate($this->perPage($request)), TaxRateResource::class);
    }

    public function store(StoreTaxRateRequest $request): JsonResponse
    {
        Gate::authorize('create', TaxRate::class);

        $taxRate = TaxRate::query()->create($request->validated());

        return (new TaxRateResource($taxRate->load('account:id,code,name')))->response()->setStatusCode(201);
    }

    public function show(TaxRate $taxRate): TaxRateResource
    {
        Gate::authorize('view', $taxRate);

        return new TaxRateResource($taxRate->load('account:id,code,name'));
    }

    public function update(StoreTaxRateRequest $request, TaxRate $taxRate): TaxRateResource
    {
        Gate::authorize('update', $taxRate);

        $taxRate->fill($request->validated())->save();

        return new TaxRateResource($taxRate->refresh()->load('account:id,code,name'));
    }

    public function destroy(TaxRate $taxRate): JsonResponse
    {
        Gate::authorize('delete', $taxRate);

        if (JournalLine::query()->where('tax_rate_id', $taxRate->id)->exists()) {
            return $this->message('This tax rate is referenced by journal lines; deactivate it instead.', ['code' => 'conflict'], 409);
        }

        $taxRate->delete();

        return $this->noContent();
    }
}
