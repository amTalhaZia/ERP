<?php

namespace Database\Factories\Sales;

use App\Enums\Sales\OpportunityStatus;
use App\Models\Company;
use App\Models\Sales\Opportunity;
use App\Models\Sales\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => fake()->catchPhrase(),
            'stage_id' => fn (array $attributes) => PipelineStage::factory()->create(['company_id' => $attributes['company_id']])->id,
            'value' => '1000.0000',
            'currency' => 'USD',
            'probability' => 50,
            'status' => OpportunityStatus::Open,
            'expected_close_date' => now()->addMonth()->toDateString(),
        ];
    }
}
