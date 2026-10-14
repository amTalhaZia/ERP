<?php

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Procurement\StoreSupplierContactRequest;
use App\Http\Resources\Procurement\SupplierContactResource;
use App\Models\Procurement\Supplier;
use App\Models\Procurement\SupplierContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Contacts are managed through their supplier: `suppliers/{supplier}/contacts`.
 * Authorisation is the supplier's view / update ability.
 */
class SupplierContactController extends ApiController
{
    public function index(Supplier $supplier): AnonymousResourceCollection
    {
        Gate::authorize('view', $supplier);

        return SupplierContactResource::collection(
            $supplier->contacts()->orderByDesc('is_primary')->orderBy('name')->get(),
        );
    }

    public function store(StoreSupplierContactRequest $request, Supplier $supplier): JsonResponse
    {
        Gate::authorize('update', $supplier);

        $contact = DB::transaction(function () use ($request, $supplier): SupplierContact {
            $data = $request->validated();

            if ($data['is_primary'] ?? false) {
                $supplier->contacts()->update(['is_primary' => false]);
            }

            return $supplier->contacts()->create($data + ['company_id' => $supplier->company_id]);
        });

        return (new SupplierContactResource($contact))->response()->setStatusCode(201);
    }

    public function update(StoreSupplierContactRequest $request, Supplier $supplier, SupplierContact $contact): SupplierContactResource
    {
        Gate::authorize('update', $supplier);

        DB::transaction(function () use ($request, $supplier, $contact): void {
            $data = $request->validated();

            if (($data['is_primary'] ?? false) && ! $contact->is_primary) {
                $supplier->contacts()->whereKeyNot($contact->id)->update(['is_primary' => false]);
            }

            $contact->fill($data)->save();
        });

        return new SupplierContactResource($contact->refresh());
    }

    public function destroy(Supplier $supplier, SupplierContact $contact): JsonResponse
    {
        Gate::authorize('update', $supplier);

        $contact->delete();

        return $this->noContent();
    }
}
