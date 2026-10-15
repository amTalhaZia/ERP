<?php

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Actions\Procurement\ApprovePurchaseOrderAction;
use App\Actions\Procurement\CancelPurchaseOrderAction;
use App\Actions\Procurement\SavePurchaseOrderAction;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Procurement\ReasonRequest;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\Procurement\PurchaseOrder;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PurchaseOrderController extends ApiController
{
    private const DETAIL_RELATIONS = [
        'lines.product:id,sku,name', 'supplier:id,code,name', 'requisition:id,number', 'approver:id,name', 'creator:id,name', 'warehouse:id,name',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $query = PurchaseOrder::query()->visibleBranches()->with(['supplier:id,code,name'])->withCount(['lines', 'goodsReceipts']);
        $this->applySearch($query, $request, ['number', 'notes', 'delivery_address']);
        $this->applyFilters($query, $request, ['status', 'supplier_id', 'requisition_id', 'branch_id', 'warehouse_id', 'currency']);
        $this->applyDateRange($query, $request, 'order_date');
        $this->applySort($query, $request, ['number', 'order_date', 'expected_date', 'status', 'total', 'created_at'], '-order_date');

        return $this->paginated($query->paginate($this->perPage($request)), PurchaseOrderResource::class);
    }

    public function store(StorePurchaseOrderRequest $request, SavePurchaseOrderAction $save): JsonResponse
    {
        Gate::authorize('create', PurchaseOrder::class);

        $order = $save->handle($request->validated(), $request->user());

        return (new PurchaseOrderResource($order->loadMissing(self::DETAIL_RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        Gate::authorize('view', $purchaseOrder);

        return new PurchaseOrderResource($purchaseOrder->load(self::DETAIL_RELATIONS)->loadCount('goodsReceipts'));
    }

    public function update(StorePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, SavePurchaseOrderAction $save): PurchaseOrderResource
    {
        Gate::authorize('update', $purchaseOrder);
        $this->ensureDraft($purchaseOrder);

        return new PurchaseOrderResource($save->handle($request->validated(), $request->user(), $purchaseOrder)->loadMissing(self::DETAIL_RELATIONS));
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        Gate::authorize('delete', $purchaseOrder);
        $this->ensureDraft($purchaseOrder);

        $purchaseOrder->delete();

        return $this->noContent();
    }

    public function submit(PurchaseOrder $purchaseOrder, AuditLogger $audit): PurchaseOrderResource
    {
        Gate::authorize('submit', $purchaseOrder);

        if (! $purchaseOrder->isDraft()) {
            abort(409, 'Only draft purchase orders can be submitted.');
        }

        if (! $purchaseOrder->lines()->exists()) {
            abort(409, 'Add at least one line before submitting.');
        }

        DB::transaction(function () use ($purchaseOrder, $audit): void {
            $purchaseOrder->forceFill(['status' => PurchaseOrderStatus::Submitted, 'submitted_at' => now()])->save();
            $audit->log('purchase.orders.submitted', $purchaseOrder, ['status' => PurchaseOrderStatus::Draft->value], ['status' => PurchaseOrderStatus::Submitted->value]);
        });

        return new PurchaseOrderResource($purchaseOrder->refresh()->loadMissing(self::DETAIL_RELATIONS));
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, ApprovePurchaseOrderAction $approve): PurchaseOrderResource
    {
        Gate::authorize('approve', $purchaseOrder);

        return new PurchaseOrderResource($approve->handle($purchaseOrder, $request->user())->loadMissing(self::DETAIL_RELATIONS));
    }

    public function cancel(ReasonRequest $request, PurchaseOrder $purchaseOrder, CancelPurchaseOrderAction $cancel): PurchaseOrderResource
    {
        Gate::authorize('cancel', $purchaseOrder);

        return new PurchaseOrderResource($cancel->handle($purchaseOrder, $request->validated('reason'))->loadMissing(self::DETAIL_RELATIONS));
    }

    /** Manual close: fully received, or nothing more is expected from the supplier. */
    public function close(PurchaseOrder $purchaseOrder, AuditLogger $audit): PurchaseOrderResource
    {
        Gate::authorize('close', $purchaseOrder);

        if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived, PurchaseOrderStatus::Received], true)) {
            abort(409, 'Only approved or received purchase orders can be closed.');
        }

        DB::transaction(function () use ($purchaseOrder, $audit): void {
            $before = $purchaseOrder->status->value;
            $purchaseOrder->forceFill(['status' => PurchaseOrderStatus::Closed, 'closed_at' => now()])->save();
            $audit->log('purchase.orders.closed', $purchaseOrder, ['status' => $before], ['status' => PurchaseOrderStatus::Closed->value]);
        });

        return new PurchaseOrderResource($purchaseOrder->refresh()->loadMissing(self::DETAIL_RELATIONS));
    }

    /** Marks the approved order as sent to the supplier (status unchanged). */
    public function send(PurchaseOrder $purchaseOrder, AuditLogger $audit): PurchaseOrderResource
    {
        Gate::authorize('send', $purchaseOrder);

        if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived], true)) {
            abort(409, 'Only approved purchase orders can be sent to the supplier.');
        }

        DB::transaction(function () use ($purchaseOrder, $audit): void {
            $purchaseOrder->forceFill(['sent_at' => now()])->save();
            $audit->log('purchase.orders.sent', $purchaseOrder, null, ['sent_at' => now()->toJSON(), 'supplier_id' => $purchaseOrder->supplier_id]);
        });

        return new PurchaseOrderResource($purchaseOrder->refresh()->loadMissing(self::DETAIL_RELATIONS));
    }

    private function ensureDraft(PurchaseOrder $order): void
    {
        if (! $order->isDraft()) {
            abort(409, 'Only draft purchase orders can be edited or deleted.');
        }
    }
}
