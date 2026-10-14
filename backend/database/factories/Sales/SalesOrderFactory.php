<?php

namespace Database\Factories\Sales;

use App\Enums\Sales\SalesOrderStatus;
use App\Models\Company;
use App\Models\Sales\Customer;
use App\Models\Sales\SalesOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Header only (zero totals); add lines with SalesOrderLineFactory or create through the API.
 *
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'SO-T-'.Str::upper(Str::random(8)),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['company_id' => $attributes['company_id']])->id,
            'order_date' => now()->toDateString(),
            'status' => SalesOrderStatus::Draft,
            'currency' => 'USD',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => SalesOrderStatus::Confirmed, 'confirmed_at' => now()]);
    }

    public function approved(): static
    {
        return $this->state(['status' => SalesOrderStatus::Approved, 'confirmed_at' => now(), 'approved_at' => now()]);
    }
}
