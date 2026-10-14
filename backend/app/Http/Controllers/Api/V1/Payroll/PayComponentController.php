<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Exceptions\Finance\FinanceException;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Payroll\StorePayComponentRequest;
use App\Http\Resources\Payroll\PayComponentResource;
use App\Models\Payroll\PayComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PayComponentController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PayComponent::class);

        $query = PayComponent::query();
        $this->applySearch($query, $request, ['code', 'name']);
        $this->applyFilters($query, $request, ['kind', 'calculation', 'is_active', 'is_taxable']);
        $this->applySort($query, $request, ['code', 'name', 'kind', 'sort_order', 'created_at'], 'sort_order');

        return $this->paginated($query->paginate($this->perPage($request)), PayComponentResource::class);
    }

    public function store(StorePayComponentRequest $request): JsonResponse
    {
        Gate::authorize('create', PayComponent::class);

        $component = PayComponent::query()->create($request->validated());

        return (new PayComponentResource($component->refresh()))->response()->setStatusCode(201);
    }

    public function show(PayComponent $payComponent): PayComponentResource
    {
        Gate::authorize('view', $payComponent);

        return new PayComponentResource($payComponent);
    }

    public function update(StorePayComponentRequest $request, PayComponent $payComponent): PayComponentResource
    {
        Gate::authorize('update', $payComponent);

        $payComponent->update($request->validated());

        return new PayComponentResource($payComponent->refresh());
    }

    public function destroy(PayComponent $payComponent): JsonResponse
    {
        Gate::authorize('delete', $payComponent);

        if ($payComponent->isInUse()) {
            throw FinanceException::conflict(
                "Pay component {$payComponent->code} is used by a salary structure or employee salary; deactivate it instead.",
                'payroll.component_in_use',
            );
        }

        $payComponent->delete();

        return $this->noContent();
    }
}
