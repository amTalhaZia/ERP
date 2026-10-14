<?php

namespace App\Http\Requests\HR;

use App\Enums\HR\AttendanceSource;
use App\Enums\HR\AttendanceStatus;
use App\Models\HR\Employee;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
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
        $creating = $this->isMethod('POST');

        return [
            'employee_id' => [$creating ? 'required' : 'prohibited', 'integer', new TenantExists(Employee::class)],
            'date' => [$creating ? 'required' : 'prohibited', 'date_format:Y-m-d'],
            'status' => [$creating ? 'required' : 'sometimes', Rule::enum(AttendanceStatus::class)],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
            'source' => ['sometimes', Rule::in([AttendanceSource::Manual->value, AttendanceSource::Import->value, AttendanceSource::Biometric->value])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
