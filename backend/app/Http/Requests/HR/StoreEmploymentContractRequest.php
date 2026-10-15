<?php

namespace App\Http\Requests\HR;

use App\Enums\HR\ContractStatus;
use App\Enums\HR\EmploymentType;
use App\Models\HR\Employee;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\Rules\WithinAccessibleBranch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmploymentContractRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'employee_id' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'integer', new TenantExists(Employee::class), new WithinAccessibleBranch(Employee::class)],
            'contract_type' => [$required, Rule::enum(EmploymentType::class)],
            'start_date' => [$required, 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'salary' => [$required, 'numeric', 'min:0', 'max:99999999999999'],
            'currency' => [$required, 'string', 'size:3'],
            'working_hours_per_week' => ['sometimes', 'numeric', 'min:0', 'max:168'],
            'status' => ['sometimes', Rule::enum(ContractStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'document' => ['nullable', 'file', 'mimes:'.implode(',', config('erp.uploads.allowed_mimes')), 'max:'.config('erp.uploads.max_kb')],
        ];
    }
}
