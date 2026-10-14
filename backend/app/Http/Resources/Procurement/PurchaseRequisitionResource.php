<?php

namespace App\Http\Resources\Procurement;

use App\Models\Procurement\PurchaseRequisition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseRequisition */
class PurchaseRequisitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'number' => $this->number,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'requester_id' => $this->requester_id,
            'requester' => $this->whenLoaded('requester', fn () => $this->requester ? ['id' => $this->requester->id, 'name' => $this->requester->name] : null),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => $this->department ? ['id' => $this->department->id, 'name' => $this->department->name] : null),
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'needed_by' => $this->needed_by?->toDateString(),
            'justification' => $this->justification,
            'estimated_total' => $this->estimated_total,
            'submitted_at' => $this->submitted_at?->toJSON(),
            'approver_id' => $this->approver_id,
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? ['id' => $this->approver->id, 'name' => $this->approver->name] : null),
            'approved_at' => $this->approved_at?->toJSON(),
            'rejected_at' => $this->rejected_at?->toJSON(),
            'rejection_reason' => $this->rejection_reason,
            'lines' => PurchaseRequisitionLineResource::collection($this->whenLoaded('lines')),
            'lines_count' => $this->whenCounted('lines'),
            'purchase_orders' => $this->whenLoaded('purchaseOrders', fn () => $this->purchaseOrders->map(fn ($order) => [
                'id' => $order->id, 'number' => $order->number, 'status' => $order->status?->value,
            ])->all()),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
