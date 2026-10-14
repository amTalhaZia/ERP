<?php

namespace Database\Seeders\Demo;

use App\Actions\Finance\Receivables\DecideExpenseClaimAction;
use App\Actions\Finance\Receivables\PostBillAction;
use App\Actions\Finance\Receivables\PostInvoiceAction;
use App\Actions\Finance\Receivables\RecordPaymentAction;
use App\Actions\Finance\Receivables\SaveBillAction;
use App\Actions\Finance\Receivables\SaveExpenseClaimAction;
use App\Actions\Finance\Receivables\SaveInvoiceAction;
use App\Actions\Finance\Receivables\SubmitBillAction;
use App\Actions\Finance\Receivables\SubmitExpenseClaimAction;
use App\Actions\Finance\Receivables\SubmitInvoiceAction;
use App\Enums\Finance\JournalSourceType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Finance\Account;
use App\Models\Finance\Bill;
use App\Models\Finance\Invoice;
use App\Models\Finance\TaxRate;
use App\Models\HR\Employee;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Sales\Customer;
use App\Models\Sales\Delivery;
use App\Models\Sales\SalesOrder;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Services\Finance\LedgerPoster;
use App\Support\Money\Money;
use Database\Seeders\Demo\Concerns\SeedsDemoCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * DEVELOPMENT ONLY. Opening balance (share capital into the bank + opening
 * stock value), invoices for delivered sales orders with receipts (paid /
 * partially paid / overdue), supplier bills for received purchase orders with
 * payments, monthly rent and utilities, and two expense claims (one approved &
 * paid, one pending). Everything posts through LedgerPoster / the receivables
 * actions, so the trial balance balances by construction.
 */
class FinanceDemoSeeder extends Seeder
{
    use SeedsDemoCompany;

    private int $bankId;

    /** @var array<string, int> tax rate code => id */
    private array $taxRates = [];

    public function run(Company $company): void
    {
        $this->bootDemo($company, 'finance');
        $this->bankId = $this->bankAccount()->id;
        $this->taxRates = TaxRate::query()->pluck('id', 'code')->all();

        $this->openingBalance();
        $this->invoices();
        $this->bills();
        $this->overheads();
        $this->expenseClaims();

        $this->info(Invoice::query()->count().' invoices, '.Bill::query()->count().' bills');
    }

    private function taxRateId(string|float $percent): ?int
    {
        return Money::isZero(Money::normalize((string) $percent)) ? ($this->taxRates['ZERO'] ?? null) : ($this->taxRates['STD'] ?? null);
    }

    private function openingBalance(): void
    {
        $capital = $this->amount(600000);
        $stock = Money::round(Money::normalize((string) StockMovement::query()->where('type', 'opening')->sum('total_cost')), 2);
        $date = $this->fiscalStart();

        $lines = [
            ['account_code' => ChartOfAccountsTemplate::BANK, 'debit' => $capital, 'credit' => '0', 'description' => 'Share capital paid in'],
            ['account_code' => ChartOfAccountsTemplate::SHARE_CAPITAL, 'debit' => '0', 'credit' => Money::add($capital, $stock), 'description' => 'Share capital (cash and contributed stock)'],
        ];

        if (Money::compare($stock, '0') > 0) {
            $lines[] = ['account_code' => ChartOfAccountsTemplate::INVENTORY, 'debit' => $stock, 'credit' => '0', 'description' => 'Opening stock valuation'];
        }

        $this->at($date->copy()->setTime(9, 0), fn () => $this->as('accountant', fn ($user) => app(LedgerPoster::class)->post([
            'entry_date' => $date->toDateString(),
            'memo' => 'Opening balances (demo)',
            'reference' => 'OB-'.$date->format('Y'),
            'source_type' => JournalSourceType::OpeningBalance->value,
            'lines' => $lines,
        ], $user->id)));
    }

