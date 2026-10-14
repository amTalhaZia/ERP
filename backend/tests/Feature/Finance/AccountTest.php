<?php

namespace Tests\Feature\Finance;

use App\Actions\Finance\SeedCompanyFinanceDefaultsAction;
use App\Events\CompanyProvisioned;
use App\Listeners\Finance\SeedChartOfAccounts;
use App\Models\Company;
use App\Models\Finance\Account;
use App\Models\Finance\FinancialPeriod;
use App\Models\Finance\TaxRate;
use App\Services\Finance\ChartOfAccountsTemplate;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Finance\Concerns\InteractsWithLedger;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use InteractsWithLedger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeFinanceClock();
    }

    public function test_listener_is_registered_for_company_provisioning(): void
    {
        Event::fake();

        Event::assertListening(CompanyProvisioned::class, SeedChartOfAccounts::class);
    }

    public function test_provisioning_seeds_chart_year_periods_and_tax_rates(): void
    {
        $company = Company::factory()->create(['fiscal_year_start_month' => 4, 'currency' => 'EUR']);

        app(SeedChartOfAccounts::class)->handle(new CompanyProvisioned($company));

        $this->assertSame(count(ChartOfAccountsTemplate::accounts()), Account::forCompany($company->id)->where('is_system', true)->count());
        $this->assertSame('EUR', $this->account($company, '1100')->currency);
        $this->assertTrue($this->account($company, '1010')->is_bank);
        $this->assertTrue($this->account($company, '1000')->is_cash);

        $periods = FinancialPeriod::forCompany($company->id)->orderBy('start_date')->get();
        $this->assertCount(12, $periods);
        $this->assertSame('2026-04-01', $periods->first()->start_date->toDateString());
        $this->assertSame('2027-03-31', $periods->last()->end_date->toDateString());
        $this->assertSame('Apr 2026', $periods->first()->name);

        $standard = TaxRate::forCompany($company->id)->where('code', 'STD')->firstOrFail();
        $this->assertSame('15.0000', $standard->rate);
        $this->assertSame($this->account($company, '2100')->id, $standard->account_id);
        $this->assertTrue(TaxRate::forCompany($company->id)->where('code', 'ZERO')->where('rate', 0)->exists());
    }

    public function test_seed_defaults_endpoint_is_idempotent(): void
    {
        [$user, $company] = $this->userWith(['accounts.view', 'accounts.manage']);

        $this->actAs($user, $company)->postJson('/api/v1/accounts/seed-defaults')
            ->assertOk()
            ->assertJsonPath('data.accounts', count(ChartOfAccountsTemplate::accounts()))
            ->assertJsonPath('data.financial_years', 1)
            ->assertJsonPath('data.tax_rates', 2);

        $this->postJson('/api/v1/accounts/seed-defaults')
            ->assertOk()
            ->assertJsonPath('data.accounts', 0)
            ->assertJsonPath('data.financial_years', 0)
            ->assertJsonPath('data.tax_rates', 0);

        [$viewer] = $this->userWith(['accounts.view'], $company);
        $this->actAs($viewer, $company)->postJson('/api/v1/accounts/seed-defaults')->assertForbidden();
    }

    public function test_lists_filters_and_searches_accounts(): void
    {
        [$user, $company] = $this->financeUser(['accounts.view']);
        $this->actAs($user, $company);

        $this->getJson('/api/v1/accounts?filter[type]=revenue&per_page=100')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/accounts?filter[is_bank]=true')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', '1010');
        $this->getJson('/api/v1/accounts?search=Receivable')->assertOk()->assertJsonPath('data.0.code', '1100')
            ->assertJsonPath('data.0.normal_balance', 'debit');
        $this->getJson('/api/v1/accounts/tree')->assertOk()->assertJsonCount(count(ChartOfAccountsTemplate::accounts()), 'data');
    }

    public function test_creates_a_child_account_and_returns_it_in_the_tree(): void
    {
        [$user, $company] = $this->financeUser(['accounts.view', 'accounts.manage']);
        $bank = $this->account($company, '1010');

        $this->actAs($user, $company)->postJson('/api/v1/accounts', [
            'code' => '1011', 'name' => 'Bank - USD Operating', 'type' => 'asset', 'subtype' => 'bank',
            'is_bank' => true, 'parent_id' => $bank->id,
        ])->assertCreated()
            ->assertJsonPath('data.parent.code', '1010')
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.currency', 'USD');

        $tree = collect($this->getJson('/api/v1/accounts/tree')->json('data'));
        $this->assertSame('1011', $tree->firstWhere('code', '1010')['children'][0]['code']);
    }

    public function test_validation_rejects_duplicate_code_and_foreign_parent(): void
    {
        [$user, $company] = $this->financeUser(['accounts.view', 'accounts.manage']);
        $other = $this->seededCompany();

        $this->actAs($user, $company)->postJson('/api/v1/accounts', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['code', 'name', 'type']);

        $this->postJson('/api/v1/accounts', [
            'code' => '1000', 'name' => 'Dup', 'type' => 'asset', 'parent_id' => $this->account($other, '1010')->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['code', 'parent_id']);
    }

    public function test_account_with_journal_lines_cannot_be_deleted_deactivated_or_retyped(): void
    {
        [$user, $company] = $this->financeUser(['accounts.view', 'accounts.manage']);
        $custom = Account::factory()->expense()->create(['company_id' => $company->id, 'code' => '6450']);
        $this->postSimple($company, '2026-03-10', '6450', '1010', '75.00');

        $this->actAs($user, $company)->deleteJson("/api/v1/accounts/{$custom->id}")
            ->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->putJson("/api/v1/accounts/{$custom->id}", ['is_active' => false])->assertStatus(409);
        $this->putJson("/api/v1/accounts/{$custom->id}", ['type' => 'asset'])->assertStatus(409);

        $this->assertDatabaseHas('accounts', ['id' => $custom->id, 'is_active' => true, 'type' => 'expense']);

        $this->putJson("/api/v1/accounts/{$custom->id}", ['name' => 'Online ads'])->assertOk()->assertJsonPath('data.name', 'Online ads');
    }

    public function test_unused_custom_account_can_be_deleted_but_system_accounts_cannot(): void
    {
        [$user, $company] = $this->financeUser(['accounts.view', 'accounts.manage']);
        $custom = Account::factory()->expense()->create(['company_id' => $company->id, 'code' => '6460']);

        $this->actAs($user, $company)->deleteJson("/api/v1/accounts/{$custom->id}")->assertNoContent();
        $this->assertDatabaseMissing('accounts', ['id' => $custom->id]);

        $system = $this->account($company, '1100');
        $this->deleteJson("/api/v1/accounts/{$system->id}")->assertForbidden();
        $this->assertDatabaseHas('accounts', ['id' => $system->id]);
    }

    public function test_cross_company_accounts_are_not_found(): void
    {
        [$user, $company] = $this->financeUser(['accounts.view', 'accounts.manage']);
        $foreign = $this->account($this->seededCompany(), '6100');

        $this->actAs($user, $company)->getJson("/api/v1/accounts/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/accounts/{$foreign->id}", ['name' => 'Hijacked'])->assertNotFound();
        $this->deleteJson("/api/v1/accounts/{$foreign->id}")->assertNotFound();
        $this->getJson("/api/v1/accounts/{$foreign->id}/ledger")->assertNotFound();

        $this->assertDatabaseHas('accounts', ['id' => $foreign->id, 'name' => 'Rent']);
    }

    public function test_permissions_are_required(): void
    {
        [$viewer, $company] = $this->financeUser(['accounts.view']);

        $this->actAs($viewer, $company)
            ->postJson('/api/v1/accounts', ['code' => '1999', 'name' => 'X', 'type' => 'asset'])
            ->assertForbidden();
        $this->assertDatabaseMissing('accounts', ['code' => '1999']);

        [$nobody] = $this->financeUser(['journals.create'], $company);
        $this->actAs($nobody, $company)->getJson('/api/v1/accounts')->assertForbidden();
    }

    public function test_seeding_action_does_not_touch_other_companies(): void
    {
        $a = $this->seededCompany();
        $b = Company::factory()->create();

        app(SeedCompanyFinanceDefaultsAction::class)->handle($b);

        $this->assertSame(
            Account::forCompany($a->id)->count(),
            Account::forCompany($b->id)->count(),
        );
    }
}
