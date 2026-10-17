<?php

namespace Database\Seeders\Demo;

use App\Actions\Finance\Receivables\PostBillAction;
use App\Actions\Finance\Receivables\PostInvoiceAction;
use App\Actions\Finance\Receivables\RecordPaymentAction;
use App\Actions\Finance\Receivables\SaveBillAction;
use App\Actions\Finance\Receivables\SaveInvoiceAction;
use App\Actions\Finance\Receivables\SubmitBillAction;
use App\Actions\Finance\Receivables\SubmitInvoiceAction;
use App\Enums\Finance\JournalSourceType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Finance\Bill;
use App\Models\Finance\Invoice;
use App\Models\Finance\TaxRate;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Models\Procurement\Supplier;
use App\Models\Sales\Customer;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Services\Finance\LedgerPoster;
use App\Support\Money\Money;
use Database\Seeders\Demo\Concerns\SeedsDemoCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * DEVELOPMENT / DEMO ONLY. Accounting data for the small demo (no sales or
 * purchase orders needed): opening capital, 10 customer invoices and 10
 * supplier bills in mixed states with their receipts/payments, and three
 * months of rent + utilities. Created by admin@, approved by accountant@.
 */
class SmallFinanceDemoSeeder extends Seeder
{
    use SeedsDemoCompany;

    private int $bankId;

    private ?int $taxId;

    public function run(Company $company): void
    {
        $this->bootDemo($company, 'small-finance');
        $this->bankId = $this->bankAccount()->id;
        $this->taxId = TaxRate::query()->where('code', 'STD')->value('id');

        $this->openingBalance();
        $this->invoices();
        $this->bills();
        $this->overheads();

        $this->info(Invoice::query()->count().' invoices, '.Bill::query()->count().' bills');
    }

    private function openingBalance(): void
    {
        $capital = $this->amount(150000);
        $stock = Money::round(Money::normalize((string) StockMovement::query()->where('type', 'opening')->sum('total_cost')), 2);
        $date = $this->today()->subDays(90)->startOfMonth();

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

    /** 10 invoices: 5 paid, 2 part-paid, 2 open (one overdue), 1 waiting for approval. */
    private function invoices(): void
    {
        $customers = Customer::query()->orderBy('id')->pluck('id')->all();
        $products = Product::query()->where('type', 'goods')->orderBy('id')->get();
        $plans = ['paid', 'paid', 'part', 'paid', 'open', 'paid', 'part', 'paid', 'open', 'pending'];

        foreach ($plans as $i => $plan) {
            $date = $this->weekday($this->today()->subDays(70 - $i * 7))->setTime(11, 0);
            $product = $products[$i % $products->count()];
            $data = [
                'customer_id' => $customers[$i % count($customers)],
                'invoice_date' => $date->toDateString(),
                'notes' => 'Demo invoice.',
                'terms' => 'Payment due within 30 days.',
                'lines' => [[
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => (string) (2 + $i % 4),
                    'unit_price' => (string) $product->sale_price,
                    'tax_rate_id' => $this->taxId,
                ]],
            ];

            $invoice = $this->at($date, fn () => $this->as('admin', function ($user) use ($data): Invoice {
                $invoice = app(SaveInvoiceAction::class)->handle($data, null, $user->id);

                return app(SubmitInvoiceAction::class)->handle($invoice, $user->id);
            }));

            if ($plan === 'pending') {
                continue; // in the accountant's approval inbox
            }

            $invoice = $this->at($date->copy()->addHours(3), fn () => $this->as('accountant', fn ($user) => app(PostInvoiceAction::class)->handle($invoice, $user->id)));

            if ($plan === 'open') {
                continue;
            }

            $paidOn = $this->clampDate($date->copy()->addDays(12));
            $amount = $plan === 'part'
                ? Money::round(Money::mul((string) $invoice->balance_due, '0.5'), 2)
                : (string) $invoice->balance_due;

            $this->pay('customer', (int) $invoice->customer_id, 'invoice', $invoice->id, $amount, $paidOn, 'RCPT '.$invoice->number);
        }
    }

    /** 10 bills: 6 paid, 3 approved but unpaid, 1 waiting for approval. */
    private function bills(): void
    {
        $suppliers = Supplier::query()->orderBy('id')->pluck('id')->all();
        $products = Product::query()->where('type', 'goods')->orderBy('id')->get();
        $branchId = Branch::query()->where('is_head_office', true)->value('id');
        $plans = ['paid', 'paid', 'open', 'paid', 'paid', 'open', 'paid', 'paid', 'open', 'pending'];

        foreach ($plans as $i => $plan) {
            $date = $this->weekday($this->today()->subDays(75 - $i * 7))->setTime(10, 0);
            $product = $products[($i + 1) % $products->count()];
            $data = [
                'supplier_id' => $suppliers[$i % count($suppliers)],
                'bill_date' => $date->toDateString(),
                'supplier_reference' => sprintf('SINV-%05d', 1200 + $i),
                'branch_id' => $branchId,
                'notes' => 'Demo supplier bill.',
                'lines' => [[
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => (string) (10 + $i * 2),
                    'unit_price' => (string) $product->cost_price,
                    'tax_rate_id' => $this->taxId,
                ]],
            ];

            $bill = $this->at($date, fn () => $this->as('admin', function ($user) use ($data): Bill {
                $bill = app(SaveBillAction::class)->handle($data, null, $user->id);

                return app(SubmitBillAction::class)->handle($bill, $user->id);
            }));

            if ($plan === 'pending') {
                continue;
            }

            $bill = $this->at($date->copy()->addDay(), fn () => $this->as('accountant', fn ($user) => app(PostBillAction::class)->handle($bill, $user->id)));

            if ($plan === 'paid') {
                $this->pay('supplier', (int) $bill->supplier_id, 'bill', $bill->id, (string) $bill->balance_due, $this->clampDate($date->copy()->addDays(15)), 'PAY '.$bill->supplier_reference);
            }
        }
    }

    private function pay(string $partner, int $partnerId, string $type, int $id, string $amount, Carbon $date, string $reference): void
    {
        $this->at($date->copy()->setTime(15, 0), fn () => $this->as('accountant', fn ($user) => app(RecordPaymentAction::class)->handle([
            'partner_type' => $partner,
            'partner_id' => $partnerId,
            'payment_date' => $date->toDateString(),
            'method' => 'bank_transfer',
            'bank_account_id' => $this->bankId,
            'amount' => $amount,
            'reference' => $reference,
            'notes' => $partner === 'customer' ? 'Customer payment (demo).' : 'Supplier payment (demo).',
            'allocations' => [['type' => $type, 'id' => $id, 'amount' => $amount]],
        ], $user->id)));
    }

    /** Rent and utilities for the last three months, paid from the bank. */
    private function overheads(): void
    {
        $poster = app(LedgerPoster::class);
        $branchId = Branch::query()->where('is_head_office', true)->value('id');

        for ($m = 2; $m >= 0; $m--) {
            $month = $this->today()->subMonthsNoOverflow($m)->startOfMonth();
            foreach ([
                [$this->weekday($month->copy()->day(2)), ChartOfAccountsTemplate::RENT, $this->amount(4200), 'Office rent '.$month->format('F Y')],
                [$this->weekday($month->copy()->day(12)), ChartOfAccountsTemplate::UTILITIES, $this->amount(650 + $m * 40), 'Electricity, water & internet '.$month->format('F Y')],
            ] as [$date, $code, $amount, $memo]) {
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
        }
    }
}