    /**
     * One invoice per delivered (or partially delivered) order, issued on the
     * last delivery date for the delivered quantities. Most are settled by a
     * receipt, some partially, some left open (and overdue once past due).
     */
    private function invoices(): void
    {
        $orders = SalesOrder::query()
            ->whereIn('status', ['delivered', 'partially_delivered'])
            ->with('lines')
            ->orderBy('order_date')
            ->orderBy('id')
            ->get();

        foreach ($orders as $k => $order) {
            $deliveredAt = Delivery::query()->where('sales_order_id', $order->id)->where('status', 'delivered')->max('delivered_at');
            $invoiceDate = $this->clampDate(Carbon::parse($deliveredAt ?? $order->order_date))->setTime(12, 0);

            $lines = $order->lines
                ->filter(fn ($line) => (float) $line->quantity_delivered > 0)
                ->map(fn ($line) => [
                    'product_id' => $line->product_id,
                    'description' => $line->description,
                    'quantity' => (string) $line->quantity_delivered,
                    'unit_price' => (string) $line->unit_price,
                    'discount_percent' => (string) $line->discount_percent,
                    'tax_rate_id' => $this->taxRateId((string) $line->tax_rate),
                ])->values()->all();

            if ($lines === []) {
                continue;
            }

            $invoice = $this->issueInvoice([
                'customer_id' => $order->customer_id,
                'sales_order_id' => $order->id,
                'invoice_date' => $invoiceDate->toDateString(),
                'notes' => "Invoice for sales order {$order->number}.",
                'terms' => 'Payment due per agreed terms.',
                'lines' => $lines,
            ], $invoiceDate);

            $plan = $k % 6;
            $paidOn = $invoiceDate->copy()->addDays(fake()->numberBetween(10, 28));

            if ($plan === 4 || $paidOn->greaterThanOrEqualTo($this->today())) {
                continue; // left open — overdue once past its due date
            }

            $amount = $plan === 2
                ? Money::round(Money::mul((string) $invoice->balance_due, '0.4'), 2)
                : (string) $invoice->balance_due;

            $this->receipt($invoice, $amount, $paidOn);
        }

        // A service invoice awaiting the accountant's approval.
        $customer = Customer::query()->orderBy('id')->skip(3)->first();
        $service = Product::query()->where('type', 'service')->orderBy('id')->first();

        if ($customer !== null && $service !== null) {
            $date = $this->today()->subDays(2)->setTime(10, 0);
            $this->at($date, fn () => $this->as('sales', function ($user) use ($customer, $service, $date): void {
                $invoice = app(SaveInvoiceAction::class)->handle([
                    'customer_id' => $customer->id,
                    'invoice_date' => $date->toDateString(),
                    'notes' => 'On-site services (demo).',
                    'lines' => [[
                        'product_id' => $service->id,
                        'description' => $service->name,
                        'quantity' => '6',
                        'unit_price' => (string) $service->sale_price,
                        'tax_rate_id' => $this->taxRates['STD'] ?? null,
                    ]],
                ], null, $user->id);

                app(SubmitInvoiceAction::class)->handle($invoice, $user->id);
            }));
        }
    }

    /** Created and submitted by sales@, approved (posted) by accountant@. */
    private function issueInvoice(array $data, Carbon $date): Invoice
    {
        $invoice = $this->at($date, fn () => $this->as('sales', function ($user) use ($data): Invoice {
            $invoice = app(SaveInvoiceAction::class)->handle($data, null, $user->id);

            return app(SubmitInvoiceAction::class)->handle($invoice, $user->id);
        }));

        return $this->at($date->copy()->addHours(3), fn () => $this->as('accountant', fn ($user) => app(PostInvoiceAction::class)->handle($invoice, $user->id)));
    }

    private function receipt(Invoice $invoice, string $amount, Carbon $date): void
    {
        $this->at($date->copy()->setTime(15, 0), fn () => $this->as('accountant', fn ($user) => app(RecordPaymentAction::class)->handle([
            'partner_type' => 'customer',
            'partner_id' => (int) $invoice->customer_id,
            'payment_date' => $date->toDateString(),
            'method' => 'bank_transfer',
            'bank_account_id' => $this->bankId,
            'amount' => $amount,
            'reference' => 'RCPT '.$invoice->number,
            'notes' => 'Customer remittance (demo).',
            'allocations' => [['type' => 'invoice', 'id' => $invoice->id, 'amount' => $amount]],
        ], $user->id)));
    }

