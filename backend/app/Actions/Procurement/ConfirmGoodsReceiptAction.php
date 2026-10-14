<?php

namespace App\Actions\Procurement;

use App\Enums\Procurement\GoodsReceiptStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Events\Procurement\GoodsReceived;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\GoodsReceiptLine;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Draft → Confirmed. Under a row lock on the order lines, re-checks the
 * outstanding quantity, increments `quantity_received`, moves the order to
 * PartiallyReceived / Received and dispatches GoodsReceived so Inventory can
 * book the stock and AP can draft the bill.
 */
class ConfirmGoodsReceiptAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(GoodsReceipt $receipt): GoodsReceipt
    {
        if ($receipt->status !== GoodsReceiptStatus::Draft) {
            throw new ConflictHttpException('Only draft goods receipts can be confirmed.');
        }

        return DB::transaction(function () use ($receipt): GoodsReceipt {
            /** @var PurchaseOrder $order */
            $order = PurchaseOrder::query()->whereKey($receipt->purchase_order_id)->lockForUpdate()->firstOrFail();

            if (! $order->status->canReceive()) {
                throw new ConflictHttpException('The purchase order is no longer open for receiving.');
            }

            $orderLines = PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->lockForUpdate()->get()->keyBy('id');
            $receiptLines = $receipt->lines()->get();

            if ($receiptLines->isEmpty()) {
                throw new ConflictHttpException('A goods receipt needs at least one line.');
            }

            $errors = [];

            foreach ($receiptLines->values() as $index => $line) {
                /** @var GoodsReceiptLine $line */
                /** @var PurchaseOrderLine|null $orderLine */
                $orderLine = $orderLines->get($line->purchase_order_line_id);

                if ($orderLine === null || Money::compare((string) $line->quantity_received, $orderLine->quantityRemaining()) > 0) {
                    $errors["lines.{$index}.quantity_received"] = ['The received quantity exceeds what is still outstanding on the order.'];
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($receiptLines as $line) {
                /** @var PurchaseOrderLine $orderLine */
                $orderLine = $orderLines->get($line->purchase_order_line_id);
                $orderLine->forceFill([
                    'quantity_received' => Money::add((string) $orderLine->quantity_received, (string) $line->quantity_received),
                ])->save();
            }

            $fullyReceived = $orderLines->every(
                fn (PurchaseOrderLine $line) => Money::compare((string) $line->quantity_received, (string) $line->quantity) >= 0,
            );

            $previousStatus = $order->status->value;
            $order->forceFill(['status' => $fullyReceived ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived])->save();

            $receipt->forceFill([
                'status' => GoodsReceiptStatus::Confirmed,
                'confirmed_at' => now(),
            ])->save();

            $this->audit->log('goods.receipts.confirmed', $receipt, ['status' => GoodsReceiptStatus::Draft->value], [
                'status' => GoodsReceiptStatus::Confirmed->value,
                'purchase_order_id' => $order->id,
                'purchase_order_status' => ['from' => $previousStatus, 'to' => $order->status->value],
                'lines' => $receiptLines->map(fn (GoodsReceiptLine $line) => [
                    'purchase_order_line_id' => $line->purchase_order_line_id,
                    'product_id' => $line->product_id,
                    'quantity_received' => (string) $line->quantity_received,
                    'unit_cost' => (string) $line->unit_cost,
                ])->all(),
            ]);

            $receipt->load(['lines', 'purchaseOrder.supplier', 'receiver:id,name']);

            GoodsReceived::dispatch($receipt);

            return $receipt;
        });
    }
}
