<?php

namespace App\Http\Requests\HR;

use App\Enums\HR\AttendanceSource;
use App\Enums\HR\AttendanceStatus;
use App\Models\HR\Employee;
use App\Support\Tenancy\Rules\TenantExists;
use App\Support\Tenancy\Rules\WithinAccessibleBranch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `{ date, source?, records: [{ employee_id, status, check_in?, check_out?, notes? }] }`
 */
class BulkAttendanceRequest extends FormRequest
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
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'source' => ['sometimes', Rule::in([AttendanceSource::Manual->value, AttendanceSource::Import->value, AttendanceSource::Biometric->value])],
            'records' => ['required', 'array', 'min:1', 'max:500'],
            'records.*.employee_id' => ['required', 'integer', 'distinct', new TenantExists(Employee::class), new WithinAccessibleBranch(Employee::class)],
            'records.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'records.*.check_in' => ['nullable', 'date'],
            'records.*.check_out' => ['nullable', 'date'],
            'records.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
