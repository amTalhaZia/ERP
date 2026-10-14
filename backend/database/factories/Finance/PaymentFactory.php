<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\PaymentMethod;
use App\Enums\Finance\PaymentPartnerType;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Finance\PaymentType;
use App\Models\Company;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Unallocated, unposted payment row — for list/permission tests only. Real
 * payments go through RecordPaymentAction.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'number' => 'RCPT-'.now()->format('Y').'-'.fake()->unique()->numerify('9#####'),
            'type' => PaymentType::Receipt,
            'partner_type' => PaymentPartnerType::Customer,
            'partner_id' => fake()->numberBetween(1, 1000),
            'payment_date' => now()->toDateString(),
            'method' => PaymentMethod::BankTransfer,
            'bank_account_id' => fn (array $attrs) => BankAccount::factory()->create(['company_id' => $attrs['company_id']])->id,
            'amount' => number_format(fake()->randomFloat(2, 10, 2000), 2, '.', ''),
            'currency' => 'USD',
            'status' => PaymentStatus::Posted,
        ];
    }
}
