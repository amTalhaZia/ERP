<?php

namespace App\Actions\Inventory;

use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockMovementType;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\Inventory\Batch;
use App\Models\Inventory\Product;
use App\Models\Inventory\SerialNumber;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\AverageCostCalculator;
use App\Services\Inventory\LowStockNotifier;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * THE single entry point for changing stock (ARCHITECTURE §8). Every module
 * (receipts, issues, transfers, adjustments, sales deliveries, purchase
 * receipts, returns) calls this; nothing else writes stock_levels or
 * stock_movements.
 *
 * Per movement, inside one DB transaction:
 *  1. lock the product row (serialises all movements of that product, which
 *     also makes the nullable-location stock_level find-or-create race-free),
 *  2. validate the product is active goods and the warehouse is in the tenant,
 *  3. lock (or create) the stock_level for (product, warehouse, location),
 *  4. inbound → weighted-average cost; outbound → cost at current average and
 *     InsufficientStockException (409) if on_hand < quantity,
 *  5. update quantity_on_hand, append the stock_movement with balance_after,
 *  6. attach batch / serial numbers, audit adjustments, queue a low-stock check.
 *
 * Quantity is passed as a positive magnitude; the sign is derived from `type`.
 *
 * @phpstan-type MovementData array{
 *   product_id: int,
 *   warehouse_id: int,
 *   type: StockMovementType|string,
 *   quantity: string|int|float,
 *   unit_cost?: string|int|float|null,
 *   location_id?: int|null,
 *   batch_id?: int|null,
 *   batch_number?: string|null,
 *   expires_at?: string|\DateTimeInterface|null,
 *   manufactured_at?: string|\DateTimeInterface|null,
 *   serials?: array<int, string>|null,
 *   reference_type?: string|null,
 *   reference_id?: int|null,
 *   reason?: string|null,
 *   moved_at?: string|\DateTimeInterface|null,
 *   created_by?: int|null
 * }
 */
