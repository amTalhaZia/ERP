<?php

namespace App\Actions\Procurement;

use App\Enums\Procurement\GoodsReceiptStatus;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Creates or replaces a draft goods receipt for a purchase order. Each line
 * must reference a line of that order and may not receive more than is still
 * outstanding (ordered − already received on confirmed receipts).
 *
 * Draft receipts do not reserve quantity; the check is repeated under lock
 * when the receipt is confirmed.
 */
class SaveGoodsReceiptAction
{
    public function __construct(private readonly DocumentNumberer $numberer) {}

    /**
     * @param  array<string, mixed>  $data  validated payload incl. `lines`
     */
    public function handle(PurchaseOrder $order, array $data, User $actor, ?GoodsReceipt $receipt = null): GoodsReceipt
    {
        if (! $order->status->canReceive()) {
            throw new ConflictHttpException('Goods can only be received against approved purchase orders.');
        }

        $orderLines = $order->lines()->get()->keyBy('id');
        $lines = $this->prepareLines($orderLines, collect($data['lines'] ?? [])->sortKeys()->values()->all());

        return DB::transaction(function () use ($order, $data, $actor, $receipt, $lines): GoodsReceipt {
            $header = Arr::except($data, ['lines']);
            $header['warehouse_id'] = $header['warehouse_id'] ?? $receipt?->warehouse_id ?? $order->warehouse_id;

            if ($receipt === null) {
                $receipt = GoodsReceipt::query()->create($header + [
                    'number' => $this->numberer->next('goods_receipt'),
                    'purchase_order_id' => $order->id,
                    'status' => GoodsReceiptStatus::Draft,
                    'received_by' => $actor->id,
                ]);
            } else {
                $receipt->fill($header)->save();
                $receipt->lines()->delete();
            }

            foreach ($lines as $line) {
                $receipt->lines()->create($line + ['company_id' => $receipt->company_id]);
            }

            return $receipt->refresh()->load(['lines', 'purchaseOrder:id,number,supplier_id,status', 'receiver:id,name']);
        });
    }

    /**
     * Validate the receipt lines against the order and build the snapshots.
     *
     * @param  Collection<int, PurchaseOrderLine>  $orderLines
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public static function prepareLines($orderLines, array $lines): array
    {
        $errors = [];
        $prepared = [];
        $requestedPerLine = [];

        foreach ($lines as $index => $line) {
            /** @var PurchaseOrderLine|null $orderLine */
            $orderLine = $orderLines->get((int) $line['purchase_order_line_id']);

            if ($orderLine === null) {
                $errors["lines.{$index}.purchase_order_line_id"] = ['The selected line does not belong to this purchase order.'];

                continue;
            }

            $received = Money::normalize($line['quantity_received']);
            $requestedPerLine[$orderLine->id] = Money::add($requestedPerLine[$orderLine->id] ?? Money::zero(), $received);

            if (Money::compare($requestedPerLine[$orderLine->id], $orderLine->quantityRemaining()) > 0) {
                $errors["lines.{$index}.quantity_received"] = [
                    sprintf('Only %s of %s remain to be received on this line.', $orderLine->quantityRemaining(), $orderLine->quantity),
                ];
            }

            $prepared[] = [
                'purchase_order_line_id' => $orderLine->id,
                'product_id' => $orderLine->product_id,
                'description' => $orderLine->description,
                'quantity_ordered' => (string) $orderLine->quantity,
                'quantity_received' => $received,
                'quantity_rejected' => Money::normalize($line['quantity_rejected'] ?? 0),
                'rejection_reason' => $line['rejection_reason'] ?? null,
                'unit_cost' => $orderLine->effectiveUnitCost(),
                'batch_number' => $line['batch_number'] ?? null,
                'expires_at' => $line['expires_at'] ?? null,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $prepared;
    }
}
