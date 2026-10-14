<?php

namespace App\Http\Requests\Projects;

use App\Enums\Projects\ProjectMemberRole;
use App\Http\Requests\Projects\Rules\CompanyMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add a member (POST) or change a member's role (PATCH). */
class StoreProjectMemberRequest extends FormRequest
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
        if (! $this->isMethod('POST')) {
            return [
                'role' => ['required', Rule::enum(ProjectMemberRole::class)],
            ];
        }

        $project = $this->route('project');

        return [
            'user_id' => [
                'required', 'integer', new CompanyMember,
                Rule::unique('project_members', 'user_id')->where('project_id', $project?->id),
            ],
            'role' => ['sometimes', Rule::enum(ProjectMemberRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['user_id.unique' => 'This user is already a member of the project.'];
    }
}
