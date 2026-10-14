<?php

namespace Database\Seeders\Demo;

use App\Actions\Procurement\ApprovePurchaseOrderAction;
use App\Actions\Procurement\ConfirmGoodsReceiptAction;
use App\Actions\Procurement\ConfirmPurchaseReturnAction;
use App\Actions\Procurement\CreatePurchaseOrderFromRequisitionAction;
use App\Actions\Procurement\DecidePurchaseRequisitionAction;
use App\Actions\Procurement\SaveGoodsReceiptAction;
use App\Actions\Procurement\SavePurchaseOrderAction;
use App\Actions\Procurement\SavePurchaseRequisitionAction;
use App\Actions\Procurement\SavePurchaseReturnAction;
use App\Actions\Procurement\SubmitPurchaseRequisitionAction;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Inventory\Product;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequisition;
use App\Models\Procurement\Supplier;
use App\Models\Procurement\SupplierContact;
use App\Support\Audit\AuditLogger;
use App\Support\Numbering\DocumentNumberer;
use Database\Seeders\Demo\Concerns\SeedsDemoCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DEVELOPMENT ONLY. Approved suppliers, requisitions (ordered / approved /
 * pending / rejected / draft), purchase orders over the history window that
 * are approved by admin@ and received into MAIN (stock comes in through the
 * goods-receipt integration), one PO awaiting approval and a purchase return.
 */
class ProcurementDemoSeeder extends Seeder
{
    use SeedsDemoCompany;

    private const SUPPLIERS = [
        'northwind.test' => [
            'Apex Office Supply Co.', 'Bluegate Electronics Wholesale', 'Cedar Furniture Works', 'Delta Paper Mills',
            'Evergreen Tech Distributors', 'Fulton Freight Services', 'Granite Desk Systems', 'Harbor Stationers',
            'Ironwood Seating', 'Juniper Peripherals',
        ],
        'indus.test' => [
            'Ravi Cotton Traders', 'Sutlej Fibre Mills', 'Chenab Dyes & Chemicals', 'Margalla Packaging',
            'Indus Valley Steel Wire', 'Kohinoor Spares House', 'Thar Industrial Oils', 'Makran Bearings',
            'Potohar Motors & Pumps', 'Swat Textile Accessories',
        ],
    ];

    /** @var Collection<int, Product> */
    private Collection $goods;

    public function run(Company $company): void
    {
        $this->bootDemo($company, 'procurement');
        $this->goods = $this->regularGoods();

        $suppliers = $this->suppliers();
        $orders = $this->purchaseOrders($suppliers);
        $this->pendingDocuments($suppliers);
        $this->purchaseReturn($orders[0]);

        $this->info($suppliers->count().' suppliers, '.PurchaseOrder::query()->count().' purchase orders');
    }

    /** @return Collection<int, Supplier> */
    private function suppliers(): Collection
    {
        $names = self::SUPPLIERS[$this->domain] ?? self::SUPPLIERS['northwind.test'];
        $hq = Branch::query()->where('is_head_office', true)->firstOrFail();
        $admin = $this->demoUser('admin');
        $numberer = app(DocumentNumberer::class);
        $suppliers = collect();

        $this->as('procurement', function () use ($names, $hq, $admin, $numberer, &$suppliers): void {
            foreach ($names as $i => $name) {
                $slug = Str::slug(Str::before($name, ' &'), '');
                $supplier = Supplier::query()->create([
                    'code' => $numberer->next('supplier', yearly: false, prefix: 'SUP', padding: 5),
                    'name' => $name,
                    'type' => 'company',
                    'email' => 'orders@'.Str::limit($slug, 20, '').'.example',
                    'phone' => $this->phone(3),
                    'website' => 'https://'.Str::limit($slug, 20, '').'.example',
                    'tax_number' => fake()->numerify('TX-#######'),
                    'currency' => $this->company->currency,
                    'payment_terms_days' => [30, 30, 45, 15, 30][$i % 5],
                    'credit_limit' => $this->amount(50000),
                    'address_line1' => fake()->buildingNumber().' Industrial Road',
                    'city' => $this->company->city,
                    'country' => $this->company->country,
                    'latitude' => round((float) $hq->latitude + fake()->randomFloat(4, -0.2, 0.2), 7),
                    'longitude' => round((float) $hq->longitude + fake()->randomFloat(4, -0.2, 0.2), 7),
                    'bank_name' => 'Demo Bank (fictional)',
                    'rating' => fake()->numberBetween(3, 5),
                    'lead_time_days' => fake()->numberBetween(3, 14),
                    'notes' => 'Fictional demo supplier.',
                    'is_active' => true,
                ]);

                $supplier->forceFill(['is_approved' => true, 'approved_by' => $admin->id, 'approved_at' => $this->historyStart()])->save();

                SupplierContact::query()->create([
                    'supplier_id' => $supplier->id,
                    'name' => fake()->name(),
                    'title' => fake()->randomElement(['Account Manager', 'Sales Executive', 'Owner']),
                    'email' => 'contact@'.Str::limit($slug, 20, '').'.example',
                    'phone' => $this->phone(4),
                    'is_primary' => true,
                ]);

                $suppliers->push($supplier);
            }
        });

        return $suppliers;
    }

