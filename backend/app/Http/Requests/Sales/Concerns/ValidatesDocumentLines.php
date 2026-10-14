<?php

namespace App\Http\Requests\Sales\Concerns;

use App\Models\Inventory\Product;
use App\Support\Tenancy\Rules\TenantExists;

/**
 * Shared line validation for quotations and sales orders. Product ids must
 * reference a product of the current tenant; the line always carries its own description / price / uom snapshot.
 */
trait ValidatesDocumentLines
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function lineRules(bool $required): array
    {
        return [
            'lines' => [$required ? 'required' : 'sometimes', 'array', 'min:1', 'max:200'],
            'lines.*.product_id' => ['nullable', 'integer', new TenantExists(Product::class)],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.uom' => ['nullable', 'string', 'max:10'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
