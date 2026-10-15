<?php

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Actions\Procurement\ConfirmPurchaseReturnAction;
use App\Actions\Procurement\SavePurchaseReturnAction;
use App\Enums\Procurement\PurchaseReturnStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Procurement\StorePurchaseReturnRequest;
use App\Http\Resources\Procurement\PurchaseReturnResource;
use App\Models\Procurement\PurchaseReturn;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PurchaseReturnController extends ApiController
{
    private const DETAIL_RELATIONS = ['lines.product:id,sku,name', 'purchaseOrder:id,number,supplier_id,status', 'goodsReceipt:id,number'];

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PurchaseReturn::class);

        $query = PurchaseReturn::query()->visibleBranches()->with(['purchaseOrder:id,number,supplier_id,status', 'goodsReceipt:id,number']);
        $this->applySearch($query, $request, ['number', 'notes']);
        $this->applyFilters($query, $request, ['status', 'reason', 'purchase_order_id', 'goods_receipt_id']);
        $this->applyDateRange($query, $request, 'return_date');
        $this->applySort($query, $request, ['number', 'return_date', 'status', 'total', 'created_at'], '-return_date');

        return $this->paginated($query->paginate($this->perPage($request)), PurchaseReturnResource::class);
    }

    public function store(StorePurchaseReturnRequest $request, SavePurchaseReturnAction $save): JsonResponse
    {
        Gate::authorize('create', PurchaseReturn::class);

        $return = $save->handle($request->validated(), $request->user());

        return (new PurchaseReturnResource($return->loadMissing(self::DETAIL_RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(PurchaseReturn $purchaseReturn): PurchaseReturnResource
    {
        Gate::authorize('view', $purchaseReturn);

        return new PurchaseReturnResource($purchaseReturn->loadMissing(self::DETAIL_RELATIONS));
    }

    public function update(StorePurchaseReturnRequest $request, PurchaseReturn $purchaseReturn, SavePurchaseReturnAction $save): PurchaseReturnResource
    {
        Gate::authorize('update', $purchaseReturn);
        $this->ensureDraft($purchaseReturn);

        return new PurchaseReturnResource($save->handle($request->validated(), $request->user(), $purchaseReturn)->loadMissing(self::DETAIL_RELATIONS));
    }

    public function destroy(PurchaseReturn $purchaseReturn): JsonResponse
    {
        Gate::authorize('delete', $purchaseReturn);
        $this->ensureDraft($purchaseReturn);

        $purchaseReturn->delete();

        return $this->noContent();
    }

    public function confirm(PurchaseReturn $purchaseReturn, ConfirmPurchaseReturnAction $confirm): PurchaseReturnResource
    {
        Gate::authorize('confirm', $purchaseReturn);

        return new PurchaseReturnResource($confirm->handle($purchaseReturn)->loadMissing(self::DETAIL_RELATIONS));
    }

    public function cancel(PurchaseReturn $purchaseReturn, AuditLogger $audit): PurchaseReturnResource
    {
        Gate::authorize('cancel', $purchaseReturn);
        $this->ensureDraft($purchaseReturn);

        DB::transaction(function () use ($purchaseReturn, $audit): void {
            $purchaseReturn->forceFill(['status' => PurchaseReturnStatus::Cancelled, 'cancelled_at' => now()])->save();
            $audit->log('purchase.returns.cancelled', $purchaseReturn, ['status' => PurchaseReturnStatus::Draft->value], ['status' => PurchaseReturnStatus::Cancelled->value]);
        });

        return new PurchaseReturnResource($purchaseReturn->refresh()->loadMissing(self::DETAIL_RELATIONS));
    }

    private function ensureDraft(PurchaseReturn $return): void
    {
        if (! $return->isDraft()) {
            abort(409, 'Only draft purchase returns can be changed.');
        }
    }
}
