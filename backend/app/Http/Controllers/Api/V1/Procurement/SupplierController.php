<?php

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Procurement\StoreSupplierRequest;
use App\Http\Resources\Procurement\SupplierResource;
use App\Models\Procurement\Supplier;
use App\Services\Procurement\SupplierSummaryService;
use App\Support\Audit\AuditLogger;
use App\Support\Numbering\DocumentNumberer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SupplierController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Supplier::class);

        $query = Supplier::query()->withCount(['contacts', 'purchaseOrders']);
        $this->applySearch($query, $request, ['name', 'code', 'email', 'city', 'tax_number']);
        $this->applyFilters($query, $request, ['is_active', 'is_approved', 'type', 'country', 'currency', 'rating']);
        $this->applySort($query, $request, ['name', 'code', 'rating', 'city', 'created_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), SupplierResource::class);
    }

    /** Lightweight list for selectors (active suppliers, no pagination). */
    public function all(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Supplier::class);

        return SupplierResource::collection(
            Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'company_id', 'code', 'name', 'type', 'currency', 'payment_terms_days', 'is_approved', 'is_active', 'city', 'country', 'rating', 'lead_time_days']),
        );
    }

    /** Geo-coded active suppliers for the map view. */
    public function map(): JsonResponse
    {
        Gate::authorize('viewAny', Supplier::class);

        $points = Supplier::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'city', 'country', 'latitude', 'longitude', 'rating', 'is_approved'])
            ->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'type' => $supplier->type?->value,
                'city' => $supplier->city,
                'country' => $supplier->country,
                'latitude' => $supplier->latitude,
                'longitude' => $supplier->longitude,
                'rating' => $supplier->rating,
                'is_approved' => $supplier->is_approved,
            ]);

        return response()->json(['data' => $points]);
    }

    public function store(StoreSupplierRequest $request, DocumentNumberer $numberer): JsonResponse
    {
        Gate::authorize('create', Supplier::class);

        $supplier = DB::transaction(function () use ($request, $numberer): Supplier {
            $data = $this->prepare($request->validated());

            if (empty($data['code'])) {
                $data['code'] = $numberer->next('supplier', yearly: false, prefix: 'SUP', padding: 5);
            }

            // Refresh so DB defaults (payment_terms_days, currency, …) are present in the response.
            return Supplier::query()->create($data)->refresh();
        });

        return (new SupplierResource($supplier->load('contacts')))->response()->setStatusCode(201);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        Gate::authorize('view', $supplier);

        return new SupplierResource($supplier->load(['contacts', 'approver:id,name'])->loadCount('purchaseOrders'));
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        Gate::authorize('update', $supplier);

        $data = $this->prepare($request->validated());

        if (array_key_exists('code', $data) && empty($data['code'])) {
            unset($data['code']);
        }

        $supplier->fill($data)->save();

        return new SupplierResource($supplier->refresh()->load(['contacts', 'approver:id,name']));
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        Gate::authorize('delete', $supplier);

        $supplier->delete();

        return $this->noContent();
    }

    public function summary(Supplier $supplier, SupplierSummaryService $summary): JsonResponse
    {
        Gate::authorize('view', $supplier);

        return response()->json(['data' => $summary->summarize($supplier)]);
    }

    /** Vendor approval: marks the supplier as approved for purchasing (audited). */
    public function approve(Request $request, Supplier $supplier, AuditLogger $audit): SupplierResource
    {
        Gate::authorize('approve', $supplier);

        if ($supplier->is_approved) {
            abort(409, 'This supplier is already approved.');
        }

        DB::transaction(function () use ($request, $supplier, $audit): void {
            $supplier->forceFill([
                'is_approved' => true,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ])->save();

            $audit->log('suppliers.approved', $supplier, ['is_approved' => false], [
                'is_approved' => true,
                'approved_by' => $request->user()->id,
            ]);
        });

        return new SupplierResource($supplier->refresh()->load(['contacts', 'approver:id,name']));
    }

    /**
     * Derive the clear-text last four digits when a bank account is supplied.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        if (array_key_exists('bank_account_number', $data)) {
            $digits = preg_replace('/\D+/', '', (string) $data['bank_account_number']) ?? '';
            $data['bank_account_last4'] = $data['bank_account_number'] === null || $data['bank_account_number'] === ''
                ? null
                : substr($digits !== '' ? $digits : (string) $data['bank_account_number'], -4);

            if ($data['bank_account_number'] === '') {
                $data['bank_account_number'] = null;
            }
        }

        return $data;
    }
}
