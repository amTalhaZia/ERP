<?php

namespace App\Console\Commands\Inventory;

use App\Enums\Inventory\ProductType;
use App\Models\Company;
use App\Models\Inventory\Product;
use App\Services\Inventory\LowStockNotifier;
use App\Support\Money\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Daily sweep: for every active company, notify users holding inventory.view
 * about active goods whose total on-hand is at/below the reorder level.
 * De-duplicated per product per day by LowStockNotifier.
 */
class CheckReorderLevels extends Command
{
    protected $signature = 'inventory:check-reorder {--company= : Only check this company id}';

    protected $description = 'Notify inventory users about products at or below their reorder level';

    public function handle(TenantContext $tenant, LowStockNotifier $notifier): int
    {
        $companies = Company::query()->where('is_active', true)
            ->when($this->option('company'), fn ($q, $id) => $q->whereKey((int) $id))
            ->orderBy('id');

        $totalLow = 0;
        $totalNotified = 0;

        try {
            foreach ($companies->lazyById(100) as $company) {
                $tenant->set($company, null);

                $products = Product::query()
                    ->where('type', ProductType::Goods)
                    ->where('is_active', true)
                    ->where('reorder_level', '>', 0)
                    ->withSum('stockLevels as quantity_on_hand', 'quantity_on_hand')
                    ->whereRaw('COALESCE((SELECT SUM(sl.quantity_on_hand) FROM stock_levels sl WHERE sl.product_id = products.id), 0) <= products.reorder_level')
                    ->get();

                foreach ($products as $product) {
                    $totalLow++;

                    if ($notifier->notify($product, Money::normalize((string) ($product->getAttribute('quantity_on_hand') ?? '0')))) {
                        $totalNotified++;
                    }
                }

                $this->components->twoColumnDetail($company->name, "{$products->count()} low-stock product(s)");
            }
        } finally {
            $tenant->clear();
        }

        $this->components->info("{$totalLow} low-stock product(s); {$totalNotified} notification batch(es) sent.");

        return self::SUCCESS;
    }
}