class RecordStockMovementAction
{
    public function __construct(
        private readonly AverageCostCalculator $averageCost,
        private readonly LowStockNotifier $lowStock,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Record one movement.
     *
     * @param  MovementData  $data
     */
    public function handle(array $data): StockMovement
    {
        return DB::transaction(fn (): StockMovement => $this->record($data));
    }

    /**
     * Record several movements atomically (a document's lines). `$shared` is
     * merged under every line — typically warehouse_id, type, reference_type,
     * reference_id, reason and moved_at.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $shared
     * @return Collection<int, StockMovement>
     */
    public function handleMany(array $lines, array $shared = []): Collection
    {
        return DB::transaction(fn (): Collection => new Collection(
            array_map(fn (array $line): StockMovement => $this->record(array_merge($shared, $line)), array_values($lines)),
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(array $data): StockMovement
    {
        $type = $data['type'] instanceof StockMovementType ? $data['type'] : StockMovementType::from((string) $data['type']);
        $quantity = Money::abs(Money::normalize($data['quantity'] ?? null));

        if (Money::isZero($quantity)) {
            throw ValidationException::withMessages(['quantity' => 'The quantity must be greater than zero.']);
        }

        /** @var Product $product */
        $product = Product::query()->whereKey((int) $data['product_id'])->lockForUpdate()->firstOrFail();

        if (! $product->isGoods()) {
            throw ValidationException::withMessages(['product_id' => "Service products cannot be stocked ({$product->sku})."]);
        }

        if (! $product->is_active) {
            throw ValidationException::withMessages(['product_id' => "Product {$product->sku} is inactive."]);
        }

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()->whereKey((int) $data['warehouse_id'])->firstOrFail();
        $locationId = isset($data['location_id']) && $data['location_id'] !== '' ? (int) $data['location_id'] : null;

        $level = $this->lockLevel($product, $warehouse, $locationId);

        $onHand = Money::normalize($level->quantity_on_hand);
        $average = Money::normalize($level->average_cost);

        if ($type->isInbound()) {
            $unitCost = $this->inboundUnitCost($data, $average, $product);
            $newAverage = $this->averageCost->next($onHand, $average, $quantity, $unitCost);
            $newOnHand = Money::add($onHand, $quantity);
            $signedQuantity = $quantity;
        } else {
            if (Money::compare($onHand, $quantity) < 0) {
                throw new InsufficientStockException($product, $warehouse->id, $locationId, $onHand, $quantity);
            }

            $unitCost = $average;
            $newAverage = $average;
            $newOnHand = Money::sub($onHand, $quantity);
            $signedQuantity = Money::negate($quantity);
        }

        $level->forceFill([
            'quantity_on_hand' => $newOnHand,
            'average_cost' => $newAverage,
        ])->save();

        $batch = $this->resolveBatch($product, $data);
        $movedAt = isset($data['moved_at']) ? Carbon::parse($data['moved_at']) : now();

        $movement = StockMovement::query()->create([
            'company_id' => $product->company_id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'location_id' => $locationId,
            'batch_id' => $batch?->id,
            'type' => $type,
            'quantity' => $signedQuantity,
            'unit_cost' => $unitCost,
            'total_cost' => Money::mul($signedQuantity, $unitCost),
            'balance_after' => $newOnHand,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reason' => isset($data['reason']) ? mb_substr((string) $data['reason'], 0, 200) : null,
            'moved_at' => $movedAt,
            'created_by' => $data['created_by'] ?? auth()->id(),
        ]);

        $this->syncSerials($product, $warehouse, $batch, $type, $quantity, $data['serials'] ?? null);

        if (in_array($type, [StockMovementType::AdjustmentIn, StockMovementType::AdjustmentOut], true)) {
            $this->audit->log('inventory.adjusted', $movement, null, [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'warehouse_id' => $warehouse->id,
                'quantity' => $signedQuantity,
                'balance_after' => $newOnHand,
                'reason' => $data['reason'] ?? null,
            ], companyId: (int) $product->company_id, entityLabel: $product->sku);
        }

        if ($type->isOutbound()) {
            DB::afterCommit(fn () => $this->lowStock->notifyIfLow($product->refresh()));
        }

        return $movement;
    }

    private function lockLevel(Product $product, Warehouse $warehouse, ?int $locationId): StockLevel
    {
        $level = StockLevel::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if ($level !== null) {
            return $level;
        }

        return StockLevel::query()->create([
            'company_id' => $product->company_id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'location_id' => $locationId,
            'quantity_on_hand' => Money::zero(),
            'quantity_reserved' => Money::zero(),
            'average_cost' => Money::zero(),
        ]);
    }

    /**
     * Inbound cost precedence: explicit unit_cost → current average → product cost price.
     *
     * @param  array<string, mixed>  $data
     */
    private function inboundUnitCost(array $data, string $average, Product $product): string
    {
        if (isset($data['unit_cost']) && $data['unit_cost'] !== '') {
            $unitCost = Money::normalize($data['unit_cost']);

            if (Money::isNegative($unitCost)) {
                throw ValidationException::withMessages(['unit_cost' => 'The unit cost cannot be negative.']);
            }

            return $unitCost;
        }

        return Money::isZero($average) ? Money::normalize($product->cost_price) : $average;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveBatch(Product $product, array $data): ?Batch
    {
        if (! empty($data['batch_id'])) {
            return Batch::query()->where('product_id', $product->id)->whereKey((int) $data['batch_id'])->firstOrFail();
        }

        if (empty($data['batch_number'])) {
            return null;
        }

        $batch = Batch::query()->firstOrCreate(
            ['product_id' => $product->id, 'batch_number' => (string) $data['batch_number']],
            [
                'company_id' => $product->company_id,
                'manufactured_at' => $data['manufactured_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ],
        );

        if ($batch->wasRecentlyCreated === false && ($data['expires_at'] ?? null) && $batch->expires_at === null) {
            $batch->forceFill(['expires_at' => $data['expires_at']])->save();
        }

        return $batch;
    }

    /**
     * Serial numbers are mandatory for serial-tracked products on receipts,
     * issues, sales, purchases and returns; optional on transfers and
     * adjustments (those documents do not carry serials).
     *
     * @param  array<int, string>|null  $serials
     */
    private function syncSerials(Product $product, Warehouse $warehouse, ?Batch $batch, StockMovementType $type, string $quantity, ?array $serials): void
    {
        $serials = array_values(array_unique(array_filter(array_map(fn ($s) => trim((string) $s), $serials ?? []))));

        $documentCarriesSerials = ! in_array($type, [
            StockMovementType::TransferIn, StockMovementType::TransferOut,
            StockMovementType::AdjustmentIn, StockMovementType::AdjustmentOut, StockMovementType::Opening,
        ], true);

        if ($serials === []) {
            if ($product->track_serials && $documentCarriesSerials) {
                throw ValidationException::withMessages(['serials' => "Product {$product->sku} is serial-tracked: list one serial per unit."]);
            }

            return;
        }

        if (Money::compare(Money::normalize(count($serials)), $quantity) !== 0) {
            throw ValidationException::withMessages(['serials' => 'The number of serials must equal the quantity.']);
        }

        $status = $type->serialStatus();

        if ($type->isInbound()) {
            foreach ($serials as $serial) {
                $record = SerialNumber::query()->firstOrNew(['product_id' => $product->id, 'serial' => $serial]);

                if ($record->exists && $record->status === SerialStatus::InStock) {
                    throw ValidationException::withMessages(['serials' => "Serial {$serial} is already in stock."]);
                }

                $record->forceFill([
                    'company_id' => $product->company_id,
                    'warehouse_id' => $warehouse->id,
                    'batch_id' => $batch?->id ?? $record->batch_id,
                    'status' => $status,
                ])->save();
            }

            return;
        }

        $inStock = SerialNumber::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('status', SerialStatus::InStock)
            ->whereIn('serial', $serials)
            ->lockForUpdate()
            ->get()
            ->keyBy('serial');

        $missing = array_values(array_diff($serials, $inStock->keys()->all()));

        if ($missing !== []) {
            throw ValidationException::withMessages(['serials' => 'Not in stock at this warehouse: '.implode(', ', $missing)]);
        }

        foreach ($inStock as $record) {
            $record->forceFill(['status' => $status])->save();
        }
    }
}
