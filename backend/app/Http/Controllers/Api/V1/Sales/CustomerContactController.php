<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Sales\StoreCustomerContactRequest;
use App\Http\Resources\Sales\CustomerContactResource;
use App\Models\Sales\Customer;
use App\Models\Sales\CustomerContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Contacts are authorised through their customer (customers.manage).
 * Route bindings are scoped: `{contact}` must belong to `{customer}`.
 */
class CustomerContactController extends ApiController
{
    public function store(StoreCustomerContactRequest $request, Customer $customer): JsonResponse
    {
        Gate::authorize('update', $customer);

        $contact = DB::transaction(function () use ($request, $customer): CustomerContact {
            $data = $request->validated();

            if ($data['is_primary'] ?? false) {
                $customer->contacts()->update(['is_primary' => false]);
            }

            return $customer->contacts()->create($data);
        });

        return (new CustomerContactResource($contact))->response()->setStatusCode(201);
    }

    public function update(StoreCustomerContactRequest $request, Customer $customer, CustomerContact $contact): CustomerContactResource
    {
        Gate::authorize('update', $customer);

        DB::transaction(function () use ($request, $customer, $contact): void {
            $data = $request->validated();

            if (($data['is_primary'] ?? false) && ! $contact->is_primary) {
                $customer->contacts()->whereKeyNot($contact->id)->update(['is_primary' => false]);
            }

            $contact->fill($data)->save();
        });

        return new CustomerContactResource($contact->refresh());
    }

    public function destroy(Customer $customer, CustomerContact $contact): JsonResponse
    {
        Gate::authorize('update', $customer);

        $contact->delete();

        return $this->noContent();
    }
}
