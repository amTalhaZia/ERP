<?php

namespace App\Actions\Finance\Receivables;

use App\Actions\Finance\Receivables\Concerns\BuildsDocumentLines;
use App\Enums\Finance\InvoiceStatus;
use App\Models\Finance\Invoice;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Services\Finance\Receivables\DocumentTotalsCalculator;
use App\Services\Finance\Receivables\PartnerDirectory;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a Draft / PendingApproval invoice. Lines, when given,
 * replace the existing ones; totals are always recomputed server-side.
 * Default due date = invoice_date + customer payment_terms_days (30 if unknown).
 */
class SaveInvoiceAction
{
    use BuildsDocumentLines;

    private const HEADER = ['customer_id', 'sales_order_id', 'invoice_date', 'due_date', 'currency', 'notes', 'terms', 'branch_id'];

    public function __construct(
        private readonly DocumentNumberer $numberer,
        private readonly DocumentTotalsCalculator $calculator,
        private readonly PartnerDirectory $partners,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Invoice $invoice = null, ?int $userId = null): Invoice
    {
        if ($invoice !== null) {
            $this->refuseUnlessEditable($invoice->isEditable(), "Invoice {$invoice->number}");
        }

        return DB::transaction(function () use ($data, $invoice, $userId): Invoice {
            $header = Arr::only($data, self::HEADER);
            $customerId = (int) ($header['customer_id'] ?? $invoice?->customer_id);
            $terms = $this->partners->terms('customers', $customerId);

            if ($invoice === null) {
                $invoice = new Invoice([
                    'number' => $this->numberer->next('invoice'),
                    'status' => InvoiceStatus::Draft,
                    'created_by' => $userId,
                    'currency' => $terms['currency'] ?? $this->tenant->company()?->currency ?? 'USD',
                ]);
            }

            $invoice->fill(array_filter(
                Arr::except($header, ['due_date']),
                fn ($value, $key) => $value !== null || ! in_array($key, ['customer_id', 'invoice_date', 'currency'], true),
                ARRAY_FILTER_USE_BOTH,
            ));

            if (($header['due_date'] ?? null) !== null) {
                $invoice->due_date = $header['due_date'];
            } elseif ($invoice->due_date === null || array_key_exists('invoice_date', $header)) {
                $invoice->due_date = Carbon::parse($invoice->invoice_date)->addDays($terms['payment_terms_days'] ?? 30)->toDateString();
            }

            $invoice->save();

            if (array_key_exists('lines', $data)) {
                $revenueId = $this->accountIdByCode(ChartOfAccountsTemplate::SALES_REVENUE);
                $rows = $this->buildLines($data['lines'], fn () => $revenueId);

                $invoice->lines()->delete();
                $invoice->lines()->createMany($rows);
            }

            $totals = $this->calculator->totals($invoice->lines()->get()->map(fn ($line) => [
                'line_subtotal' => (string) $line->line_subtotal,
                'discount_amount' => (string) $line->discount_amount,
                'tax_amount' => (string) $line->tax_amount,
            ]));

            $invoice->fill($totals + ['amount_paid' => '0', 'balance_due' => $totals['total']])->save();

            return $invoice->refresh();
        });
    }
}
