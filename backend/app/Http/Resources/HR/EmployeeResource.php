<?php

namespace App\Http\Resources\HR;

use App\Models\HR\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Encrypted identifiers are never emitted in lists. On show, holders of
 * employees.update receive them masked to the last four characters.
 *
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    private bool $withSensitive = false;

    public function withSensitive(bool $include = true): static
    {
        $this->withSensitive = $include;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_code' => $this->employee_code,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'email' => $this->user->email] : null),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->fullName(),
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender?->value,
            'marital_status' => $this->marital_status?->value,
            'address' => [
                'line1' => $this->address_line1,
                'line2' => $this->address_line2,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
            ],
            'emergency_contact' => [
                'name' => $this->emergency_contact_name,
                'phone' => $this->emergency_contact_phone,
                'relationship' => $this->emergency_contact_relationship,
            ],
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn () => $this->department ? ['id' => $this->department->id, 'name' => $this->department->name] : null),
            'designation_id' => $this->designation_id,
            'designation' => $this->whenLoaded('designation', fn () => $this->designation ? ['id' => $this->designation->id, 'title' => $this->designation->title] : null),
            'manager_id' => $this->manager_id,
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->fullName(),
                'employee_code' => $this->manager->employee_code,
            ] : null),
            'employment_type' => $this->employment_type?->value,
            'status' => $this->status?->value,
            'hired_at' => $this->hired_at?->toDateString(),
            'probation_ends_at' => $this->probation_ends_at?->toDateString(),
            'terminated_at' => $this->terminated_at?->toDateString(),
            'termination_reason' => $this->termination_reason,
            'photo_url' => $this->photoUrl(),
            'base_salary' => $this->base_salary,
            'currency' => $this->currency,
            'bank_name' => $this->bank_name,
            'notes' => $this->notes,
            'reports_count' => $this->whenCounted('reports'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];

        if ($this->withSensitive) {
            $data['sensitive'] = [
                'national_id' => self::mask($this->national_id),
                'bank_account_number' => self::mask($this->bank_account_number),
                'bank_iban' => self::mask($this->bank_iban),
                'tax_identifier' => self::mask($this->tax_identifier),
            ];
        }

        return $data;
    }

    public static function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_repeat('*', max(0, mb_strlen($value) - 4)).mb_substr($value, -4);
    }
}
