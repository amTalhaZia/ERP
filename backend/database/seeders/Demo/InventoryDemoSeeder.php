<?php

namespace Database\Seeders\Demo;

use App\Actions\Inventory\PostStockAdjustmentAction;
use App\Actions\Inventory\ReceiveStockTransferAction;
use App\Actions\Inventory\RecordStockMovementAction;
use App\Actions\Inventory\SaveStockAdjustmentAction;
use App\Actions\Inventory\SaveStockTransferAction;
use App\Actions\Inventory\ShipStockTransferAction;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;
use Database\Seeders\Demo\Concerns\SeedsDemoCompany;
use Illuminate\Database\Seeder;

/**
 * DEVELOPMENT ONLY. Categories, ~30 products (goods + services, one batch- and
 * one serial-tracked item), a second warehouse, opening stock through
 * RecordStockMovementAction, a few items below their reorder level, one
 * received transfer and one posted adjustment.
 */
class InventoryDemoSeeder extends Seeder
{
    use SeedsDemoCompany;

    /** Catalogues: categories [code => name]; products [name, category, uom, cost, sale, type, flag]. Prices in USD, scaled for PKR. */
    private const CATALOGUE = [
        'northwind.test' => [
            'prefix' => 'NW',
            'categories' => ['ELEC' => 'Office Electronics', 'FURN' => 'Furniture', 'STAT' => 'Stationery & Supplies', 'SERV' => 'Services'],
            'products' => [
                ['Wireless Keyboard', 'ELEC', 'pcs', 18, 39, 'goods', null],
                ['Wireless Mouse', 'ELEC', 'pcs', 9, 22, 'goods', null],
                ['24" LED Monitor', 'ELEC', 'pcs', 120, 219, 'goods', null],
                ['USB-C Docking Station', 'ELEC', 'pcs', 75, 149, 'goods', null],
                ['Laser Printer LP-200', 'ELEC', 'pcs', 140, 279, 'goods', 'serial'],
                ['Toner Cartridge TC-200', 'ELEC', 'pcs', 35, 79, 'goods', 'batch'],
                ['HD Webcam', 'ELEC', 'pcs', 28, 59, 'goods', null],
                ['Headset with Microphone', 'ELEC', 'pcs', 22, 49, 'goods', 'low'],
                ['Portable SSD 1TB', 'ELEC', 'pcs', 60, 119, 'goods', null],
                ['Wi-Fi Router AX1800', 'ELEC', 'pcs', 45, 99, 'goods', null],
                ['Cable Management Kit', 'ELEC', 'pcs', 7, 16, 'goods', null],
                ['Ergonomic Office Chair', 'FURN', 'pcs', 110, 239, 'goods', null],
                ['Height-Adjustable Desk', 'FURN', 'pcs', 260, 499, 'goods', null],
                ['3-Drawer Filing Cabinet', 'FURN', 'pcs', 85, 169, 'goods', 'low'],
                ['5-Tier Bookshelf', 'FURN', 'pcs', 70, 139, 'goods', null],
                ['Meeting Table (8 seats)', 'FURN', 'pcs', 310, 599, 'goods', null],
                ['LED Desk Lamp', 'FURN', 'pcs', 14, 32, 'goods', null],
                ['Monitor Arm', 'FURN', 'pcs', 32, 69, 'goods', null],
                ['A4 Copy Paper (5 reams)', 'STAT', 'box', 22, 38, 'goods', null],
                ['Ballpoint Pens (box of 50)', 'STAT', 'box', 6, 14, 'goods', null],
                ['Sticky Notes Pack', 'STAT', 'pcs', 3, 7, 'goods', null],
                ['Whiteboard Marker Set', 'STAT', 'pcs', 5, 12, 'goods', 'low'],
                ['Spiral Notebook A5', 'STAT', 'pcs', 2, 5, 'goods', null],
                ['Heavy-Duty Stapler', 'STAT', 'pcs', 6, 14, 'goods', null],
                ['Archive Box', 'STAT', 'pcs', 3, 8, 'goods', null],
                ['Desk Organizer', 'STAT', 'pcs', 9, 21, 'goods', null],
                ['Installation Service', 'SERV', 'hr', 0, 65, 'service', null],
                ['On-site IT Support', 'SERV', 'hr', 0, 85, 'service', null],
                ['Office Setup Consulting', 'SERV', 'hr', 0, 120, 'service', null],
                ['Annual Maintenance Plan', 'SERV', 'pcs', 0, 450, 'service', null],
            ],
        ],
        'indus.test' => [
            'prefix' => 'IN',
            'categories' => ['RAW' => 'Raw Materials', 'FG' => 'Finished Goods', 'SPR' => 'Spare Parts', 'SERV' => 'Services'],
            'products' => [
                ['Cotton Yarn 30s', 'RAW', 'kg', 3.2, 4.5, 'goods', null],
                ['Polyester Fibre', 'RAW', 'kg', 1.8, 2.6, 'goods', null],
                ['Reactive Dye - Blue', 'RAW', 'kg', 12, 18, 'goods', 'batch'],
                ['Industrial Starch', 'RAW', 'kg', 0.9, 1.4, 'goods', null],
                ['Packaging Carton', 'RAW', 'pcs', 0.6, 1.0, 'goods', null],
                ['Steel Wire Coil', 'RAW', 'kg', 1.1, 1.7, 'goods', null],
                ['PP Woven Bags', 'RAW', 'pcs', 0.25, 0.45, 'goods', null],
                ['Machine Oil', 'RAW', 'L', 4, 6.5, 'goods', 'low'],
                ['Cotton Bedsheet Set', 'FG', 'pcs', 9, 18, 'goods', null],
                ['Bath Towel', 'FG', 'pcs', 3.5, 7.5, 'goods', null],
                ['Denim Fabric', 'FG', 'm', 2.8, 5.2, 'goods', null],
                ['Workwear Overall', 'FG', 'pcs', 11, 22, 'goods', null],
                ['Canvas Tote Bag', 'FG', 'pcs', 2.2, 4.8, 'goods', null],
                ['Hand Towel', 'FG', 'pcs', 1.4, 3.2, 'goods', null],
                ['Duvet Cover', 'FG', 'pcs', 7.5, 15, 'goods', null],
                ['Cushion Cover', 'FG', 'pcs', 1.6, 3.6, 'goods', null],
                ['Bearing 6204', 'SPR', 'pcs', 2.5, 4.5, 'goods', null],
                ['V-Belt A-42', 'SPR', 'pcs', 3, 5.5, 'goods', 'low'],
                ['Spindle Assembly', 'SPR', 'pcs', 45, 80, 'goods', 'serial'],
                ['Sewing Needle Pack', 'SPR', 'pcs', 4, 7, 'goods', null],
                ['Electric Motor 0.5 HP', 'SPR', 'pcs', 70, 120, 'goods', null],
                ['Gear Coupling', 'SPR', 'pcs', 18, 32, 'goods', null],
                ['Air Filter Cartridge', 'SPR', 'pcs', 6, 11, 'goods', null],
                ['Conveyor Roller', 'SPR', 'pcs', 14, 26, 'goods', null],
                ['Pressure Gauge', 'SPR', 'pcs', 9, 16, 'goods', 'low'],
                ['Lubrication Pump', 'SPR', 'pcs', 28, 50, 'goods', null],
                ['Machine Maintenance', 'SERV', 'hr', 0, 25, 'service', null],
                ['Dyeing Job Work', 'SERV', 'kg', 0, 1.5, 'service', null],
                ['Calibration Service', 'SERV', 'pcs', 0, 60, 'service', null],
                ['Logistics Handling', 'SERV', 'pcs', 0, 40, 'service', null],
            ],
        ],
    ];

