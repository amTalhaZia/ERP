<?php

namespace App\Services\Sales;

use App\Enums\Sales\DeliveryStatus;
use App\Models\Sales\DeliveryLine;
use App\Models\Sales\SalesOrder;
use App\Support\Money\Money;

/**
 * Remaining-to-deliver arithmetic shared by validation and the delivery actions.
 */
final class DeliveryQuantities
{
    /**
     * Quantity still available per order line: ordered − delivered − quantities
     * sitting on deliveries in the given (open) statuses.
     *
     * @param  array<int, DeliveryStatus>  $reservedStatuses
     * @return array<int, string> keyed by sales_order_line_id
     */
    public function remainingByLine(SalesOrder $order, array $reservedStatuses = []): array
    {
        $lines = $order->lines()->get(['id', 'quantity', 'quantity_delivered']);

        $reserved = [];

        if ($reservedStatuses !== []) {
            $reserved = DeliveryLine::query()
                ->join('deliveries', 'deliveries.id', '=', 'delivery_lines.delivery_id')
                ->where('deliveries.sales_order_id', $order->id)
                ->whereIn('deliveries.status', array_map(fn (DeliveryStatus $s) => $s->value, $reservedStatuses))
                ->groupBy('delivery_lines.sales_order_line_id')
                ->selectRaw('delivery_lines.sales_order_line_id, SUM(delivery_lines.quantity) AS reserved')
                ->pluck('reserved', 'sales_order_line_id')
                ->all();
        }

        $remaining = [];

        foreach ($lines as $line) {
            $open = Money::sub((string) $line->quantity, (string) $line->quantity_delivered);
            $remaining[$line->id] = Money::sub($open, Money::normalize($reserved[$line->id] ?? 0));
        }

        return $remaining;
    }
}
