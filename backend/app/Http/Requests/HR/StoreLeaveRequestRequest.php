<?php

namespace App\Http\Requests\HR;

use App\Models\HR\LeaveType;
use App\Support\Tenancy\Rules\TenantExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequestRequest extends FormRequest
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
            'leave_type_id' => ['required', 'integer', new TenantExists(LeaveType::class, where: ['is_active' => true])],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:'.implode(',', config('erp.uploads.allowed_mimes')), 'max:'.config('erp.uploads.max_kb')],
        ];
    }
}
