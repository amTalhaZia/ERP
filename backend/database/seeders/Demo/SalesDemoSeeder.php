<?php

namespace Database\Seeders\Demo;

use App\Actions\Sales\ConfirmDeliveryAction;
use App\Actions\Sales\ConvertLeadAction;
use App\Actions\Sales\CreateDeliveryAction;
use App\Actions\Sales\CreateSalesOrderFromQuotationAction;
use App\Actions\Sales\UpsertQuotationAction;
use App\Actions\Sales\UpsertSalesOrderAction;
use App\Enums\Sales\QuotationStatus;
use App\Enums\Sales\SalesOrderStatus;
use App\Events\Sales\SalesOrderConfirmed;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Inventory\Product;
use App\Models\Sales\Customer;
use App\Models\Sales\CustomerCommunication;
use App\Models\Sales\CustomerContact;
use App\Models\Sales\Lead;
use App\Models\Sales\Opportunity;
use App\Models\Sales\PipelineStage;
use App\Models\Sales\Quotation;
use App\Models\Sales\SalesOrder;
use App\Support\Audit\AuditLogger;
use App\Support\Numbering\DocumentNumberer;
use Database\Seeders\Demo\Concerns\SeedsDemoCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DEVELOPMENT ONLY. Customers (geo-coded around the head office) with contacts
 * and communications, leads (two converted), opportunities across the
 * pipeline, quotations and sales orders spanning the history window. Orders
 * are confirmed by sales@, approved by admin@ and delivered — confirming a
 * delivery issues stock from MAIN through the inventory integration.
 * Invoicing of delivered orders happens in FinanceDemoSeeder.
 */
class SalesDemoSeeder extends Seeder
{
    use SeedsDemoCompany;

    private const CUSTOMERS = [
        'northwind.test' => [
            'Acorn Legal Partners', 'Brightline Dental Group', 'Copperleaf Architects', 'Driftwood Media',
            'Elm Street Clinic', 'Foxglove Consulting', 'Glasshouse Studios', 'Hillcrest Academy',
            'Inkwell Publishing', 'Jasper & Cole Accountants', 'Kestrel Logistics', 'Lighthouse Realty',
            'Maple Grove Insurance', 'Northstar Fitness', 'Oakridge Engineering', 'Pinecone Software',
            'Quarry Lane Bakery', 'Riverside Veterinary', 'Silverline Travel', 'Tidewater Marine Supply',
        ],
        'indus.test' => [
            'Clifton Linen House', 'Saddar Garments', 'Korangi Hospitality', 'Gulshan Uniforms',
            'Malir Hotels Group', 'Port Qasim Traders', 'Tariq Road Fabrics', 'Nazimabad Bedding',
            'Sea View Resorts', 'Shahrah Furnishings', 'Landhi Industrial Supply', 'Keamari Exporters',
            'Zamzama Interiors', 'Bahadurabad Retail', 'Defence Textile Outlet', 'Gizri Boutique',
            'Orangi Stitching Unit', 'Hyderabad Mills Outlet', 'Lyari Workwear Co.', 'Clifton Home Stores',
        ],
    ];

    /** @var Collection<int, Product> */
    private Collection $goods;

    /** @var Collection<int, Product> */
    private Collection $services;

    /** @var array<int, float> product_id => quantity still available in MAIN for this seeder */
    private array $available = [];

    public function run(Company $company): void
    {
        $this->bootDemo($company, 'sales');

        $main = $this->mainWarehouse();
        $this->goods = $this->regularGoods();
        $this->services = Product::query()->where('type', 'service')->orderBy('id')->get();
        foreach ($this->goods as $product) {
            $this->available[$product->id] = $this->onHand($product->id, $main->id) - 5;
        }

        $customers = $this->customers();
        $this->leadsAndOpportunities($customers);
        $this->ordersAndQuotations($customers);

        $this->info($customers->count().' customers, '.SalesOrder::query()->count().' sales orders, '.Quotation::query()->count().' quotations');
    }

