<?php

namespace App\Http\Requests\Payroll;

use App\Models\Department;
use App\Support\Tenancy\Rules\AccessibleBranch;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Monthly runs only: the period must be a single calendar month
 * (period_start = 1st, period_end = last day of the same month).
 */
class StorePayrollRunRequest extends FormRequest
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
        $restricted = app(TenantContext::class)->allowedBranchIds() !== null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'payment_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'branch_id' => [Rule::requiredIf($restricted), 'nullable', 'integer', new AccessibleBranch],
            'department_id' => ['nullable', 'integer', new TenantExists(Department::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['period_start', 'period_end'])) {
                return;
            }

            $start = CarbonImmutable::parse($this->input('period_start'));
            $end = CarbonImmutable::parse($this->input('period_end'));

            if ($start->day !== 1 || ! $end->isSameDay($start->endOfMonth())) {
                $validator->errors()->add('period_end', 'Only monthly payroll is supported: the period must be one full calendar month.');
            }
        }];
    }
}
