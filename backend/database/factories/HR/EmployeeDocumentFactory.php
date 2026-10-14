<?php

namespace Database\Factories\HR;

use App\Enums\HR\DocumentCategory;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'company_id' => fn (array $attrs) => Employee::withoutTenancy()->find($attrs['employee_id'])->company_id,
            'title' => 'Passport copy',
            'category' => DocumentCategory::Id,
            'file_path' => 'hr-tests/'.fake()->uuid().'.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 1024,
            'is_confidential' => false,
        ];
    }

    public function confidential(): static
    {
        return $this->state(['is_confidential' => true]);
    }
}
