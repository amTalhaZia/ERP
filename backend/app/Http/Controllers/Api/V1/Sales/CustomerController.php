<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Sales\StoreCustomerRequest;
use App\Http\Resources\Sales\CustomerResource;
use App\Models\Sales\Customer;
use App\Services\Sales\SalesReports;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CustomerController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Customer::class);

        $query = Customer::query()->with('salesRep')->withCount('contacts');
        $this->applySearch($query, $request, ['name', 'code', 'email', 'phone', 'billing_city']);
        $this->applyFilters($query, $request, ['is_active', 'type', 'sales_rep_id', 'source', 'billing_country', 'currency']);
        $this->applySort($query, $request, ['name', 'code', 'created_at', 'payment_terms_days', 'credit_limit'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), CustomerResource::class);
    }

    /** Lightweight `{id, name, code, currency, payment_terms_days}` list for selectors. */
    public function all(): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        return response()->json([
            'data' => Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'currency', 'payment_terms_days'])->all(),
        ]);
    }

    /** Active customers that have coordinates, for the map view. */
    public function map(): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        return response()->json([
            'data' => Customer::query()
                ->where('is_active', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'latitude', 'longitude', 'billing_city', 'billing_country'])
                ->map(fn (Customer $c) => [
                    'id' => $c->id, 'name' => $c->name, 'code' => $c->code,
                    'latitude' => $c->latitude, 'longitude' => $c->longitude,
                    'city' => $c->billing_city, 'country' => $c->billing_country,
                ])->all(),
        ]);
    }

    public function store(StoreCustomerRequest $request, DocumentNumberer $numberer): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        $customer = DB::transaction(function () use ($request, $numberer): Customer {
            $data = $request->validated();
            $data['code'] = ($data['code'] ?? null) ?: $numberer->next('customer', yearly: false, prefix: 'CUST', padding: 5);
            $data['currency'] ??= app(TenantContext::class)->company()?->currency ?? 'USD';

            return Customer::query()->create($data);
        });

        return (new CustomerResource($customer->load('salesRep')))->response()->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        Gate::authorize('view', $customer);

        return new CustomerResource($customer->load(['salesRep', 'contacts']));
    }

    public function update(StoreCustomerRequest $request, Customer $customer): CustomerResource
    {
        Gate::authorize('update', $customer);

        $data = $request->validated();

        if (array_key_exists('code', $data) && ! $data['code']) {
            unset($data['code']);
        }

        $customer->fill($data)->save();

        return new CustomerResource($customer->refresh()->load(['salesRep', 'contacts']));
    }

    public function destroy(Customer $customer): JsonResponse
    {
        Gate::authorize('delete', $customer);

        $customer->delete();

        return $this->noContent();
    }

    public function summary(Customer $customer, SalesReports $reports): JsonResponse
    {
        Gate::authorize('view', $customer);

        return response()->json(['data' => $reports->customerSummary($customer)]);
    }
}
