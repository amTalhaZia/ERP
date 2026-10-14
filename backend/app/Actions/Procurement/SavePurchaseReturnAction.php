<?php

namespace App\Actions\Procurement;

use App\Enums\Procurement\PurchaseReturnStatus;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderLine;
use App\Models\Procurement\PurchaseReturn;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or replaces a draft purchase return. A line can return at most what
 * was received and not yet returned on the referenced order line.
 */
class SavePurchaseReturnAction
{
    public function __construct(private readonly DocumentNumberer $numberer) {}

    /**
     * @param  array<string, mixed>  $data  validated payload incl. `lines`
     */
    public function handle(array $data, User $actor, ?PurchaseReturn $return = null): PurchaseReturn
    {
        /** @var PurchaseOrder $order */
        $order = PurchaseOrder::query()->findOrFail($data['purchase_order_id'] ?? $return?->purchase_order_id);

        if (! empty($data['goods_receipt_id'])) {
            $belongs = GoodsReceipt::query()->whereKey($data['goods_receipt_id'])->where('purchase_order_id', $order->id)->exists();

            if (! $belongs) {
                throw ValidationException::withMessages(['goods_receipt_id' => ['The goods receipt does not belong to this purchase order.']]);
            }
        }

        $orderLines = $order->lines()->get()->keyBy('id');
        $lines = self::prepareLines($orderLines, collect($data['lines'] ?? [])->sortKeys()->values()->all());

        return DB::transaction(function () use ($data, $actor, $return, $order, $lines): PurchaseReturn {
            $header = Arr::except($data, ['lines']);
            $header['total'] = Money::sum(array_column($lines, 'line_total'));

            if ($return === null) {
                $return = PurchaseReturn::query()->create($header + [
                    'number' => $this->numberer->next('purchase_return'),
                    'purchase_order_id' => $order->id,
                    'status' => PurchaseReturnStatus::Draft,
                    'created_by' => $actor->id,
                ]);
            } else {
                $return->fill($header)->save();
                $return->lines()->delete();
            }

            foreach ($lines as $line) {
                $return->lines()->create($line + ['company_id' => $return->company_id]);
            }

            return $return->refresh()->load(['lines', 'purchaseOrder:id,number,supplier_id,status', 'goodsReceipt:id,number']);
        });
    }

    /**
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

            $quantity = Money::normalize($line['quantity']);
            $requestedPerLine[$orderLine->id] = Money::add($requestedPerLine[$orderLine->id] ?? Money::zero(), $quantity);

            if (Money::compare($requestedPerLine[$orderLine->id], $orderLine->quantityReturnable()) > 0) {
                $errors["lines.{$index}.quantity"] = [
                    sprintf('Only %s received units can still be returned on this line.', $orderLine->quantityReturnable()),
                ];
            }

            $unitCost = isset($line['unit_cost']) && $line['unit_cost'] !== null && $line['unit_cost'] !== ''
                ? Money::normalize($line['unit_cost'])
                : $orderLine->effectiveUnitCost();

            $prepared[] = [
                'purchase_order_line_id' => $orderLine->id,
                'product_id' => $orderLine->product_id,
                'description' => $orderLine->description,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_total' => Money::mul($quantity, $unitCost),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $prepared;
    }
}
