<?php

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Actions\Procurement\CreatePurchaseOrderFromRequisitionAction;
use App\Actions\Procurement\DecidePurchaseRequisitionAction;
use App\Actions\Procurement\SavePurchaseRequisitionAction;
use App\Actions\Procurement\SubmitPurchaseRequisitionAction;
use App\Enums\Procurement\RequisitionStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Procurement\CreatePurchaseOrderFromRequisitionRequest;
use App\Http\Requests\Procurement\ReasonRequest;
use App\Http\Requests\Procurement\StorePurchaseRequisitionRequest;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Http\Resources\Procurement\PurchaseRequisitionResource;
use App\Models\Procurement\PurchaseRequisition;
use App\Models\Procurement\Supplier;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PurchaseRequisitionController extends ApiController
{
    private const HEADER_RELATIONS = ['requester:id,name', 'department:id,name', 'branch:id,name', 'approver:id,name'];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PurchaseRequisition::class);

        $query = PurchaseRequisition::query()->visibleBranches()->with(self::HEADER_RELATIONS)->withCount('lines');

        // Requesters without a review permission only ever see their own documents.
        if (! $request->user()->hasAnyPermission(['requisitions.view', 'requisitions.approve']) || $request->boolean('mine')) {
            $query->where('requester_id', $request->user()->id);
        }

        $this->applySearch($query, $request, ['number', 'justification']);
        $this->applyFilters($query, $request, ['status', 'priority', 'department_id', 'branch_id', 'requester_id']);
        $this->applyDateRange($query, $request, 'created_at');
        $this->applySort($query, $request, ['number', 'status', 'priority', 'needed_by', 'estimated_total', 'created_at'], '-created_at');

        return $this->paginated($query->paginate($this->perPage($request)), PurchaseRequisitionResource::class);
    }

    public function store(StorePurchaseRequisitionRequest $request, SavePurchaseRequisitionAction $save): JsonResponse
    {
        Gate::authorize('create', PurchaseRequisition::class);

        $requisition = $save->handle($request->validated(), $request->user());

        return (new PurchaseRequisitionResource($requisition->loadMissing('lines.product:id,sku,name')))->response()->setStatusCode(201);
    }

    public function show(PurchaseRequisition $requisition): PurchaseRequisitionResource
    {
        Gate::authorize('view', $requisition);

        return new PurchaseRequisitionResource($requisition->load([...self::HEADER_RELATIONS, 'lines.product:id,sku,name', 'purchaseOrders:id,requisition_id,number,status']));
    }

    public function update(StorePurchaseRequisitionRequest $request, PurchaseRequisition $requisition, SavePurchaseRequisitionAction $save): PurchaseRequisitionResource
    {
        Gate::authorize('update', $requisition);
        $this->ensureDraft($requisition);

        return new PurchaseRequisitionResource($save->handle($request->validated(), $request->user(), $requisition)->loadMissing('lines.product:id,sku,name'));
    }

    public function destroy(PurchaseRequisition $requisition): JsonResponse
    {
        Gate::authorize('delete', $requisition);
        $this->ensureDraft($requisition);

        $requisition->delete();

        return $this->noContent();
    }

    public function submit(PurchaseRequisition $requisition, SubmitPurchaseRequisitionAction $submit): PurchaseRequisitionResource
    {
        Gate::authorize('submit', $requisition);

        return new PurchaseRequisitionResource($submit->handle($requisition)->load([...self::HEADER_RELATIONS, 'lines.product:id,sku,name']));
    }

    public function approve(Request $request, PurchaseRequisition $requisition, DecidePurchaseRequisitionAction $decide): PurchaseRequisitionResource
    {
        Gate::authorize('approve', $requisition);

        return new PurchaseRequisitionResource($decide->approve($requisition, $request->user())->load([...self::HEADER_RELATIONS, 'lines.product:id,sku,name']));
    }

    public function reject(ReasonRequest $request, PurchaseRequisition $requisition, DecidePurchaseRequisitionAction $decide): PurchaseRequisitionResource
    {
        Gate::authorize('reject', $requisition);

        $decided = $decide->reject($requisition, $request->user(), $request->validated('reason'));

        return new PurchaseRequisitionResource($decided->load([...self::HEADER_RELATIONS, 'lines.product:id,sku,name']));
    }

    /** Requester withdraws a draft or submitted requisition. */
    public function cancel(PurchaseRequisition $requisition, AuditLogger $audit): PurchaseRequisitionResource
    {
        Gate::authorize('cancel', $requisition);

        if (! $requisition->status->isOpen()) {
            abort(409, 'Only draft or submitted requisitions can be cancelled.');
        }

        DB::transaction(function () use ($requisition, $audit): void {
            $before = $requisition->status->value;
            $requisition->forceFill(['status' => RequisitionStatus::Cancelled])->save();
            $audit->log('purchase.requisitions.cancelled', $requisition, ['status' => $before], ['status' => RequisitionStatus::Cancelled->value]);
        });

        return new PurchaseRequisitionResource($requisition->refresh()->load([...self::HEADER_RELATIONS, 'lines.product:id,sku,name']));
    }

    public function createPurchaseOrder(
        CreatePurchaseOrderFromRequisitionRequest $request,
        PurchaseRequisition $requisition,
        CreatePurchaseOrderFromRequisitionAction $convert,
    ): JsonResponse {
        Gate::authorize('createPurchaseOrder', $requisition);

        $data = $request->validated();
        $supplier = Supplier::query()->findOrFail($data['supplier_id']);
        $overrides = array_filter(
            ['expected_date' => $data['expected_date'] ?? null, 'warehouse_id' => $data['warehouse_id'] ?? null, 'delivery_address' => $data['delivery_address'] ?? null, 'notes' => $data['notes'] ?? null],
            fn ($value) => $value !== null,
        );

        $order = $convert->handle($requisition->load('lines'), $supplier, $request->user(), $overrides);

        return (new PurchaseOrderResource($order->loadMissing(['lines.product:id,sku,name', 'warehouse:id,name'])))->response()->setStatusCode(201);
    }

    private function ensureDraft(PurchaseRequisition $requisition): void
    {
        if (! $requisition->isDraft()) {
            abort(409, 'Only draft requisitions can be edited or deleted.');
        }
    }
}
