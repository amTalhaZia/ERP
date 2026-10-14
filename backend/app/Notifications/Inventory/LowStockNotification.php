<?php

namespace App\Notifications\Inventory;

use App\Models\Inventory\Product;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    public const TYPE = 'inventory.low_stock';

    public function __construct(
        public readonly Product $product,
        public readonly string $quantityOnHand,
        public readonly int $companyId,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{type: string, title: string, body: string, url: string, company_id: int, product_id: int}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => self::TYPE,
            'title' => 'Low stock: '.$this->product->name,
            'body' => sprintf(
                '%s (%s) is down to %s, at or below its reorder level of %s.',
                $this->product->name,
                $this->product->sku,
                $this->quantityOnHand,
                $this->product->reorder_level,
            ),
            'url' => '/inventory/products/low-stock',
            'company_id' => $this->companyId,
            'product_id' => $this->product->id,
        ];
    }
}
