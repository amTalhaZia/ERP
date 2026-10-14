<?php

namespace Database\Factories\HR;

use App\Enums\HR\EmployeeStatus;
use App\Enums\HR\EmploymentType;
use App\Enums\HR\Gender;
use App\Models\Company;
use App\Models\HR\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_code' => 'EMP-'.fake()->unique()->numerify('9####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-20 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases()),
            'national_id' => fake()->numerify('#########'),
            'city' => fake()->city(),
            'country' => 'US',
            'employment_type' => EmploymentType::FullTime,
            'status' => EmployeeStatus::Active,
            'hired_at' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'bank_name' => 'First Bank',
            'bank_account_number' => fake()->numerify('############'),
            'base_salary' => '5000.0000',
            'currency' => 'USD',
        ];
    }

    public function probation(): static
    {
        return $this->state(['status' => EmployeeStatus::Probation, 'probation_ends_at' => now()->addMonths(3)->toDateString()]);
    }

    public function terminated(): static
    {
        return $this->state(['status' => EmployeeStatus::Terminated, 'terminated_at' => now()->subDay()->toDateString(), 'termination_reason' => 'Resigned']);
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id, 'email' => $user->email]);
    }
}
