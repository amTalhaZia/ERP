<?php

namespace App\Actions\Sales;

use App\Enums\Sales\DeliveryStatus;
use App\Enums\Sales\SalesOrderStatus;
use App\Events\Sales\DeliveryConfirmed;
use App\Models\Sales\Delivery;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Marks a delivery Delivered: increments quantity_delivered on each order line
 * under row locks, rolls the order status forward (PartiallyDelivered /
 * Delivered) and dispatches DeliveryConfirmed for the Inventory integration.
 */
class ConfirmDeliveryAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Delivery $delivery): Delivery
    {
        return DB::transaction(function () use ($delivery): Delivery {
            $delivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if (! $delivery->status->isOpen()) {
                throw new ConflictHttpException('Only pending or in-transit deliveries can be marked as delivered.');
            }

            $order = SalesOrder::query()->whereKey($delivery->sales_order_id)->lockForUpdate()->firstOrFail();

            if ($order->status === SalesOrderStatus::Cancelled) {
                throw new ConflictHttpException('The sales order has been cancelled.');
            }

            $deliveryLines = $delivery->lines()->get();
            $orderLines = SalesOrderLine::query()
                ->whereKey($deliveryLines->pluck('sales_order_line_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($deliveryLines as $line) {
                $orderLine = $orderLines[$line->sales_order_line_id] ?? throw new ConflictHttpException('Delivery line no longer matches an order line.');
                $newDelivered = Money::add((string) $orderLine->quantity_delivered, (string) $line->quantity);

                if (Money::compare($newDelivered, (string) $orderLine->quantity) > 0) {
                    throw new ConflictHttpException("Delivering line {$orderLine->line_no} would exceed the ordered quantity.");
                }

                $orderLine->quantity_delivered = $newDelivered;
                $orderLine->save();
            }

            $delivery->forceFill([
                'status' => DeliveryStatus::Delivered,
                'delivered_at' => now(),
                'shipped_at' => $delivery->shipped_at ?? now(),
            ])->save();

            $fullyDelivered = $order->lines()->get()->every(
                fn (SalesOrderLine $line) => Money::compare((string) $line->quantity_delivered, (string) $line->quantity) >= 0,
            );

            $order->forceFill([
                'status' => $fullyDelivered ? SalesOrderStatus::Delivered : SalesOrderStatus::PartiallyDelivered,
            ])->save();

            $this->audit->log('deliveries.delivered', $delivery, context: [
                'sales_order_id' => $order->id,
                'order_status' => $order->status->value,
            ]);

            $delivery->setRelation('salesOrder', $order);
            $delivery->load('lines.salesOrderLine');

            DeliveryConfirmed::dispatch($delivery);

            return $delivery;
        });
    }
}
