<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\AccountSubtype;
use App\Enums\Finance\AccountType;
use App\Models\Company;
use App\Models\Finance\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => (string) fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->words(2, true),
            'type' => AccountType::Asset,
            'subtype' => AccountSubtype::CurrentAsset->value,
            'is_bank' => false,
            'is_cash' => false,
            'is_system' => false,
            'is_active' => true,
            'currency' => 'USD',
            'opening_balance' => '0.0000',
        ];
    }

    public function ofType(AccountType $type, ?AccountSubtype $subtype = null): static
    {
        return $this->state(['type' => $type, 'subtype' => $subtype?->value]);
    }

    public function asset(): static
    {
        return $this->ofType(AccountType::Asset, AccountSubtype::CurrentAsset);
    }

    public function liability(): static
    {
        return $this->ofType(AccountType::Liability, AccountSubtype::CurrentLiability);
    }

    public function equity(): static
    {
        return $this->ofType(AccountType::Equity, AccountSubtype::ShareCapital);
    }

    public function revenue(): static
    {
        return $this->ofType(AccountType::Revenue, AccountSubtype::SalesRevenue);
    }

    public function expense(): static
    {
        return $this->ofType(AccountType::Expense, AccountSubtype::OperatingExpense);
    }

    public function costOfSales(): static
    {
        return $this->ofType(AccountType::Expense, AccountSubtype::CostOfSales);
    }

    public function bank(): static
    {
        return $this->ofType(AccountType::Asset, AccountSubtype::Bank)->state(['is_bank' => true]);
    }

    public function cash(): static
    {
        return $this->ofType(AccountType::Asset, AccountSubtype::Cash)->state(['is_cash' => true]);
    }

    public function system(): static
    {
        return $this->state(['is_system' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
