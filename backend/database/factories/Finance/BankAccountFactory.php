<?php

namespace Database\Factories\Finance;

use App\Models\Company;
use App\Models\Finance\Account;
use App\Models\Finance\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'account_id' => fn (array $attrs) => Account::factory()->bank()->create(['company_id' => $attrs['company_id']])->id,
            'name' => 'Main Operating Account',
            'bank_name' => fake()->company().' Bank',
            'account_number' => fake()->numerify('############'),
            'iban' => fake()->iban('GB'),
            'swift' => 'ABCDGB2L',
            'currency' => 'USD',
            'opening_balance' => '0.0000',
            'is_active' => true,
        ];
    }
}
