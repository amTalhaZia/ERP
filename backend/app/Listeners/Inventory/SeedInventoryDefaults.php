<?php

namespace App\Listeners\Inventory;

use App\Events\CompanyProvisioned;
use App\Models\Branch;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Inventory\Warehouse;

/**
 * Seeds per-company inventory defaults when a company is provisioned: the
 * standard units of measure and a default "Main Warehouse" (code MAIN) linked
 * to the head-office branch. Runs inside the provisioning transaction with no
 * tenant context (auto-discovered), hence explicit company_id. Idempotent.
 */
class SeedInventoryDefaults
{
    /** @var array<int, array{name: string, symbol: string, precision: int}> */
    public const UNITS = [
        ['name' => 'Piece', 'symbol' => 'pcs', 'precision' => 0],
        ['name' => 'Kilogram', 'symbol' => 'kg', 'precision' => 3],
        ['name' => 'Litre', 'symbol' => 'L', 'precision' => 3],
        ['name' => 'Metre', 'symbol' => 'm', 'precision' => 2],
        ['name' => 'Box', 'symbol' => 'box', 'precision' => 0],
        ['name' => 'Hour', 'symbol' => 'hr', 'precision' => 2],
    ];

    public function handle(CompanyProvisioned $event): void
    {
        $company = $event->company;

        foreach (self::UNITS as $unit) {
            UnitOfMeasure::withoutTenancy()->firstOrCreate(
                ['company_id' => $company->id, 'symbol' => $unit['symbol']],
                $unit + ['is_active' => true],
            );
        }

        if (Warehouse::withoutTenancy()->where('company_id', $company->id)->exists()) {
            return;
        }

        $headOffice = Branch::withoutTenancy()
            ->where('company_id', $company->id)
            ->where('is_head_office', true)
            ->first();

        Warehouse::withoutTenancy()->create([
            'company_id' => $company->id,
            'branch_id' => $headOffice?->id,
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'address_line1' => $headOffice->address_line1 ?? $company->address_line1,
            'city' => $headOffice->city ?? $company->city,
            'state' => $headOffice->state ?? $company->state,
            'postal_code' => $headOffice->postal_code ?? $company->postal_code,
            'country' => $headOffice->country ?? $company->country,
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}