    /** @return Collection<int, Customer> */
    private function customers(): Collection
    {
        $names = self::CUSTOMERS[$this->domain] ?? self::CUSTOMERS['northwind.test'];
        $hq = Branch::query()->where('is_head_office', true)->firstOrFail();
        $numberer = app(DocumentNumberer::class);
        $sales = $this->demoUser('sales');
        $customers = collect();

        $this->as('sales', function () use ($names, $hq, $numberer, $sales, &$customers): void {
            foreach ($names as $i => $name) {
                $slug = Str::limit(Str::slug(Str::before($name, ' &'), ''), 24, '');
                $street = fake()->buildingNumber().' '.fake()->randomElement(['Market Street', 'Park Avenue', 'Harbor Road', 'Main Boulevard', 'Commerce Lane']);
                $customer = Customer::query()->create([
                    'code' => $numberer->next('customer', yearly: false, prefix: 'CUST', padding: 5),
                    'name' => $name,
                    'type' => $i % 7 === 6 ? 'individual' : 'company',
                    'email' => "accounts@{$slug}.example",
                    'phone' => $this->phone(5),
                    'website' => "https://{$slug}.example",
                    'tax_number' => fake()->numerify('TX-#######'),
                    'currency' => $this->company->currency,
                    'payment_terms_days' => [30, 30, 15, 45, 30][$i % 5],
                    'credit_limit' => $this->amount([10000, 25000, 50000][$i % 3]),
                    'billing_address_line1' => $street,
                    'billing_city' => $this->company->city,
                    'billing_country' => $this->company->country,
                    'shipping_address_line1' => $street,
                    'shipping_city' => $this->company->city,
                    'shipping_country' => $this->company->country,
                    'latitude' => round((float) $hq->latitude + fake()->randomFloat(4, -0.15, 0.15), 7),
                    'longitude' => round((float) $hq->longitude + fake()->randomFloat(4, -0.15, 0.15), 7),
                    'sales_rep_id' => $sales->id,
                    'source' => fake()->randomElement(['website', 'referral', 'event', 'campaign']),
                    'notes' => 'Fictional demo customer.',
                    'is_active' => true,
                ]);

                foreach (range(1, $i % 3 === 0 ? 2 : 1) as $n) {
                    CustomerContact::query()->create([
                        'customer_id' => $customer->id,
                        'name' => fake()->name(),
                        'title' => fake()->randomElement(['Office Manager', 'Procurement Lead', 'Director', 'Finance Manager']),
                        'email' => "contact{$n}@{$slug}.example",
                        'phone' => $this->phone(6),
                        'is_primary' => $n === 1,
                    ]);
                }

                foreach (range(1, 1 + $i % 2) as $n) {
                    $type = fake()->randomElement(['call', 'email', 'meeting', 'note']);
                    CustomerCommunication::query()->create([
                        'customer_id' => $customer->id,
                        'user_id' => $sales->id,
                        'type' => $type,
                        'subject' => match ($type) {
                            'call' => 'Follow-up call on recent order',
                            'email' => 'Sent updated price list',
                            'meeting' => 'Quarterly account review',
                            default => 'Account note',
                        },
                        'body' => 'Demo interaction — fictional content.',
                        'happened_at' => $this->realNow()->subDays(fake()->numberBetween(1, 60))->setTime(fake()->numberBetween(9, 17), 0),
                        'follow_up_at' => $n === 1 && $i % 4 === 0 ? $this->realNow()->addDays(fake()->numberBetween(2, 20))->setTime(10, 0) : null,
                    ]);
                }

                $customers->push($customer);
            }
        });

        return $customers;
    }

    /** @param  Collection<int, Customer>  $customers */
    private function leadsAndOpportunities(Collection $customers): void
    {
        $sales = $this->demoUser('sales');
        $stages = PipelineStage::query()->orderBy('position')->get()->keyBy('name');
        $statuses = ['new', 'new', 'contacted', 'contacted', 'qualified', 'qualified', 'unqualified', 'contacted', 'qualified', 'qualified'];
        $leads = [];

        $this->as('sales', function () use ($sales, $statuses, &$leads): void {
            foreach ($statuses as $i => $status) {
                $company = fake()->randomElement(['Summit', 'Harborview', 'Bluebell', 'Granite', 'Orchard', 'Sterling', 'Willow', 'Crescent']).' '.fake()->randomElement(['Partners', 'Group', 'Holdings', 'Studio', 'Traders']);
                $leads[] = Lead::query()->create([
                    'name' => fake()->name(),
                    'company_name' => "{$company} (Demo {$i})",
                    'email' => 'lead'.($i + 1).'@'.Str::slug($company, '').'.example',
                    'phone' => $this->phone(7),
                    'source' => fake()->randomElement(['website', 'referral', 'campaign', 'cold_call', 'event']),
                    'status' => $status,
                    'estimated_value' => $this->amount(fake()->numberBetween(20, 150) * 100),
                    'assigned_to' => $sales->id,
                    'notes' => 'Demo lead.',
                ]);
            }
        });

        $convert = app(ConvertLeadAction::class);
        foreach ([4, 8] as $i) {
            $this->as('sales', fn ($user) => $convert->handle($leads[$i], true, $stages['Qualified']->id ?? null, $user));
        }

        // [stage, value (USD), close in days]
        $plan = [
            ['New', 4000, 45], ['New', 2500, 60], ['Qualified', 6000, 30], ['Qualified', 8000, 40],
            ['Proposal', 12000, 20], ['Proposal', 5500, 25], ['Negotiation', 15000, 10], ['Negotiation', 9000, 14],
            ['Won', 7000, -10], ['Lost', 3000, -20],
        ];

        $this->as('sales', function () use ($plan, $stages, $customers, $sales): void {
            foreach ($plan as $i => [$stageName, $value, $closeIn]) {
                $customer = $customers[($i * 3) % $customers->count()];
                $opportunity = new Opportunity([
                    'title' => $customer->name.' — '.fake()->randomElement(['Annual supply contract', 'Office refresh', 'Bulk order', 'Service agreement', 'Expansion project']),
                    'customer_id' => $customer->id,
                    'value' => $this->amount($value),
                    'currency' => $this->company->currency,
                    'expected_close_date' => $this->today()->addDays($closeIn)->toDateString(),
                    'owner_id' => $sales->id,
                ]);
                $opportunity->applyStage($stages[$stageName], $stageName === 'Lost' ? 'Chose a competitor on price.' : null);
                $opportunity->save();
            }
        });
    }

