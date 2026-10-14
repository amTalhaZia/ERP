<?php

namespace App\Support\Numbering;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Issues sequential business document numbers per company and type.
 *
 *   $numberer->next('invoice')            → "INV-2026-000001"
 *   $numberer->next('employee', yearly: false, prefix: 'EMP', padding: 4) → "EMP-0001"
 *
 * Must be called inside the same DB transaction that persists the document so
 * a failed insert rolls the counter back too (no gaps, no duplicates).
 */
class DocumentNumberer
{
    /** @var array<string, string> */
    public const DEFAULT_PREFIXES = [
        'invoice' => 'INV', 'bill' => 'BILL', 'payment' => 'PAY', 'receipt' => 'RCPT', 'journal' => 'JE',
        'quotation' => 'QT', 'sales_order' => 'SO', 'purchase_requisition' => 'PR', 'purchase_order' => 'PO',
        'goods_receipt' => 'GRN', 'purchase_return' => 'PRT', 'stock_transfer' => 'TRF', 'stock_adjustment' => 'ADJ',
        'expense_claim' => 'EXP', 'payroll_run' => 'PAYRUN', 'payslip' => 'SLIP', 'employee' => 'EMP',
        'project' => 'PRJ', 'delivery' => 'DLV', 'credit_note' => 'CN',
    ];

    public function __construct(private readonly TenantContext $tenant) {}

    public function next(string $type, bool $yearly = true, ?string $prefix = null, int $padding = 6, ?int $companyId = null): string
    {
        $companyId ??= $this->tenant->id() ?? throw new \LogicException('No company context for document numbering.');
        $prefix ??= self::DEFAULT_PREFIXES[$type] ?? strtoupper(substr($type, 0, 4));
        $year = $yearly ? (int) now()->format('Y') : 0;

        return DB::transaction(function () use ($companyId, $type, $year, $prefix, $padding, $yearly): string {
            $row = DB::table('document_sequences')
                ->where('company_id', $companyId)->where('type', $type)->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('document_sequences')->insert([
                    'company_id' => $companyId, 'type' => $type, 'year' => $year,
                    'prefix' => $prefix, 'next_number' => 2, 'padding' => $padding,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $number = 1;
            } else {
                $number = (int) $row->next_number;
                $prefix = $row->prefix;
                $padding = (int) $row->padding;
                DB::table('document_sequences')->where('id', $row->id)->update(['next_number' => $number + 1, 'updated_at' => now()]);
            }

            $padded = str_pad((string) $number, $padding, '0', STR_PAD_LEFT);

            return $yearly ? "{$prefix}-{$year}-{$padded}" : "{$prefix}-{$padded}";
        });
    }
}
