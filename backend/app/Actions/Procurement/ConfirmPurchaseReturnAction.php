<?php

namespace App\Actions\Procurement;

use App\Enums\Procurement\PurchaseReturnStatus;
use App\Events\Procurement\PurchaseReturnConfirmed;
use App\Models\Procurement\PurchaseOrderLine;
use App\Models\Procurement\PurchaseReturn;
use App\Models\Procurement\PurchaseReturnLine;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Draft → Confirmed. Under a row lock re-checks the returnable quantity,
 * increments `quantity_returned` on the order lines and dispatches
 * PurchaseReturnConfirmed. The order's status is left unchanged.
 */
class ConfirmPurchaseReturnAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(PurchaseReturn $return): PurchaseReturn
    {
        if ($return->status !== PurchaseReturnStatus::Draft) {
            throw new ConflictHttpException('Only draft purchase returns can be confirmed.');
        }

        return DB::transaction(function () use ($return): PurchaseReturn {
            $orderLines = PurchaseOrderLine::query()
                ->where('purchase_order_id', $return->purchase_order_id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $returnLines = $return->lines()->get();

            if ($returnLines->isEmpty()) {
                throw new ConflictHttpException('A purchase return needs at least one line.');
            }

            $errors = [];

            foreach ($returnLines->values() as $index => $line) {
                /** @var PurchaseReturnLine $line */
                /** @var PurchaseOrderLine|null $orderLine */
                $orderLine = $orderLines->get($line->purchase_order_line_id);

                if ($orderLine === null || Money::compare((string) $line->quantity, $orderLine->quantityReturnable()) > 0) {
                    $errors["lines.{$index}.quantity"] = ['The returned quantity exceeds what was received on the order line.'];
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($returnLines as $line) {
                /** @var PurchaseOrderLine $orderLine */
                $orderLine = $orderLines->get($line->purchase_order_line_id);
                $orderLine->forceFill([
                    'quantity_returned' => Money::add((string) $orderLine->quantity_returned, (string) $line->quantity),
                ])->save();
            }

            $return->forceFill([
                'status' => PurchaseReturnStatus::Confirmed,
                'confirmed_at' => now(),
            ])->save();

            $this->audit->log('purchase.returns.confirmed', $return, ['status' => PurchaseReturnStatus::Draft->value], [
                'status' => PurchaseReturnStatus::Confirmed->value,
                'purchase_order_id' => $return->purchase_order_id,
                'total' => (string) $return->total,
            ]);

            $return->load(['lines', 'purchaseOrder.supplier', 'goodsReceipt:id,number,warehouse_id']);

            PurchaseReturnConfirmed::dispatch($return);

            return $return;
        });
    }
}
