<?php

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Actions\Procurement\ConfirmGoodsReceiptAction;
use App\Actions\Procurement\SaveGoodsReceiptAction;
use App\Enums\Procurement\GoodsReceiptStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Procurement\StoreGoodsReceiptRequest;
use App\Http\Resources\Procurement\GoodsReceiptResource;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GoodsReceiptController extends ApiController
{
    private const DETAIL_RELATIONS = ['lines.product:id,sku,name', 'purchaseOrder:id,number,supplier_id,status', 'receiver:id,name', 'warehouse:id,name'];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', GoodsReceipt::class);

        $query = GoodsReceipt::query()->visibleBranches()->with(['purchaseOrder:id,number,supplier_id,status', 'receiver:id,name'])->withCount('lines');
        $this->applySearch($query, $request, ['number', 'supplier_delivery_note']);
        $this->applyFilters($query, $request, ['status', 'purchase_order_id', 'warehouse_id', 'received_by']);
        $this->applyDateRange($query, $request, 'received_date');
        $this->applySort($query, $request, ['number', 'received_date', 'status', 'created_at'], '-received_date');

        return $this->paginated($query->paginate($this->perPage($request)), GoodsReceiptResource::class);
    }

    /** POST purchase-orders/{purchase_order}/goods-receipts */
    public function storeForOrder(StoreGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder, SaveGoodsReceiptAction $save): JsonResponse
    {
        Gate::authorize('create', GoodsReceipt::class);
        Gate::authorize('receive', $purchaseOrder);

        $receipt = $save->handle($purchaseOrder, $request->validated(), $request->user());

        return (new GoodsReceiptResource($receipt->loadMissing(self::DETAIL_RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        Gate::authorize('view', $goodsReceipt);

        return new GoodsReceiptResource($goodsReceipt->loadMissing(self::DETAIL_RELATIONS));
    }

    public function update(StoreGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt, SaveGoodsReceiptAction $save): GoodsReceiptResource
    {
        Gate::authorize('update', $goodsReceipt);
        $this->ensureDraft($goodsReceipt);

        $order = $goodsReceipt->purchaseOrder()->firstOrFail();

        return new GoodsReceiptResource($save->handle($order, $request->validated(), $request->user(), $goodsReceipt)->loadMissing(self::DETAIL_RELATIONS));
    }

    public function destroy(GoodsReceipt $goodsReceipt): JsonResponse
    {
        Gate::authorize('delete', $goodsReceipt);
        $this->ensureDraft($goodsReceipt);

        $goodsReceipt->delete();

        return $this->noContent();
    }

    public function confirm(GoodsReceipt $goodsReceipt, ConfirmGoodsReceiptAction $confirm): GoodsReceiptResource
    {
        Gate::authorize('confirm', $goodsReceipt);

        return new GoodsReceiptResource($confirm->handle($goodsReceipt)->loadMissing(self::DETAIL_RELATIONS));
    }

    public function cancel(GoodsReceipt $goodsReceipt, AuditLogger $audit): GoodsReceiptResource
    {
        Gate::authorize('cancel', $goodsReceipt);
        $this->ensureDraft($goodsReceipt);

        DB::transaction(function () use ($goodsReceipt, $audit): void {
            $goodsReceipt->forceFill(['status' => GoodsReceiptStatus::Cancelled, 'cancelled_at' => now()])->save();
            $audit->log('goods.receipts.cancelled', $goodsReceipt, ['status' => GoodsReceiptStatus::Draft->value], ['status' => GoodsReceiptStatus::Cancelled->value]);
        });

        return new GoodsReceiptResource($goodsReceipt->refresh()->loadMissing(self::DETAIL_RELATIONS));
    }

    private function ensureDraft(GoodsReceipt $receipt): void
    {
        if (! $receipt->isDraft()) {
            abort(409, 'Only draft goods receipts can be changed.');
        }
    }
}
