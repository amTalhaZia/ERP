<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\User;
use App\Notifications\Inventory\LowStockNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Inventory\Concerns\InteractsWithInventory;
use Tests\TestCase;

class LowStockNotificationTest extends TestCase
{
    use InteractsWithInventory;

    public function test_outbound_movement_crossing_reorder_level_notifies_inventory_viewers_once_per_day(): void
    {
        Notification::fake();
        [$user, $company, $warehouse, $product] = $this->arrangeStockKeeper(['reorder_level' => '5']);
        $outsider = User::factory()->inCompany($company, ['products.view'])->create();

        $this->actingAsIn($user, $company);
        $this->receive($warehouse, $product, '10', '1');
        $this->issue($warehouse, $product, '4')->assertCreated(); // 6 left: above level
        Notification::assertNothingSent();

        $this->issue($warehouse, $product, '2')->assertCreated(); // 4 left
        $this->issue($warehouse, $product, '1')->assertCreated(); // same day: deduplicated

        Notification::assertSentToTimes($user, LowStockNotification::class, 1);
        Notification::assertNotSentTo($outsider, LowStockNotification::class);
        Notification::assertSentTo($user, LowStockNotification::class, function (LowStockNotification $n) use ($user, $company, $product) {
            $data = $n->toDatabase($user);

            return $data['type'] === 'inventory.low_stock'
                && $data['url'] === '/inventory/products/low-stock'
                && $data['company_id'] === $company->id
                && $data['product_id'] === $product->id;
        });
    }

    public function test_check_reorder_command_writes_database_notifications_per_company(): void
    {
        [$user, $company] = $this->arrangeStockKeeper();
        Product::factory()->reorderAt('3')->create(['company_id' => $company->id, 'name' => 'Empty shelf']);
        Product::factory()->create(['company_id' => $company->id]); // no reorder level

        $this->artisan('inventory:check-reorder')->assertSuccessful();
        $this->artisan('inventory:check-reorder')->assertSuccessful(); // deduplicated per day

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => LowStockNotification::class,
        ]);
        $this->assertStringContainsString('inventory.low_stock', (string) \DB::table('notifications')->value('data'));
    }
}
