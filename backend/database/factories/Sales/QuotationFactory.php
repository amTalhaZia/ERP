<?php

namespace Database\Factories\Sales;

use App\Enums\Sales\QuotationStatus;
use App\Models\Company;
use App\Models\Sales\Customer;
use App\Models\Sales\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Header only (zero totals); add lines with QuotationLineFactory or create through the API.
 *
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'QT-T-'.Str::upper(Str::random(8)),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['company_id' => $attributes['company_id']])->id,
            'date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'status' => QuotationStatus::Draft,
            'currency' => 'USD',
        ];
    }

    public function sent(): static
    {
        return $this->state(['status' => QuotationStatus::Sent, 'sent_at' => now()]);
    }
}
