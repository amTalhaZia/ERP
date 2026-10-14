<?php

namespace App\Actions\Inventory;

use App\Enums\Inventory\StockTransferStatus;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StockTransferLine;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use Illuminate\Support\Facades\DB;

/**
 * Creates / updates DRAFT transfers with their lines. Lines are replaced
 * wholesale on update (the client sends the full set).
 */
class SaveStockTransferAction
{
    public function __construct(private readonly DocumentNumberer $numberer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): StockTransfer
    {
        return DB::transaction(function () use ($data): StockTransfer {
            $transfer = StockTransfer::query()->create([
                'number' => $this->numberer->next('stock_transfer'),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'status' => StockTransferStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->syncLines($transfer, $data['lines']);

            return $transfer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(StockTransfer $transfer, array $data): StockTransfer
    {
        abort_unless($transfer->isDraft(), 409, 'Only draft transfers can be edited.');

        return DB::transaction(function () use ($transfer, $data): StockTransfer {
            $transfer->fill(array_intersect_key($data, array_flip(['from_warehouse_id', 'to_warehouse_id', 'notes'])))->save();

            if (array_key_exists('lines', $data)) {
                $this->syncLines($transfer, $data['lines']);
            }

            return $transfer;
        });
    }

    /**
     * @param  array<int, array{product_id: int, quantity: string|int|float}>  $lines
     */
    private function syncLines(StockTransfer $transfer, array $lines): void
    {
        $transfer->lines()->delete();

        foreach ($lines as $line) {
            StockTransferLine::query()->create([
                'stock_transfer_id' => $transfer->id,
                'product_id' => $line['product_id'],
                'quantity' => Money::normalize($line['quantity']),
                'unit_cost' => Money::zero(),
            ]);
        }
    }
}
