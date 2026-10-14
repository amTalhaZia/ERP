<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlatformCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // `platform` + `permission:` middleware gate the route.
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash', Rule::unique('companies', 'slug')],
            'code' => ['nullable', 'string', 'max:20', 'alpha_dash', Rule::unique('companies', 'code')],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:200'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'alpha'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all'],
            'locale' => ['sometimes', 'required', 'string', 'max:10'],
            'date_format' => ['sometimes', 'required', Rule::in(['Y-m-d', 'd/m/Y', 'm/d/Y', 'd.m.Y', 'd M Y'])],
            'fiscal_year_start_month' => ['sometimes', 'required', 'integer', 'between:1,12'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_demo' => ['sometimes', 'boolean'],
            'settings' => ['nullable', 'array'],
            'settings.*' => ['nullable'],
            'owner' => ['required', 'array'],
            'owner.name' => ['required', 'string', 'max:150'],
            'owner.email' => ['required', 'email', 'max:150'],
        ];
    }

    /**
     * Company attributes only (owner stripped), ready for CreateCompanyAction.
     *
     * @return array<string, mixed>
     */
    public function companyAttributes(): array
    {
        $data = $this->validated();
        unset($data['owner']);

        return array_filter($data, fn ($value) => $value !== null);
    }

    /**
     * @return array{name: string, email: string}
     */
    public function owner(): array
    {
        return $this->validated('owner');
    }
}
