<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Actions\Sales\UpsertSalesOrderAction;
use App\Enums\Sales\DeliveryStatus;
use App\Enums\Sales\SalesOrderStatus;
use App\Events\Sales\SalesOrderConfirmed;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Sales\StoreSalesOrderRequest;
use App\Http\Resources\Sales\SalesOrderResource;
use App\Models\Sales\SalesOrder;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SalesOrderController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SalesOrder::class);

        $query = SalesOrder::query()->visibleBranches()->with(['customer', 'salesRep'])->withCount(['lines', 'deliveries']);
        $this->applySearch($query, $request, ['number', 'shipping_name', 'notes']);
        $this->applyFilters($query, $request, ['status', 'customer_id', 'sales_rep_id', 'warehouse_id', 'quotation_id', 'currency']);
        $this->applyDateRange($query, $request, 'order_date');
        $this->applySort($query, $request, ['number', 'order_date', 'expected_delivery_date', 'status', 'total', 'created_at']);

        return $this->paginated($query->paginate($this->perPage($request)), SalesOrderResource::class);
    }

    public function store(StoreSalesOrderRequest $request, UpsertSalesOrderAction $action): JsonResponse
    {
        Gate::authorize('create', SalesOrder::class);

        $order = $action->handle($request->validated(), null, $request->user());

        return (new SalesOrderResource($this->present($order)))->response()->setStatusCode(201);
    }

    public function show(SalesOrder $salesOrder): SalesOrderResource
    {
        Gate::authorize('view', $salesOrder);

        return new SalesOrderResource($this->present($salesOrder)->load(['approver', 'deliveries.lines.salesOrderLine.product:id,sku,name']));
    }

    public function update(StoreSalesOrderRequest $request, SalesOrder $salesOrder, UpsertSalesOrderAction $action): SalesOrderResource
    {
        Gate::authorize('update', $salesOrder);
        $this->ensureStatus($salesOrder, [SalesOrderStatus::Draft], 'edited');

        $order = $action->handle($request->validated(), $salesOrder, $request->user());

        return new SalesOrderResource($this->present($order->refresh()));
    }

    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        Gate::authorize('delete', $salesOrder);
        $this->ensureStatus($salesOrder, [SalesOrderStatus::Draft], 'deleted');

        $salesOrder->delete();

        return $this->noContent();
    }

    /** Draft → Confirmed; announces the order to Inventory/AR via SalesOrderConfirmed. */
    public function confirm(SalesOrder $salesOrder): SalesOrderResource
    {
        Gate::authorize('update', $salesOrder);
        $this->ensureStatus($salesOrder, [SalesOrderStatus::Draft], 'confirmed');

        if (! $salesOrder->lines()->exists()) {
            throw new ConflictHttpException('Add at least one line before confirming the sales order.');
        }

        DB::transaction(function () use ($salesOrder): void {
            $salesOrder->forceFill(['status' => SalesOrderStatus::Confirmed, 'confirmed_at' => now()])->save();
            $this->audit->log('sales_orders.confirmed', $salesOrder);

            SalesOrderConfirmed::dispatch($salesOrder->load(['lines', 'customer']));
        });

        return new SalesOrderResource($this->present($salesOrder));
    }

    /** Confirmed → Approved (sales_orders.approve). */
    public function approve(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        Gate::authorize('approve', $salesOrder);
        $this->ensureStatus($salesOrder, [SalesOrderStatus::Confirmed], 'approved');

        $salesOrder->forceFill([
            'status' => SalesOrderStatus::Approved,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ])->save();
        $this->audit->log('sales_orders.approved', $salesOrder);

        return new SalesOrderResource($this->present($salesOrder)->load('approver'));
    }

    /** Cancel unless anything has already been delivered. Open deliveries are cancelled too. */
    public function cancel(Request $request, SalesOrder $salesOrder): SalesOrderResource
    {
        Gate::authorize('update', $salesOrder);
        $this->ensureStatus($salesOrder, [
            SalesOrderStatus::Draft, SalesOrderStatus::Confirmed, SalesOrderStatus::Approved, SalesOrderStatus::PartiallyDelivered,
        ], 'cancelled');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($salesOrder, $data): void {
            $delivered = $salesOrder->lines()->lockForUpdate()->get()
                ->contains(fn ($line) => Money::compare((string) $line->quantity_delivered, '0') > 0);

            if ($delivered) {
                throw new ConflictHttpException('This order has delivered quantities and can no longer be cancelled.');
            }

            $salesOrder->deliveries()
                ->whereIn('status', [DeliveryStatus::Pending->value, DeliveryStatus::InTransit->value])
                ->update(['status' => DeliveryStatus::Cancelled->value, 'cancelled_at' => now(), 'updated_at' => now()]);

            $salesOrder->forceFill([
                'status' => SalesOrderStatus::Cancelled,
                'cancelled_reason' => $data['reason'],
                'cancelled_at' => now(),
            ])->save();
            $this->audit->log('sales_orders.cancelled', $salesOrder, context: ['reason' => $data['reason']]);
        });

        return new SalesOrderResource($this->present($salesOrder));
    }

    private function present(SalesOrder $order): SalesOrder
    {
        return $order->loadMissing(['customer', 'salesRep', 'lines.product:id,sku,name', 'warehouse:id,name']);
    }

    /**
     * @param  array<int, SalesOrderStatus>  $allowed
     */
    private function ensureStatus(SalesOrder $order, array $allowed, string $verb): void
    {
        if (! in_array($order->status, $allowed, true)) {
            throw new ConflictHttpException(sprintf(
                'A %s sales order cannot be %s.', str_replace('_', ' ', $order->status->value), $verb,
            ));
        }
    }
}
