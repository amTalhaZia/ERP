<?php

namespace Tests\Feature\Sales\Concerns;

use App\Models\Company;
use App\Models\Inventory\Product;
use App\Models\Sales\Customer;
use App\Models\User;

trait ArrangesSales
{
    /** @var array<int, string> */
    protected array $allSalesPermissions = [
        'customers.view', 'customers.manage', 'leads.view', 'leads.manage',
        'quotations.view', 'quotations.manage', 'sales_orders.view', 'sales_orders.manage', 'sales_orders.approve',
        'reports.view',
    ];

    /**
     * actingAsIn() that also forgets resolved guards, so switching users
     * mid-test is not masked by the sanctum guard's cached user.
     */
    protected function actAs(User $user, Company $company): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAsIn($user, $company);
    }

    /**
     * @param  array<int, string>|null  $permissions
     * @return array{0: User, 1: Company, 2: Customer}
     */
    protected function salesUser(?array $permissions = null): array
    {
        [$user, $company] = $this->userWith($permissions ?? $this->allSalesPermissions);
        $customer = Customer::factory()->for($company)->create(['currency' => 'USD']);

        return [$user, $company, $customer];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $lines
     * @return array<string, mixed>
     */
    protected function documentPayload(Customer $customer, ?array $lines = null): array
    {
        return [
            'customer_id' => $customer->id,
            'lines' => $lines ?? [
                ['description' => 'Widget', 'quantity' => 2, 'unit_price' => '100', 'discount_percent' => 10, 'tax_rate' => 15, 'uom' => 'pcs', 'product_id' => $this->catalogueProduct($customer)->id],
            ],
        ];
    }

    /** A catalogue product of the customer's company (created once per company). */
    protected function catalogueProduct(Customer $customer): Product
    {
        return Product::withoutTenancy()->where('company_id', $customer->company_id)->where('sku', 'WIDGET-1')->first()
            ?? Product::factory()->create(['company_id' => $customer->company_id, 'sku' => 'WIDGET-1', 'name' => 'Widget']);
    }
}
