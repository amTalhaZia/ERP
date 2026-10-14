<?php

namespace App\Actions\Sales;

use App\Enums\Sales\DeliveryStatus;
use App\Models\Sales\Delivery;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use App\Services\Sales\DeliveryQuantities;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Opens a Pending delivery against an Approved (or partially delivered) order.
 * Quantities were validated by the request; they are re-checked under a row
 * lock so two concurrent deliveries cannot over-allocate a line.
 */
class CreateDeliveryAction
{
    public function __construct(
        private readonly DocumentNumberer $numberer,
        private readonly DeliveryQuantities $quantities,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated StoreDeliveryRequest payload
     */
    public function handle(SalesOrder $order, array $data, ?User $actor = null): Delivery
    {
        return DB::transaction(function () use ($order, $data, $actor): Delivery {
            $order = SalesOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $order->status->acceptsDeliveries()) {
                throw new ConflictHttpException('Deliveries can only be created for approved sales orders.');
            }

            $remaining = $this->quantities->remainingByLine($order, [DeliveryStatus::Pending, DeliveryStatus::InTransit]);

            foreach ($data['lines'] as $line) {
                $lineId = (int) $line['sales_order_line_id'];
                $quantity = Money::normalize($line['quantity']);

                if (! isset($remaining[$lineId]) || Money::compare($quantity, $remaining[$lineId]) > 0) {
                    throw new ConflictHttpException('A line quantity exceeds the remaining undelivered quantity.');
                }
            }

            $delivery = Delivery::query()->create(Arr::except($data, ['lines']) + [
                'number' => $this->numberer->next('delivery'),
                'sales_order_id' => $order->id,
                'delivery_date' => today()->toDateString(),
                'status' => DeliveryStatus::Pending,
                'created_by' => $actor?->id,
            ]);

            $delivery->lines()->createMany(array_map(fn (array $line) => [
                'sales_order_line_id' => (int) $line['sales_order_line_id'],
                'quantity' => Money::normalize($line['quantity']),
            ], $data['lines']));

            return $delivery;
        });
    }
}
