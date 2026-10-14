<?php

namespace Database\Seeders\Demo\Concerns;

use App\Actions\Finance\GeneratePeriodsAction;
use App\Models\Company;
use App\Models\Finance\Account;
use App\Models\Finance\BankAccount;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * DEVELOPMENT ONLY. Shared plumbing for the per-module demo seeders: tenant
 * context, demo-user lookup, "act as" and "travel to" helpers, the demo
 * history window and a currency scale so PKR amounts look plausible.
 */
trait SeedsDemoCompany
{
    protected Company $company;

    protected string $domain;

    /** @var array<string, User> */
    private array $demoUsers = [];

    protected function bootDemo(Company $company, string $module): void
    {
        $this->company = $company;
        $this->domain = Str::after((string) $company->email, '@');
        $this->demoUsers = [];

        app(TenantContext::class)->set($company, null);
        fake()->seed(crc32($company->name.'|'.$module));
    }

    /** Demo user by email local-part: owner, admin, hr, accountant, sales, inventory, procurement, manager, employee, auditor. */
    protected function demoUser(string $local): User
    {
        return $this->demoUsers[$local] ??= User::query()->where('email', "{$local}@{$this->domain}")->firstOrFail();
    }

    /**
     * Run $callback authenticated as a demo user (auth()->id() is used for
     * created_by / posted_by by several actions).
     *
     * @template T
     *
     * @param  callable(User): T  $callback
     * @return T
     */
    protected function as(string $local, callable $callback): mixed
    {
        $guard = Auth::guard('web');
        $previous = $guard->user();
        $user = $this->demoUser($local);
        $guard->setUser($user);

        try {
            return $callback($user);
        } finally {
            $previous !== null ? $guard->setUser($previous) : $guard->forgetUser();
        }
    }

    /**
     * Run $callback with the clock frozen at $when (actions that stamp now()
     * then record history). The previous test-now is always restored.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function at(CarbonInterface|string $when, callable $callback): mixed
    {
        $previous = Carbon::getTestNow();
        $previousImmutable = CarbonImmutable::getTestNow();
        $moment = Carbon::parse($when);

        if ($moment->isAfter($this->realNow())) {
            $moment = $this->realNow();
        }

        Carbon::setTestNow($moment);
        \Carbon\Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment->toImmutable());

        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previous);
            \Carbon\Carbon::setTestNow($previous);
            CarbonImmutable::setTestNow($previousImmutable);
        }
    }

    /** Wall-clock now, ignoring any frozen test time. */
    protected function realNow(): Carbon
    {
        return Carbon::createFromTimestamp(time(), config('app.timezone'));
    }

    protected function today(): Carbon
    {
        return $this->realNow()->startOfDay();
    }

    /** First day of the fiscal year containing today. */
    protected function fiscalStart(): Carbon
    {
        $previous = Carbon::getTestNow();
        Carbon::setTestNow();

        try {
            return GeneratePeriodsAction::currentYearStart($this->company)->startOfDay();
        } finally {
            Carbon::setTestNow($previous);
        }
    }

    /** Demo history starts at max(fiscal year start, start of the month six months ago). */
    protected function historyStart(): Carbon
    {
        $sixMonths = $this->today()->subMonthsNoOverflow(6)->startOfMonth();

        return $sixMonths->max($this->fiscalStart())->copy();
    }

    /** A fictional phone number in the company's country format (block keeps numbers distinct per entity type). */
    protected function phone(int $block): string
    {
        return strtoupper((string) $this->company->country) === 'PK'
            ? fake()->numerify("+92 30{$block} 0######")
            : fake()->numerify("+1 555 0{$block}## ####");
    }

    /** $date clamped into [historyStart, today]. */
    protected function clampDate(CarbonInterface $date): Carbon
    {
        $date = Carbon::parse($date);

        return $date->max($this->historyStart())->min($this->today())->copy();
    }

    /** Next weekday on or after $date. */
    protected function weekday(CarbonInterface $date): Carbon
    {
        $date = Carbon::parse($date);

        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date;
    }

    /** Currency scale: PKR prices/salaries are ~100x the USD figures. */
    protected function scale(): int
    {
        return strtoupper((string) $this->company->currency) === 'PKR' ? 100 : 1;
    }

    /** A USD-denominated base amount scaled to the company currency, as a 2-dp string. */
    protected function amount(float|int $base): string
    {
        return number_format($base * $this->scale(), 2, '.', '');
    }

    protected function mainWarehouse(): Warehouse
    {
        return Warehouse::query()->where('code', 'MAIN')->firstOrFail();
    }

    /**
     * Stocked goods without batch/serial tracking and not deliberately kept
     * low — safe to buy and sell in the demo document flows.
     *
     * @return Collection<int, Product>
     */
    protected function regularGoods(): Collection
    {
        return Product::query()
            ->where('type', 'goods')
            ->where('track_batches', false)
            ->where('track_serials', false)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (Product $p) => (($p->getAttribute('attributes') ?? [])['demo_flag'] ?? null) === null)
            ->values();
    }

    /** On-hand quantity of a product in a warehouse. */
    protected function onHand(int $productId, int $warehouseId): float
    {
        return (float) StockLevel::query()->where('product_id', $productId)->where('warehouse_id', $warehouseId)->sum('quantity_on_hand');
    }

    /** The operating bank account (GL 1010), created on first use. */
    protected function bankAccount(): BankAccount
    {
        $existing = BankAccount::query()->where('name', 'Operating Account (Demo)')->first();

        if ($existing !== null) {
            return $existing;
        }

        $glId = Account::query()->where('code', ChartOfAccountsTemplate::BANK)->value('id');

        return BankAccount::query()->create([
            'account_id' => $glId,
            'name' => 'Operating Account (Demo)',
            'bank_name' => 'Demo Bank (fictional)',
            'account_number' => '000000'.str_pad((string) $this->company->id, 6, '0', STR_PAD_LEFT),
            'currency' => $this->company->currency ?: 'USD',
            'opening_balance' => '0',
            'is_active' => true,
        ]);
    }

    protected function info(string $message): void
    {
        $this->command?->line("    <fg=gray>{$this->company->name}:</> {$message}");
    }
}
