<?php

namespace App\Http\Requests\Reports;

use App\Services\Reports\Definitions\ReportDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /reports/{key}/export — `format` here; the report-specific `params`
 * are validated against the definition's schema by ReportRunner::params().
 */
class ExportReportRequest extends FormRequest
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
            'format' => ['required', 'string', Rule::in(ReportDefinition::FORMATS)],
            'params' => ['nullable', 'array'],
        ];
    }
}
