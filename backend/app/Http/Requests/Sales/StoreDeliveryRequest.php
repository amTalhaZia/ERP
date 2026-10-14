<?php

namespace App\Http\Requests\Sales;

use App\Enums\Sales\DeliveryStatus;
use App\Models\Sales\SalesOrder;
use App\Services\Sales\DeliveryQuantities;
use App\Support\Money\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Lines must belong to the order on the route and each quantity may not exceed
 * what is still undelivered (minus quantities already on open deliveries).
 */
class StoreDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'delivery_date' => ['sometimes', 'date'],
            'carrier' => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.sales_order_line_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var SalesOrder|null $order */
                $order = $this->route('salesOrder');

                if ($order === null || $validator->errors()->has('lines')) {
                    return;
                }

                $remaining = app(DeliveryQuantities::class)->remainingByLine($order, [DeliveryStatus::Pending, DeliveryStatus::InTransit]);

                foreach ((array) $this->input('lines', []) as $index => $line) {
                    $lineId = (int) ($line['sales_order_line_id'] ?? 0);

                    if (! array_key_exists($lineId, $remaining)) {
                        $validator->errors()->add("lines.$index.sales_order_line_id", 'The selected line does not belong to this sales order.');

                        continue;
                    }

                    if (Money::compare(Money::normalize($line['quantity'] ?? 0), $remaining[$lineId]) > 0) {
                        $validator->errors()->add("lines.$index.quantity", "Quantity exceeds the remaining undelivered quantity ({$remaining[$lineId]}).");
                    }
                }
            },
        ];
    }
}
