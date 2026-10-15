<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Actions\Inventory\ReceiveStockTransferAction;
use App\Actions\Inventory\SaveStockTransferAction;
use App\Actions\Inventory\ShipStockTransferAction;
use App\Enums\Inventory\StockTransferStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Inventory\StoreStockTransferRequest;
use App\Http\Resources\Inventory\StockTransferResource;
use App\Models\Inventory\StockTransfer;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class StockTransferController extends ApiController
{
    /** @var array<int, string> */
    private const WITH = ['fromWarehouse:id,name,code', 'toWarehouse:id,name,code', 'lines.product:id,sku,name'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', StockTransfer::class);

        $query = StockTransfer::query()->visibleBranches()->with(['fromWarehouse:id,name,code', 'toWarehouse:id,name,code'])->withCount('lines');
        $this->applySearch($query, $request, ['number', 'notes']);
        $this->applyFilters($query, $request, ['status', 'from_warehouse_id', 'to_warehouse_id']);
        $this->applyDateRange($query, $request, 'created_at');
        $this->applySort($query, $request, ['number', 'status', 'created_at', 'shipped_at', 'received_at'], '-created_at');

        return $this->paginated($query->paginate($this->perPage($request)), StockTransferResource::class);
    }

    public function store(StoreStockTransferRequest $request, SaveStockTransferAction $action): JsonResponse
    {
        Gate::authorize('create', StockTransfer::class);

        $transfer = $action->create($request->validated());

        return (new StockTransferResource($transfer->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(StockTransfer $stockTransfer): StockTransferResource
    {
        Gate::authorize('view', $stockTransfer);

        return new StockTransferResource($stockTransfer->load(self::WITH));
    }

    public function update(StoreStockTransferRequest $request, StockTransfer $stockTransfer, SaveStockTransferAction $action): StockTransferResource
    {
        Gate::authorize('update', $stockTransfer);

        $transfer = $action->update($stockTransfer, $request->validated());

        return new StockTransferResource($transfer->refresh()->load(self::WITH));
    }

    public function destroy(StockTransfer $stockTransfer): JsonResponse
    {
        Gate::authorize('delete', $stockTransfer);

        if (! $stockTransfer->isDraft()) {
            return $this->message('Only draft transfers can be deleted.', ['code' => 'conflict'], 409);
        }

        $stockTransfer->delete();

        return $this->noContent();
    }

    public function ship(StockTransfer $stockTransfer, ShipStockTransferAction $action): StockTransferResource
    {
        Gate::authorize('ship', $stockTransfer);

        return new StockTransferResource($action->handle($stockTransfer)->load(self::WITH));
    }

    public function receive(StockTransfer $stockTransfer, ReceiveStockTransferAction $action): StockTransferResource
    {
        Gate::authorize('receive', $stockTransfer);

        return new StockTransferResource($action->handle($stockTransfer)->load(self::WITH));
    }

    public function cancel(StockTransfer $stockTransfer): StockTransferResource
    {
        Gate::authorize('cancel', $stockTransfer);

        if (! $stockTransfer->isDraft()) {
            abort(409, 'Only draft transfers can be cancelled.');
        }

        $stockTransfer->forceFill(['status' => StockTransferStatus::Cancelled])->save();
        $this->audit->log('inventory.transfer_cancelled', $stockTransfer);

        return new StockTransferResource($stockTransfer->load(self::WITH));
    }
}
