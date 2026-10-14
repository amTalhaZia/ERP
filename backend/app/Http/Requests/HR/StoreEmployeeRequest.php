<?php

namespace App\Http\Requests\HR;

use App\Enums\HR\EmployeeStatus;
use App\Enums\HR\EmploymentType;
use App\Enums\HR\Gender;
use App\Enums\HR\MaritalStatus;
use App\Models\Department;
use App\Models\HR\Designation;
use App\Models\HR\Employee;
use App\Support\Tenancy\Rules\AccessibleBranch;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $companyId = app(TenantContext::class)->id();
        /** @var Employee|null $current */
        $current = $this->route('employee');
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'first_name' => [$required, 'string', 'max:100'],
            'last_name' => [$required, 'string', 'max:100'],
            'email' => [
                $required, 'email', 'max:150',
                Rule::unique('employees', 'email')->where('company_id', $companyId)->ignore($current?->id)->whereNull('deleted_at'),
            ],
            'user_id' => [
                'nullable', 'integer',
                // The login must already be a member of this company.
                Rule::exists('company_user', 'user_id')->where('company_id', $companyId),
                Rule::unique('employees', 'user_id')->where('company_id', $companyId)->ignore($current?->id)->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'national_id' => ['nullable', 'string', 'max:60'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:60'],
            'branch_id' => ['nullable', 'integer', new AccessibleBranch],
            'department_id' => ['nullable', 'integer', new TenantExists(Department::class)],
            'designation_id' => ['nullable', 'integer', new TenantExists(Designation::class)],
            'manager_id' => [
                'nullable', 'integer', new TenantExists(Employee::class),
                function (string $attribute, mixed $value, Closure $fail) use ($current): void {
                    if ($current !== null && $this->wouldCreateCycle($current, (int) $value)) {
                        $fail('An employee cannot report to themself or to one of their own reports.');
                    }
                },
            ],
            'employment_type' => [$required, Rule::enum(EmploymentType::class)],
            'status' => [
                'sometimes',
                $this->isMethod('POST')
                    ? Rule::in([EmployeeStatus::Probation->value, EmployeeStatus::Active->value])
                    : Rule::in([EmployeeStatus::Probation->value, EmployeeStatus::Active->value, EmployeeStatus::OnLeave->value, EmployeeStatus::Suspended->value]),
            ],
            'hired_at' => [$required, 'date'],
            'probation_ends_at' => ['nullable', 'date', 'after_or_equal:hired_at'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:60'],
            'bank_iban' => ['nullable', 'string', 'max:60'],
            'tax_identifier' => ['nullable', 'string', 'max:60'],
            'base_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Walk up the proposed manager's chain; reaching the employee means a cycle.
     */
    private function wouldCreateCycle(Employee $employee, int $managerId): bool
    {
        if ($managerId === $employee->id) {
            return true;
        }

        $visited = [];
        $cursor = $managerId;

        while ($cursor !== null && ! isset($visited[$cursor]) && count($visited) < 500) {
            if ($cursor === $employee->id) {
                return true;
            }

            $visited[$cursor] = true;
            $cursor = Employee::query()->whereKey($cursor)->value('manager_id');
            $cursor = $cursor === null ? null : (int) $cursor;
        }

        return false;
    }
}
