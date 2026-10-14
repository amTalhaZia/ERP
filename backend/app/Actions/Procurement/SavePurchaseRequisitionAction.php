<?php

namespace App\Actions\Procurement;

use App\Enums\Procurement\RequisitionStatus;
use App\Models\Procurement\PurchaseRequisition;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates or replaces a draft requisition together with its lines.
 * Lines are rewritten wholesale: the payload is the full desired state.
 */
class SavePurchaseRequisitionAction
{
    public function __construct(private readonly DocumentNumberer $numberer) {}

    /**
     * @param  array<string, mixed>  $data  validated payload incl. `lines`
     */
    public function handle(array $data, User $actor, ?PurchaseRequisition $requisition = null): PurchaseRequisition
    {
        return DB::transaction(function () use ($data, $actor, $requisition): PurchaseRequisition {
            $lines = $this->prepareLines($data['lines'] ?? []);
            $header = Arr::except($data, ['lines']);
            $header['estimated_total'] = Money::sum(array_column($lines, 'line_total'));

            if ($requisition === null) {
                $requisition = PurchaseRequisition::query()->create($header + [
                    'number' => $this->numberer->next('purchase_requisition'),
                    'requester_id' => $actor->id,
                    'status' => RequisitionStatus::Draft,
                ]);
            } else {
                $requisition->fill($header)->save();
                $requisition->lines()->delete();
            }

            foreach ($lines as $line) {
                $requisition->lines()->create($line + ['company_id' => $requisition->company_id]);
            }

            return $requisition->refresh()->load(['lines', 'requester:id,name', 'department:id,name', 'branch:id,name']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function prepareLines(array $lines): array
    {
        $prepared = [];

        foreach (collect($lines)->sortKeys()->values() as $index => $line) {
            $quantity = Money::normalize($line['quantity']);
            $price = Money::normalize($line['estimated_unit_price'] ?? 0);

            $prepared[] = [
                'line_no' => $index + 1,
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $quantity,
                'uom' => $line['uom'] ?? null,
                'estimated_unit_price' => $price,
                'line_total' => Money::mul($quantity, $price),
            ];
        }

        return $prepared;
    }
}
