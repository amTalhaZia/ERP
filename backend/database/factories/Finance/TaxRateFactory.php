<?php

namespace Database\Factories\Finance;

use App\Enums\Finance\TaxRateType;
use App\Models\Company;
use App\Models\Finance\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Standard Rate',
            'code' => Str::upper(Str::random(5)),
            'rate' => '15.0000',
            'type' => TaxRateType::Both,
            'is_compound' => false,
            'is_active' => true,
        ];
    }

    public function zero(): static
    {
        return $this->state(['name' => 'Zero Rate', 'rate' => '0.0000']);
    }
}