    /**
     * Eight orders spread across the history window: four raised from approved
     * requisitions, four direct; all approved and received (the latest one
     * only partially).
     *
     * @param  Collection<int, Supplier>  $suppliers
     * @return list<PurchaseOrder>
     */
    private function purchaseOrders(Collection $suppliers): array
    {
        $start = $this->historyStart()->addDays(3);
        $span = max(30, $start->diffInDays($this->today()->subDays(12)));
        $orders = [];

        for ($i = 0; $i < 8; $i++) {
            $orderDate = $this->weekday($start->copy()->addDays((int) round($span * $i / 8)))->setTime(10, 0);
            $supplier = $suppliers[$i % $suppliers->count()];
            $products = $this->goods->slice(($i * 3) % max(1, $this->goods->count() - 3), 3)->values();

            $order = $i % 2 === 0
                ? $this->orderFromRequisition($products, $supplier, $orderDate)
                : $this->directOrder($products, $supplier, $orderDate);

            $this->submitAndApprove($order, $orderDate);
            $this->receive($order, $orderDate->copy()->addDays(fake()->numberBetween(4, 9)), partial: $i === 7);

            $orders[] = $order->refresh();
        }

        return $orders;
    }

    /** @param  Collection<int, Product>  $products */
    private function orderFromRequisition(Collection $products, Supplier $supplier, Carbon $orderDate): PurchaseOrder
    {
        $requisition = $this->requisition($products, $orderDate->copy()->subDays(4), 'approve');

        return $this->at($orderDate, fn () => $this->as('procurement', fn ($user) => app(CreatePurchaseOrderFromRequisitionAction::class)->handle(
            $requisition->load('lines'),
            $supplier,
            $user,
            ['warehouse_id' => $this->mainWarehouse()->id, 'terms' => 'Delivery to main warehouse. Payment per supplier terms.'],
        )));
    }

