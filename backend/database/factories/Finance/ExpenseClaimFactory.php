<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\ExpenseClaimStatus;
use App\Models\Company;
use App\Models\Finance\ExpenseClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Header-only expense claim (no lines).
 *
 * @extends Factory<ExpenseClaim>
 */
class ExpenseClaimFactory extends Factory
{
    protected $model = ExpenseClaim::class;

    public function definition(): array
    {
        $total = number_format(fake()->randomFloat(2, 10, 800), 2, '.', '');

        return [
            'company_id' => Company::factory(),
            'number' => 'EXP-'.now()->format('Y').'-'.fake()->unique()->numerify('9#####'),
            'user_id' => User::factory(),
            'claim_date' => now()->toDateString(),
            'currency' => 'USD',
            'status' => ExpenseClaimStatus::Draft,
            'subtotal' => $total,
            'tax_total' => '0',
            'total' => $total,
            'amount_paid' => '0',
            'balance_due' => $total,
        ];
    }
}
