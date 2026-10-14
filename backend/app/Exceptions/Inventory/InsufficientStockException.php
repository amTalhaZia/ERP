<?php

namespace App\Exceptions\Inventory;

use App\Models\Inventory\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by RecordStockMovementAction when an outbound movement would take a
 * stock level below zero. Rendered as 409 so the client can distinguish it
 * from validation errors; the transaction that raised it is rolled back.
 */
class InsufficientStockException extends RuntimeException
{
    public const CODE = 'inventory.insufficient_stock';

    public function __construct(
        public readonly Product $product,
        public readonly int $warehouseId,
        public readonly ?int $locationId,
        public readonly string $available,
        public readonly string $requested,
    ) {
        parent::__construct(sprintf(
            'Insufficient stock for %s (%s): %s available, %s requested.',
            $product->name,
            $product->sku,
            $available,
            $requested,
        ));
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
            'details' => [
                'product_id' => $this->product->id,
                'sku' => $this->product->sku,
                'warehouse_id' => $this->warehouseId,
                'location_id' => $this->locationId,
                'available' => $this->available,
                'requested' => $this->requested,
            ],
        ], 409);
    }
}
