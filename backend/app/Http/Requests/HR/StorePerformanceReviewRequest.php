<?php

namespace App\Http\Requests\HR;

use App\Models\HR\Employee;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\Rules\WithinAccessibleBranch;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePerformanceReviewRequest extends FormRequest
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
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'employee_id' => [$creating ? 'required' : 'prohibited', 'integer', new TenantExists(Employee::class), new WithinAccessibleBranch(Employee::class)],
            'reviewer_id' => [$required, 'integer', Rule::exists('company_user', 'user_id')->where('company_id', $companyId)],
            'cycle' => [$required, 'string', 'max:20'],
            'period_start' => [$required, 'date'],
            'period_end' => [$required, 'date', 'after_or_equal:period_start'],
            'overall_rating' => ['nullable', 'numeric', 'between:0,10'],
            'ratings' => ['nullable', 'array', 'max:50'],
            'ratings.*.criterion' => ['required', 'string', 'max:150'],
            'ratings.*.rating' => ['required', 'numeric', 'between:0,10'],
            'ratings.*.comment' => ['nullable', 'string', 'max:1000'],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'improvements' => ['nullable', 'string', 'max:5000'],
            'goals' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
