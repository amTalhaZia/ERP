<?php

namespace Database\Factories\Sales;

use App\Models\Company;
use App\Models\Sales\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipelineStage>
 */
class PipelineStageFactory extends Factory
{
    protected $model = PipelineStage::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->word()),
            'probability' => 50,
            'position' => fake()->numberBetween(1, 50),
            'is_won' => false,
            'is_lost' => false,
            'color' => '#0EA5E9',
        ];
    }

    public function won(): static
    {
        return $this->state(['name' => 'Won', 'probability' => 100, 'is_won' => true]);
    }

    public function lost(): static
    {
        return $this->state(['name' => 'Lost', 'probability' => 0, 'is_lost' => true]);
    }
}
