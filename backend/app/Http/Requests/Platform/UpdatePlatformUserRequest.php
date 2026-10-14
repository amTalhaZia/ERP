<?php

namespace App\Http\Requests\Platform;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePlatformUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Owner-only rule for platform_role is enforced in the controller (403).
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'platform_role' => ['sometimes', 'nullable', Rule::in([Role::PLATFORM_OWNER, Role::PLATFORM_ADMIN])],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasAny(['is_active', 'platform_role'])) {
                    $validator->errors()->add('is_active', 'Provide is_active and/or platform_role.');
                }
            },
        ];
    }
}