    /**
     * @param  Collection<int, Customer>  $customers
     */
    private function ordersAndQuotations(Collection $customers): void
    {
        $start = $this->historyStart()->addDays(10);
        $span = max(20, $start->diffInDays($this->today()->subDays(6)));
        $count = 16;

        for ($i = 0; $i < $count; $i++) {
            $date = $this->weekday($start->copy()->addDays((int) round($span * $i / $count)))->setTime(11, 0);
            $customer = $customers[($i * 7) % $customers->count()];
            $lines = $this->orderLines($i);

            if ($lines === []) {
                continue;
            }

            if ($i % 3 === 0) {
                $quotation = $this->quotation($customer, $lines, $date->copy()->subDays(5), 'accept');
                $order = $this->at($date, fn () => $this->as('sales', fn ($user) => app(CreateSalesOrderFromQuotationAction::class)->handle($quotation, $user)));
                $order->forceFill(['order_date' => $date->toDateString(), 'warehouse_id' => $this->mainWarehouse()->id])->save();
            } else {
                $order = $this->at($date, fn () => $this->as('sales', fn ($user) => app(UpsertSalesOrderAction::class)->handle([
                    'customer_id' => $customer->id,
                    'order_date' => $date->toDateString(),
                    'expected_delivery_date' => $date->copy()->addDays(5)->toDateString(),
                    'warehouse_id' => $this->mainWarehouse()->id,
                    'notes' => 'Demo order.',
                    'terms' => 'Payment per agreed terms.',
                    'lines' => $lines,
                ], null, $user)));
            }

            $this->confirm($order, $date);

            if ($i === $count - 1) {
                continue; // stays Confirmed: awaits approval in the inbox
            }

            $this->at($date->copy()->addHours(4), fn () => $this->as('admin', fn ($user) => $this->approve($order, $user->id)));

            if ($i === $count - 2) {
                continue; // approved, not yet delivered
            }

            $this->deliver($order, $date->copy()->addDays(fake()->numberBetween(2, 5)), partial: $i === $count - 3);
        }

        // Open quotations and one draft order for the lists.
        $this->quotation($customers[1], $this->orderLines(100), $this->today()->subDays(6), 'send');
        $this->quotation($customers[5], $this->orderLines(101), $this->today()->subDays(4), 'send');
        $this->quotation($customers[9], $this->orderLines(102), $this->today()->subDays(12), 'accept');
        $this->quotation($customers[11], $this->orderLines(103), $this->today()->subDays(9), 'accept');
        $this->quotation($customers[13], $this->orderLines(104), $this->today()->subDays(1), 'draft');

        $this->as('sales', fn ($user) => app(UpsertSalesOrderAction::class)->handle([
            'customer_id' => $customers[2]->id,
            'warehouse_id' => $this->mainWarehouse()->id,
            'notes' => 'Draft order (demo).',
            'lines' => $this->orderLines(105),
        ], null, $user));
    }

