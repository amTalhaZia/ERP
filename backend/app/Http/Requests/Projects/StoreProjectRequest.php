<?php

namespace App\Http\Requests\Projects;

use App\Enums\Projects\Priority;
use App\Enums\Projects\ProjectStatus;
use App\Http\Requests\Projects\Rules\CompanyMember;
use App\Models\Department;
use App\Support\Tenancy\Rules\AccessibleBranch;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $companyId = app(TenantContext::class)->id();
        $current = $this->route('project');
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:150'],
            'code' => [
                'nullable', 'string', 'max:30', 'alpha_dash',
                Rule::unique('projects', 'code')->where('company_id', $companyId)->whereNull('deleted_at')->ignore($current?->id),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'customer_id' => ['nullable', 'integer', $this->customerRule()],
            'department_id' => ['nullable', 'integer', new TenantExists(Department::class)],
            'branch_id' => ['nullable', 'integer', new AccessibleBranch],
            'manager_id' => ['nullable', 'integer', new CompanyMember],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:99999999999999', 'decimal:0,4'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/'],
            'is_billable' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * customer_id has no FK (Sales is a separate module); when the customers
     * table exists the id must belong to the current company.
     */
    private function customerRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! Schema::hasTable('customers')) {
                return;
            }

            $exists = DB::table('customers')
                ->where('id', $value)
                ->where('company_id', app(TenantContext::class)->id())
                ->when(Schema::hasColumn('customers', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->exists();

            if (! $exists) {
                $fail('The selected :attribute is invalid.');
            }
        };
    }
}
