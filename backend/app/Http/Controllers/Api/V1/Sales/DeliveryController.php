<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Actions\Sales\ConfirmDeliveryAction;
use App\Actions\Sales\CreateDeliveryAction;
use App\Enums\Sales\DeliveryStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Sales\StoreDeliveryRequest;
use App\Http\Resources\Sales\DeliveryResource;
use App\Models\Sales\Delivery;
use App\Models\Sales\SalesOrder;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Deliveries are managed under the sales order permissions. Tracking is
 * manual data entry (carrier/tracking number) — no live GPS.
 */
class DeliveryController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Delivery::class);

        $query = Delivery::query()->with('salesOrder.customer:id,name');
        $this->applySearch($query, $request, ['number', 'carrier', 'tracking_number']);
        $this->applyFilters($query, $request, ['status', 'sales_order_id', 'carrier']);
        $this->applyDateRange($query, $request, 'delivery_date');
        $this->applySort($query, $request, ['number', 'delivery_date', 'status', 'shipped_at', 'delivered_at', 'created_at']);

        return $this->paginated($query->paginate($this->perPage($request)), DeliveryResource::class);
    }

    public function show(Delivery $delivery): DeliveryResource
    {
        Gate::authorize('view', $delivery);

        return new DeliveryResource($this->present($delivery));
    }

    /** POST /sales-orders/{salesOrder}/deliveries */
    public function store(StoreDeliveryRequest $request, SalesOrder $salesOrder, CreateDeliveryAction $action): JsonResponse
    {
        Gate::authorize('update', $salesOrder);

        $delivery = $action->handle($salesOrder, $request->validated(), $request->user());

        return (new DeliveryResource($this->present($delivery)))->response()->setStatusCode(201);
    }

    public function ship(Request $request, Delivery $delivery): DeliveryResource
    {
        Gate::authorize('update', $delivery);

        if ($delivery->status !== DeliveryStatus::Pending) {
            throw new ConflictHttpException('Only pending deliveries can be shipped.');
        }

        $data = $request->validate([
            'carrier' => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ]);

        $delivery->forceFill($data + ['status' => DeliveryStatus::InTransit, 'shipped_at' => now()])->save();
        $this->audit->log('deliveries.shipped', $delivery, context: ['carrier' => $delivery->carrier, 'tracking_number' => $delivery->tracking_number]);

        return new DeliveryResource($this->present($delivery));
    }

    public function deliver(Delivery $delivery, ConfirmDeliveryAction $action): DeliveryResource
    {
        Gate::authorize('update', $delivery);

        $delivery = $action->handle($delivery);

        return new DeliveryResource($this->present($delivery));
    }

    public function cancel(Request $request, Delivery $delivery): DeliveryResource
    {
        Gate::authorize('update', $delivery);

        if (! $delivery->status->isOpen()) {
            throw new ConflictHttpException('Only pending or in-transit deliveries can be cancelled.');
        }

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $delivery->forceFill(['status' => DeliveryStatus::Cancelled, 'cancelled_at' => now()])->save();
        $this->audit->log('deliveries.cancelled', $delivery, context: ['reason' => $data['reason'] ?? null]);

        return new DeliveryResource($this->present($delivery));
    }

    private function present(Delivery $delivery): Delivery
    {
        return $delivery->loadMissing(['salesOrder.customer:id,name', 'lines.salesOrderLine.product:id,sku,name']);
    }
}