    /**
     * One bill per received purchase order for the received quantities,
     * entered by procurement@ and approved by accountant@; most are paid. The
     * most recent bill is left awaiting approval.
     */
    private function bills(): void
    {
        $orders = PurchaseOrder::query()
            ->whereIn('status', ['received', 'partially_received', 'closed'])
            ->with('lines')
            ->orderBy('order_date')
            ->orderBy('id')
            ->get();

        foreach ($orders as $k => $order) {
            $receivedOn = GoodsReceipt::query()->where('purchase_order_id', $order->id)->where('status', 'confirmed')->max('received_date');
            $billDate = $this->clampDate(Carbon::parse($receivedOn ?? $order->order_date)->addDay())->setTime(10, 0);
            $last = $k === $orders->count() - 1;

            $lines = $order->lines
                ->filter(fn ($line) => (float) $line->quantity_received > 0)
                ->map(fn ($line) => [
                    'product_id' => $line->product_id,
                    'description' => $line->description,
                    'quantity' => (string) $line->quantity_received,
                    'unit_price' => (string) $line->unit_price,
                    'discount_percent' => (string) $line->discount_percent,
                    'tax_rate_id' => $this->taxRateId((string) $line->tax_rate),
                ])->values()->all();

            if ($lines === []) {
                continue;
            }

            $bill = $this->at($billDate, fn () => $this->as('procurement', function ($user) use ($order, $billDate, $lines): Bill {
                $bill = app(SaveBillAction::class)->handle([
                    'supplier_id' => $order->supplier_id,
                    'purchase_order_id' => $order->id,
                    'bill_date' => $billDate->toDateString(),
                    'supplier_reference' => 'SINV-'.fake()->numerify('#####'),
                    'branch_id' => $order->branch_id,
                    'notes' => "Bill for purchase order {$order->number}.",
                    'lines' => $lines,
                ], null, $user->id);

                return app(SubmitBillAction::class)->handle($bill, $user->id);
            }));

            if ($last) {
                continue; // awaits approval in the inbox
            }

            $bill = $this->at($billDate->copy()->addDay(), fn () => $this->as('accountant', fn ($user) => app(PostBillAction::class)->handle($bill, $user->id)));

            $paidOn = $billDate->copy()->addDays(fake()->numberBetween(12, 30));
            if ($k % 5 === 3 || $paidOn->greaterThanOrEqualTo($this->today())) {
                continue; // approved, still open
            }

            $amount = (string) $bill->balance_due;
            $this->at($paidOn->copy()->setTime(14, 0), fn () => $this->as('accountant', fn ($user) => app(RecordPaymentAction::class)->handle([
                'partner_type' => 'supplier',
                'partner_id' => (int) $bill->supplier_id,
                'payment_date' => $paidOn->toDateString(),
                'method' => 'bank_transfer',
                'bank_account_id' => $this->bankId,
                'amount' => $amount,
                'reference' => 'PAY '.$bill->supplier_reference,
                'notes' => 'Supplier payment (demo).',
                'allocations' => [['type' => 'bill', 'id' => $bill->id, 'amount' => $amount]],
            ], $user->id)));
        }
    }

    /** Monthly rent (1st) and utilities (12th) paid from the bank, posted by accountant@. */
    private function overheads(): void
    {
        $poster = app(LedgerPoster::class);
        $branchId = Branch::query()->where('is_head_office', true)->value('id');
        $month = $this->historyStart()->startOfMonth();

        while ($month->lessThanOrEqualTo($this->today())) {
            $entries = [
                [$this->weekday($month->copy()), ChartOfAccountsTemplate::RENT, $this->amount(8500), 'Office & warehouse rent '.$month->format('F Y')],
                [$this->weekday($month->copy()->day(12)), ChartOfAccountsTemplate::UTILITIES, $this->amount(fake()->numberBetween(900, 1500)), 'Electricity, water & internet '.$month->format('F Y')],
            ];

            foreach ($entries as [$date, $code, $amount, $memo]) {
                if ($date->greaterThan($this->today())) {
                    continue;
                }

                $this->at($date->copy()->setTime(9, 30), fn () => $this->as('accountant', fn ($user) => $poster->post([
                    'entry_date' => $date->toDateString(),
                    'memo' => $memo.' (demo)',
                    'reference' => strtoupper(substr($memo, 0, 4)).'-'.$date->format('Ym'),
                    'source_type' => JournalSourceType::Manual->value,
                    'branch_id' => $branchId,
                    'lines' => [
                        ['account_code' => $code, 'debit' => $amount, 'credit' => '0', 'description' => $memo],
                        ['account_code' => ChartOfAccountsTemplate::BANK, 'debit' => '0', 'credit' => $amount, 'description' => $memo],
                    ],
                ], $user->id)));
            }

            $month->addMonthNoOverflow();
        }
    }

