<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Sales\StoreCustomerCommunicationRequest;
use App\Http\Resources\Sales\CustomerCommunicationResource;
use App\Models\Sales\Customer;
use App\Models\Sales\CustomerCommunication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Communication log per customer: customers.view to read, customers.manage to write.
 */
class CustomerCommunicationController extends ApiController
{
    public function index(Request $request, Customer $customer): AnonymousResourceCollection
    {
        Gate::authorize('view', $customer);

        $query = $customer->communications()->getQuery()->with('user');
        $this->applyFilters($query, $request, ['type', 'user_id']);
        $this->applyDateRange($query, $request, 'happened_at');
        $this->applySearch($query, $request, ['subject', 'body']);
        $this->applySort($query, $request, ['happened_at', 'follow_up_at', 'created_at'], '-happened_at');

        return $this->paginated($query->paginate($this->perPage($request)), CustomerCommunicationResource::class);
    }

    public function store(StoreCustomerCommunicationRequest $request, Customer $customer): JsonResponse
    {
        Gate::authorize('update', $customer);

        $communication = $customer->communications()->create($request->validated() + [
            'user_id' => $request->user()->id,
            'happened_at' => now(),
        ]);

        return (new CustomerCommunicationResource($communication->load('user')))->response()->setStatusCode(201);
    }

    public function update(StoreCustomerCommunicationRequest $request, Customer $customer, CustomerCommunication $communication): CustomerCommunicationResource
    {
        Gate::authorize('update', $customer);

        $communication->fill($request->validated())->save();

        return new CustomerCommunicationResource($communication->refresh()->load('user'));
    }

    public function destroy(Customer $customer, CustomerCommunication $communication): JsonResponse
    {
        Gate::authorize('update', $customer);

        $communication->delete();

        return $this->noContent();
    }
}