    public function run(Company $company): void
    {
        $this->bootDemo($company, 'inventory');

        $this->as('inventory', function (): void {
            $main = $this->mainWarehouse();
            $wh2 = $this->secondWarehouse();
            $products = $this->products();

            $this->openingStock($products, $main, $wh2);
            $this->transfer($products, $main, $wh2);
            $this->adjustment($products, $main);
        });

        $this->info(Product::query()->count().' products, '.StockLevel::query()->count().' stock levels');
    }

    private function secondWarehouse(): Warehouse
    {
        $branch = Branch::query()->where('is_head_office', false)->orderBy('id')->skip(1)->first()
            ?? Branch::query()->where('is_head_office', false)->orderBy('id')->first();

        return Warehouse::query()->create([
            'branch_id' => $branch?->id,
            'name' => ($branch?->city ?? 'Regional').' Warehouse',
            'code' => 'WH2',
            'address_line1' => '200 Logistics Park (demo)',
            'city' => $branch?->city,
            'country' => $this->company->country,
            'latitude' => $branch?->latitude,
            'longitude' => $branch?->longitude,
            'is_active' => true,
            'is_default' => false,
        ]);
    }

    /** @return list<array{product: Product, flag: ?string}> */
    private function products(): array
    {
        $catalogue = self::CATALOGUE[$this->domain] ?? self::CATALOGUE['northwind.test'];
        $uoms = UnitOfMeasure::query()->pluck('id', 'symbol');
        $categories = [];

        foreach ($catalogue['categories'] as $code => $name) {
            $categories[$code] = ProductCategory::query()->create(['name' => $name, 'code' => $code, 'is_active' => true]);
        }

        $result = [];

        foreach ($catalogue['products'] as $i => [$name, $category, $uom, $cost, $sale, $type, $flag]) {
            $isService = $type === 'service';
            $product = Product::query()->create([
                'category_id' => $categories[$category]->id,
                'uom_id' => $uoms[$uom] ?? null,
                'sku' => sprintf('%s-%s-%03d', $catalogue['prefix'], $category, $i + 1),
                'barcode' => $isService ? null : fake()->ean13(),
                'name' => $name,
                'description' => "{$name} (demo catalogue item).",
                'type' => $type,
                'cost_price' => $this->amount($cost),
                'sale_price' => $this->amount($sale),
                'currency' => $this->company->currency,
                'tax_rate' => '15',
                'reorder_level' => $isService ? '0' : (string) ($flag === 'low' ? 15 : $this->reorderLevel($cost)),
                'reorder_quantity' => $isService ? '0' : (string) ($this->reorderLevel($cost) * 3),
                'track_batches' => $flag === 'batch',
                'track_serials' => $flag === 'serial',
                'is_active' => true,
                'attributes' => ['demo_flag' => $flag],
            ]);

            $result[] = ['product' => $product, 'flag' => $flag];
        }

        return $result;
    }

