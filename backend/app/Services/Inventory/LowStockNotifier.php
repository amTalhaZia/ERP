<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\User;
use App\Notifications\Inventory\LowStockNotification;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Sends the low-stock database notification to every user holding
 * `inventory.view` in the company, at most once per product per day.
 * Used both by RecordStockMovementAction (after outbound movements) and the
 * `inventory:check-reorder` command.
 */
class LowStockNotifier
{
    public const PERMISSION = 'inventory.view';

    /**
     * Returns true when a notification was sent.
     */
    public function notifyIfLow(Product $product): bool
    {
        $reorderLevel = Money::normalize($product->reorder_level);

        if (! $product->isGoods() || ! $product->is_active || Money::compare($reorderLevel, Money::zero()) <= 0) {
            return false;
        }

        $onHand = Money::normalize((string) StockLevel::withoutTenancy()
            ->where('company_id', $product->company_id)
            ->where('product_id', $product->id)
            ->sum('quantity_on_hand'));

        if (Money::compare($onHand, $reorderLevel) > 0) {
            return false;
        }

        return $this->notify($product, $onHand);
    }

    public function notify(Product $product, string $onHand): bool
    {
        $key = sprintf('inventory:low_stock:%d:%d:%s', $product->company_id, $product->id, now()->toDateString());

        if (! Cache::add($key, true, now()->endOfDay()->addMinute())) {
            return false;
        }

        $recipients = $this->recipients((int) $product->company_id);

        if ($recipients->isEmpty()) {
            return false;
        }

        Notification::send($recipients, new LowStockNotification($product, $onHand, (int) $product->company_id));

        return true;
    }

    /**
     * Active users who hold the permission in the given company.
     *
     * @return Collection<int, User>
     */
    public function recipients(int $companyId): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($roles) => $roles
                ->where('roles.company_id', $companyId)
                ->where('role_user.company_id', $companyId)
                ->whereHas('permissions', fn ($permissions) => $permissions->where('slug', self::PERMISSION)))
            ->get();
    }
}
