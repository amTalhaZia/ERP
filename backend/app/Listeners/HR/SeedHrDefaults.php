<?php

namespace App\Listeners\HR;

use App\Events\CompanyProvisioned;
use App\Models\HR\LeaveType;
use App\Models\HR\Shift;

/**
 * Seeds per-company HR defaults when a company is provisioned: the standard
 * leave types and a default Mon–Fri 09:00–18:00 shift. Runs inside the
 * provisioning transaction with no tenant context, hence explicit company_id.
 */
class SeedHrDefaults
{
    /** @var array<int, array{name: string, code: string, days_per_year: string, is_paid: bool, carry_forward: bool, max_carry_forward_days: string|null, color: string}> */
    private const LEAVE_TYPES = [
        ['name' => 'Annual Leave', 'code' => 'ANNUAL', 'days_per_year' => '20.0', 'is_paid' => true, 'carry_forward' => true, 'max_carry_forward_days' => '5.0', 'color' => '#2563eb'],
        ['name' => 'Sick Leave', 'code' => 'SICK', 'days_per_year' => '10.0', 'is_paid' => true, 'carry_forward' => false, 'max_carry_forward_days' => null, 'color' => '#dc2626'],
        ['name' => 'Unpaid Leave', 'code' => 'UNPAID', 'days_per_year' => '0.0', 'is_paid' => false, 'carry_forward' => false, 'max_carry_forward_days' => null, 'color' => '#6b7280'],
        ['name' => 'Maternity Leave', 'code' => 'MATERNITY', 'days_per_year' => '90.0', 'is_paid' => true, 'carry_forward' => false, 'max_carry_forward_days' => null, 'color' => '#db2777'],
        ['name' => 'Paternity Leave', 'code' => 'PATERNITY', 'days_per_year' => '10.0', 'is_paid' => true, 'carry_forward' => false, 'max_carry_forward_days' => null, 'color' => '#0891b2'],
    ];

    public function handle(CompanyProvisioned $event): void
    {
        $companyId = $event->company->id;

        foreach (self::LEAVE_TYPES as $type) {
            LeaveType::withoutTenancy()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $type['code']],
                $type + ['requires_approval' => true, 'is_active' => true],
            );
        }

        if (! Shift::withoutTenancy()->where('company_id', $companyId)->exists()) {
            Shift::withoutTenancy()->create([
                'company_id' => $companyId,
                'name' => 'Standard Day',
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'break_minutes' => 60,
                'days_of_week' => [1, 2, 3, 4, 5],
                'is_default' => true,
            ]);
        }
    }
}
