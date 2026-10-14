<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'company_id' => Company::factory(),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(3)),
            'name' => $name,
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }

    public function platform(): static
    {
        return $this->state(['company_id' => null]);
    }
}