    /**
     * 1–3 goods lines that MAIN can still supply, plus an occasional service line.
     *
     * @return list<array<string, mixed>>
     */
    private function orderLines(int $seed): array
    {
        $lines = [];
        $picked = fake()->randomElements($this->goods->all(), fake()->numberBetween(1, 3));

        foreach ($picked as $product) {
            $cost = (float) $product->cost_price / $this->scale();
            $quantity = match (true) {
                $cost < 5 => fake()->numberBetween(5, 20) * 10,
                $cost < 50 => fake()->numberBetween(3, 15),
                default => fake()->numberBetween(1, 4),
            };

            if (($this->available[$product->id] ?? 0) < $quantity) {
                continue;
            }

            $this->available[$product->id] -= $quantity;
            $lines[] = [
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => $quantity,
                'uom' => null,
                'unit_price' => (string) $product->sale_price,
                'discount_percent' => $quantity >= 10 ? 5 : 0,
                'tax_rate' => 15,
            ];
        }

        if ($seed % 4 === 1 && $this->services->isNotEmpty()) {
            $service = $this->services[$seed % $this->services->count()];
            $lines[] = [
                'product_id' => $service->id,
                'description' => $service->name,
                'quantity' => fake()->numberBetween(2, 6),
                'uom' => null,
                'unit_price' => (string) $service->sale_price,
                'discount_percent' => 0,
                'tax_rate' => 15,
            ];
        }

        return $lines;
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function quotation(Customer $customer, array $lines, Carbon $date, string $outcome): ?Quotation
    {
        if ($lines === []) {
            return null;
        }

        $date = $this->clampDate($date)->setTime(10, 0);

        $quotation = $this->at($date, fn () => $this->as('sales', fn ($user) => app(UpsertQuotationAction::class)->handle([
            'customer_id' => $customer->id,
            'date' => $date->toDateString(),
            'valid_until' => $date->copy()->addDays(30)->toDateString(),
            'notes' => 'Prices valid for 30 days (demo).',
            'terms' => 'Delivery within 5 working days of order.',
            'lines' => $lines,
        ], null, $user)));

        if ($outcome === 'draft') {
            return $quotation;
        }

        $audit = app(AuditLogger::class);
        $this->at($date->copy()->addHour(), function () use ($quotation, $audit): void {
            $quotation->forceFill(['status' => QuotationStatus::Sent, 'sent_at' => now()])->save();
            $audit->log('quotations.sent', $quotation);
        });

        if ($outcome === 'accept') {
            $this->at($date->copy()->addDays(2), function () use ($quotation, $audit): void {
                $quotation->forceFill(['status' => QuotationStatus::Accepted, 'accepted_at' => now()])->save();
                $audit->log('quotations.accepted', $quotation);
            });
        }

        return $quotation->refresh();
    }

    /** Draft → Confirmed, exactly as SalesOrderController::confirm does. */
    private function confirm(SalesOrder $order, Carbon $date): void
    {
        $this->at($date->copy()->addHour(), fn () => $this->as('sales', function () use ($order): void {
            DB::transaction(function () use ($order): void {
                $order->forceFill(['status' => SalesOrderStatus::Confirmed, 'confirmed_at' => now()])->save();
                app(AuditLogger::class)->log('sales_orders.confirmed', $order);

                SalesOrderConfirmed::dispatch($order->load(['lines', 'customer']));
            });
        }));
    }

    /** Confirmed → Approved, exactly as SalesOrderController::approve does. */
    private function approve(SalesOrder $order, int $userId): void
    {
        $order->forceFill([
            'status' => SalesOrderStatus::Approved,
            'approved_by' => $userId,
            'approved_at' => now(),
        ])->save();
        app(AuditLogger::class)->log('sales_orders.approved', $order);
    }

    private function deliver(SalesOrder $order, Carbon $date, bool $partial): void
    {
        $date = $this->clampDate($date)->setTime(9, 0);
        $lines = $order->lines()->orderBy('line_no')->get()->map(fn ($line) => [
            'sales_order_line_id' => $line->id,
            'quantity' => $partial && (float) $line->quantity >= 2 ? (string) floor((float) $line->quantity / 2) : (string) $line->quantity,
        ])->all();

        $delivery = $this->at($date, fn () => $this->as('inventory', fn ($user) => app(CreateDeliveryAction::class)->handle($order->refresh(), [
            'delivery_date' => $date->toDateString(),
            'carrier' => fake()->randomElement(['Own fleet', 'Demo Express', 'City Couriers']),
            'tracking_number' => 'TRK'.fake()->numerify('########'),
            'notes' => 'Demo delivery.',
            'lines' => $lines,
        ], $user)));

        $this->at($date->copy()->addHours(6), fn () => $this->as('inventory', fn () => app(ConfirmDeliveryAction::class)->handle($delivery)));
    }
}