    /** employee@: one claim approved by manager@ and reimbursed; sales@: one claim awaiting approval. */
    private function expenseClaims(): void
    {
        $travel = Account::query()->where('code', ChartOfAccountsTemplate::TRAVEL)->value('id');
        $supplies = Account::query()->where('code', ChartOfAccountsTemplate::OFFICE_SUPPLIES)->value('id');
        $marketing = Account::query()->where('code', ChartOfAccountsTemplate::MARKETING)->value('id');

        // 1) Approved & paid.
        $date = $this->weekday($this->today()->subDays(21))->setTime(17, 0);
        $claim = $this->at($date, fn () => $this->as('employee', function ($user) use ($date, $travel, $supplies) {
            $claim = app(SaveExpenseClaimAction::class)->handle([
                'employee_id' => Employee::forUser($user)?->id,
                'claim_date' => $date->toDateString(),
                'notes' => 'Client site visit (demo).',
                'lines' => [
                    ['expense_date' => $date->toDateString(), 'category' => 'Travel', 'account_id' => $travel, 'description' => 'Taxi to client site and back', 'amount' => $this->amount(46)],
                    ['expense_date' => $date->toDateString(), 'category' => 'Meals', 'account_id' => $travel, 'description' => 'Working lunch', 'amount' => $this->amount(28.5)],
                    ['expense_date' => $date->toDateString(), 'category' => 'Supplies', 'account_id' => $supplies, 'description' => 'Printed handouts', 'amount' => $this->amount(19)],
                ],
            ], null, $user->id);

            return app(SubmitExpenseClaimAction::class)->handle($claim);
        }));

        $claim = $this->at($date->copy()->addDays(2), fn () => $this->as('manager', fn ($user) => app(DecideExpenseClaimAction::class)->approve($claim, $user->id)));

        $paidOn = $date->copy()->addDays(6);
        $amount = (string) $claim->balance_due;
        $this->at($paidOn, fn () => $this->as('accountant', fn ($user) => app(RecordPaymentAction::class)->handle([
            'partner_type' => 'employee',
            'partner_id' => (int) $claim->user_id,
            'payment_date' => $paidOn->toDateString(),
            'method' => 'bank_transfer',
            'bank_account_id' => $this->bankId,
            'amount' => $amount,
            'reference' => 'REIMB '.$claim->number,
            'allocations' => [['type' => 'expense_claim', 'id' => $claim->id, 'amount' => $amount]],
        ], $user->id)));

        // 2) Pending approval.
        $date = $this->weekday($this->today()->subDays(4))->setTime(18, 0);
        $this->at($date, fn () => $this->as('sales', function ($user) use ($date, $travel, $marketing): void {
            $claim = app(SaveExpenseClaimAction::class)->handle([
                'employee_id' => Employee::forUser($user)?->id,
                'claim_date' => $date->toDateString(),
                'notes' => 'Trade fair attendance (demo).',
                'lines' => [
                    ['expense_date' => $date->toDateString(), 'category' => 'Travel', 'account_id' => $travel, 'description' => 'Train tickets', 'amount' => $this->amount(120)],
                    ['expense_date' => $date->toDateString(), 'category' => 'Marketing', 'account_id' => $marketing, 'description' => 'Brochure printing', 'amount' => $this->amount(85)],
                ],
            ], null, $user->id);

            app(SubmitExpenseClaimAction::class)->handle($claim);
        }));
    }
}
