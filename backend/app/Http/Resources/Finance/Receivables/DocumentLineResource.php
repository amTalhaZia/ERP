<?php

namespace App\Http\Resources\Finance\Receivables;

use App\Models\Finance\BillLine;
use App\Models\Finance\InvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvoiceLine|BillLine */
class DocumentLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line_no' => $this->line_no,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
            ] : null),
            'description' => $this->description,
            'quantity' => (string) $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'discount_percent' => (string) $this->discount_percent,
            'tax_rate_id' => $this->tax_rate_id,
            'tax_rate' => (string) $this->tax_rate,
            'account_id' => $this->account_id,
            'account' => $this->whenLoaded('account', fn () => $this->account ? ['id' => $this->account->id, 'code' => $this->account->code, 'name' => $this->account->name] : null),
            'line_subtotal' => (string) $this->line_subtotal,
            'discount_amount' => (string) $this->discount_amount,
            'tax_amount' => (string) $this->tax_amount,
            'line_total' => (string) $this->line_total,
        ];
    }
}