    private function reorderLevel(float|int $cost): int
    {
        return match (true) {
            $cost < 5 => 80,
            $cost < 50 => 20,
            default => 5,
        };
    }

    private function openingQuantity(float|int $cost): int
    {
        return match (true) {
            $cost < 5 => fake()->numberBetween(500, 900),
            $cost < 50 => fake()->numberBetween(100, 220),
            default => fake()->numberBetween(30, 70),
        };
    }

    /**
     * @param  list<array{product: Product, flag: ?string}>  $products
     */
    private function openingStock(array $products, Warehouse $main, Warehouse $wh2): void
    {
        $record = app(RecordStockMovementAction::class);
        $openingAt = $this->fiscalStart()->setTime(8, 0);

        foreach ($products as $i => ['product' => $product, 'flag' => $flag]) {
            if (! $product->isGoods()) {
                continue;
            }

            $cost = (float) $product->cost_price / $this->scale();
            $quantity = $flag === 'low' ? 8 : $this->openingQuantity($cost);
            $data = [
                'product_id' => $product->id,
                'warehouse_id' => $main->id,
                'type' => 'opening',
                'quantity' => $quantity,
                'unit_cost' => (string) $product->cost_price,
                'reason' => 'Opening balance (demo)',
                'moved_at' => $openingAt,
            ];

            if ($flag === 'serial') {
                $data['quantity'] = 6;
                $data['serials'] = array_map(fn (int $n) => sprintf('%s-SN-%04d', $product->sku, $n), range(1, 6));
            } elseif ($flag === 'batch') {
                $data['batch_number'] = 'LOT-'.$this->fiscalStart()->format('Ym').'-A';
                $data['expires_at'] = $this->fiscalStart()->addYears(2)->toDateString();
            }

            $record->handle($data);

            if ($flag === null && $i % 2 === 0) {
                $record->handle([
                    'product_id' => $product->id,
                    'warehouse_id' => $wh2->id,
                    'type' => 'opening',
                    'quantity' => max(5, intdiv($quantity, 3)),
                    'unit_cost' => (string) $product->cost_price,
                    'reason' => 'Opening balance (demo)',
                    'moved_at' => $openingAt,
                ]);
            }
        }
    }

    /**
     * @param  list<array{product: Product, flag: ?string}>  $products
     */
    private function transfer(array $products, Warehouse $main, Warehouse $wh2): void
    {
        $lines = collect($products)
            ->filter(fn (array $p) => $p['flag'] === null && $p['product']->isGoods())
            ->take(3)
            ->map(fn (array $p) => ['product_id' => $p['product']->id, 'quantity' => 10])
            ->values()
            ->all();

        $shippedAt = $this->weekday($this->today()->subDays(20))->setTime(10, 0);

        $transfer = $this->at($shippedAt, fn () => app(SaveStockTransferAction::class)->create([
            'from_warehouse_id' => $main->id,
            'to_warehouse_id' => $wh2->id,
            'notes' => 'Replenish regional warehouse (demo).',
            'lines' => $lines,
        ]));

        $this->at($shippedAt, fn () => app(ShipStockTransferAction::class)->handle($transfer));
        $this->at($shippedAt->copy()->addDays(2), fn () => app(ReceiveStockTransferAction::class)->handle($transfer->refresh()));
    }

    /**
     * @param  list<array{product: Product, flag: ?string}>  $products
     */
    private function adjustment(array $products, Warehouse $main): void
    {
        $low = collect($products)->first(fn (array $p) => $p['flag'] === 'low')['product'];
        $normal = collect($products)->filter(fn (array $p) => $p['flag'] === null && $p['product']->isGoods())->values()->get(5)['product'];
        $countedAt = $this->weekday($this->today()->subDays(7))->setTime(16, 0);

        $adjustment = $this->at($countedAt, fn () => app(SaveStockAdjustmentAction::class)->create([
            'warehouse_id' => $main->id,
            'reason' => 'count',
            'notes' => 'Monthly cycle count (demo): two damaged units written off, three units found.',
            'lines' => [
                ['product_id' => $low->id, 'counted_quantity' => (string) ((float) SaveStockAdjustmentAction::systemQuantity($low->id, $main->id) - 2)],
                ['product_id' => $normal->id, 'counted_quantity' => (string) ((float) SaveStockAdjustmentAction::systemQuantity($normal->id, $main->id) + 3)],
            ],
        ]));

        $this->at($countedAt, fn () => app(PostStockAdjustmentAction::class)->handle($adjustment));
    }
}