    /** @param  Collection<int, Product>  $products */
    private function directOrder(Collection $products, Supplier $supplier, Carbon $orderDate): PurchaseOrder
    {
        return $this->at($orderDate, fn () => $this->as('procurement', fn ($user) => app(SavePurchaseOrderAction::class)->handle([
            'supplier_id' => $supplier->id,
            'branch_id' => Branch::query()->where('is_head_office', true)->value('id'),
            'order_date' => $orderDate->toDateString(),
            'expected_date' => $orderDate->copy()->addDays(7)->toDateString(),
            'warehouse_id' => $this->mainWarehouse()->id,
            'notes' => 'Routine replenishment (demo).',
            'lines' => $this->lines($products, withTax: true),
        ], $user)));
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private function lines(Collection $products, bool $withTax): array
    {
        return $products->map(fn (Product $p) => [
            'product_id' => $p->id,
            'description' => $p->name,
            'quantity' => $this->purchaseQuantity($p),
            'unit_price' => (string) $p->cost_price,
            'discount_percent' => 0,
            'tax_rate' => $withTax ? 15 : 0,
        ])->all();
    }

    private function purchaseQuantity(Product $product): int
    {
        $cost = (float) $product->cost_price / $this->scale();

        return match (true) {
            $cost < 5 => fake()->numberBetween(20, 40) * 10,
            $cost < 50 => fake()->numberBetween(4, 10) * 10,
            default => fake()->numberBetween(5, 15),
        };
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function requisition(Collection $products, Carbon $date, string $outcome): PurchaseRequisition
    {
        $date = $this->clampDate($date)->setTime(9, 30);
        $ops = Department::query()->where('code', 'OPS')->value('id');

        $requisition = $this->at($date, fn () => $this->as('manager', fn ($user) => app(SavePurchaseRequisitionAction::class)->handle([
            'department_id' => $ops,
            'branch_id' => Branch::query()->where('is_head_office', true)->value('id'),
            'needed_by' => $date->copy()->addDays(14)->toDateString(),
            'priority' => fake()->randomElement(['normal', 'normal', 'high', 'low']),
            'justification' => 'Stock replenishment for upcoming customer orders (demo).',
            'lines' => $products->map(fn (Product $p) => [
                'product_id' => $p->id,
                'description' => $p->name,
                'quantity' => $this->purchaseQuantity($p),
                'estimated_unit_price' => (string) $p->cost_price,
            ])->all(),
        ], $user)));

        if ($outcome === 'draft') {
            return $requisition;
        }

        $this->at($date, fn () => app(SubmitPurchaseRequisitionAction::class)->handle($requisition));

        $decide = app(DecidePurchaseRequisitionAction::class);
        $decidedAt = $date->copy()->addDay();

        return match ($outcome) {
            'approve' => $this->at($decidedAt, fn () => $this->as('admin', fn ($user) => $decide->approve($requisition->refresh(), $user))),
            'reject' => $this->at($decidedAt, fn () => $this->as('admin', fn ($user) => $decide->reject($requisition->refresh(), $user, 'Budget for this quarter is exhausted — resubmit next quarter.'))),
            default => $requisition->refresh(),
        };
    }

    /** Draft → Submitted (as the controller does) by procurement@, then approved by admin@. */
    private function submitAndApprove(PurchaseOrder $order, Carbon $date, bool $approve = true): void
    {
        $this->at($date, fn () => $this->as('procurement', function () use ($order): void {
            DB::transaction(function () use ($order): void {
                $order->forceFill(['status' => PurchaseOrderStatus::Submitted, 'submitted_at' => now()])->save();
                app(AuditLogger::class)->log('purchase.orders.submitted', $order, ['status' => PurchaseOrderStatus::Draft->value], ['status' => PurchaseOrderStatus::Submitted->value]);
            });
        }));

        if ($approve) {
            $this->at($date->copy()->addHours(5), fn () => $this->as('admin', fn ($user) => app(ApprovePurchaseOrderAction::class)->handle($order->refresh(), $user)));
        }
    }

    private function receive(PurchaseOrder $order, Carbon $date, bool $partial = false): GoodsReceipt
    {
        $date = $this->clampDate($date)->setTime(11, 0);
        $lines = $order->lines()->get()->map(fn ($line) => [
            'purchase_order_line_id' => $line->id,
            'quantity_received' => $partial ? (string) max(1, intdiv((int) $line->quantity, 2)) : (string) $line->quantity,
        ])->all();

        return $this->at($date, fn () => $this->as('inventory', function ($user) use ($order, $date, $lines): GoodsReceipt {
            $receipt = app(SaveGoodsReceiptAction::class)->handle($order->refresh(), [
                'received_date' => $date->toDateString(),
                'warehouse_id' => $this->mainWarehouse()->id,
                'supplier_delivery_note' => 'DN-'.fake()->numerify('#####'),
                'notes' => 'Received in good condition (demo).',
                'lines' => $lines,
            ], $user);

            return app(ConfirmGoodsReceiptAction::class)->handle($receipt);
        }));
    }

    /** Approvals inbox material: a requisition awaiting approval, an approved one not yet ordered, a rejected one, a draft and a PO awaiting approval. */
    private function pendingDocuments(Collection $suppliers): void
    {
        $recent = $this->today()->subDays(3);
        $this->requisition($this->goods->slice(2, 2)->values(), $recent, 'submit');
        $this->requisition($this->goods->slice(6, 2)->values(), $recent->copy()->subDays(6), 'approve');
        $this->requisition($this->goods->slice(10, 1)->values(), $recent->copy()->subDays(20), 'reject');
        $this->requisition($this->goods->slice(12, 2)->values(), $recent, 'draft');

        $order = $this->directOrder($this->goods->slice(4, 2)->values(), $suppliers[3], $this->today()->subDays(1)->setTime(10, 0));
        $this->submitAndApprove($order, $this->today()->subDays(1)->setTime(10, 0), approve: false);
    }

    private function purchaseReturn(PurchaseOrder $order): void
    {
        $receipt = GoodsReceipt::query()->where('purchase_order_id', $order->id)->firstOrFail();
        $line = $order->lines()->orderBy('line_no')->firstOrFail();
        $date = $this->clampDate(Carbon::parse($receipt->received_date)->addDays(3))->setTime(14, 0);

        $this->at($date, fn () => $this->as('procurement', function ($user) use ($order, $receipt, $line, $date): void {
            $return = app(SavePurchaseReturnAction::class)->handle([
                'purchase_order_id' => $order->id,
                'goods_receipt_id' => $receipt->id,
                'return_date' => $date->toDateString(),
                'reason' => 'damaged',
                'notes' => 'Units arrived with damaged packaging (demo).',
                'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 2]],
            ], $user);

            app(ConfirmPurchaseReturnAction::class)->handle($return);
        }));
    }
}
