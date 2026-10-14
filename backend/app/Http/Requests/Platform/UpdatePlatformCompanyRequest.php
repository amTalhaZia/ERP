<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\Company\UpdateCompanyRequest;

/**
 * Same editable surface as the tenant-side company settings, plus the
 * platform-only flags.
 */
class UpdatePlatformCompanyRequest extends UpdateCompanyRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'is_demo' => ['sometimes', 'boolean'],
        ];
    }
}
