<?php

namespace App\Services\Inventory;

use App\Actions\Inventory\RecordStockMovementAction;
use App\Enums\Inventory\StockMovementType;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Bridges commercial documents (deliveries, goods receipts, purchase returns)
 * to RecordStockMovementAction. Lines are filtered before posting:
 *
 *  - no product_id (free-text line)          → skipped silently,
 *  - product missing / service product       → skipped (services never stock),
 *  - serial-tracked goods                     → skipped with a warning: the
 *    commercial documents do not capture serial numbers, so those units must
 *    be moved through an Inventory receipt / issue,
 *  - zero / negative quantity                → skipped.
 *
 * Everything else is posted atomically with handleMany(); an
 * InsufficientStockException (409) or ValidationException (422, e.g. an
 * inactive product) propagates and rolls back the calling transaction.
 */
class DocumentStockRecorder
{
    public function __construct(private readonly RecordStockMovementAction $movements) {}

    /**
     * @param  array<int, array{product_id: int|string|null, quantity: string|int|float|null, unit_cost?: string|null, batch_number?: string|null, expires_at?: mixed}>  $lines
     * @return Collection<int, StockMovement>
     */
    public function record(
        Model $document,
        int $companyId,
        ?int $warehouseId,
        StockMovementType $type,
        array $lines,
        string $reason,
    ): Collection {
        $lines = array_values(array_filter(
            $lines,
            fn (array $line): bool => ! empty($line['product_id']) && Money::compare(Money::normalize($line['quantity'] ?? 0), Money::zero()) > 0,
        ));

        if ($lines === []) {
            return new Collection;
        }

        $products = Product::withoutTenancy()
            ->where('company_id', $companyId)
            ->whereKey(array_unique(array_map(fn (array $line): int => (int) $line['product_id'], $lines)))
            ->get()
            ->keyBy('id');

        $stockable = [];

        foreach ($lines as $line) {
            /** @var Product|null $product */
            $product = $products->get((int) $line['product_id']);

            if ($product === null || ! $product->isGoods()) {
                continue;
            }

            if ($product->track_serials) {
                Log::warning('Stock integration skipped a serial-tracked product: the document carries no serial numbers.', [
                    'document' => $document->getMorphClass(),
                    'document_id' => $document->getKey(),
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                ]);

                continue;
            }

            $stockable[] = array_filter([
                'product_id' => $product->id,
                'quantity' => Money::normalize($line['quantity']),
                'unit_cost' => isset($line['unit_cost']) && $line['unit_cost'] !== '' ? $line['unit_cost'] : null,
                'batch_number' => $line['batch_number'] ?? null,
                'expires_at' => $line['expires_at'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== '');
        }

        if ($stockable === []) {
            return new Collection;
        }

        if ($warehouseId === null) {
            Log::warning('Stock integration skipped a document without a warehouse; no stock was moved.', [
                'document' => $document->getMorphClass(),
                'document_id' => $document->getKey(),
                'type' => $type->value,
            ]);

            return new Collection;
        }

        return $this->movements->handleMany($stockable, [
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'reference_type' => $document->getMorphClass(),
            'reference_id' => $document->getKey(),
            'reason' => $reason,
            'moved_at' => now(),
        ]);
    }
}
