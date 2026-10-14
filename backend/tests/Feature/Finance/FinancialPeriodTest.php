<?php

namespace Tests\Feature\Finance;

use App\Models\Company;
use App\Models\Finance\FinancialPeriod;
use Tests\Feature\Finance\Concerns\InteractsWithLedger;
use Tests\TestCase;

class FinancialPeriodTest extends TestCase
{
    use InteractsWithLedger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeFinanceClock();
    }

    private function period(Company $company, string $start): FinancialPeriod
    {
        return FinancialPeriod::forCompany($company->id)->whereDate('start_date', $start)->firstOrFail();
    }

    public function test_creating_a_financial_year_generates_twelve_monthly_periods(): void
    {
        [$user, $company] = $this->financeUser(['periods.manage']);

        $this->actAs($user, $company)
            ->postJson('/api/v1/financial-years', ['start_date' => '2027-07-01'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'FY 2027/28')
            ->assertJsonPath('data.end_date', '2028-06-30')
            ->assertJsonCount(12, 'data.periods')
            ->assertJsonPath('data.periods.0.name', 'Jul 2027')
            ->assertJsonPath('data.periods.7.name', 'Feb 2028')
            ->assertJsonPath('data.periods.7.end_date', '2028-02-29');

        $this->getJson('/api/v1/financial-years')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/financial-periods?per_page=100')->assertOk()->assertJsonCount(24, 'data');
    }

    public function test_overlapping_or_mid_month_years_are_rejected(): void
    {
        [$user, $company] = $this->financeUser(['periods.manage']);

        $this->actAs($user, $company)->postJson('/api/v1/financial-years', ['start_date' => '2026-06-01'])
            ->assertUnprocessable()->assertJsonValidationErrors(['start_date']);
        $this->postJson('/api/v1/financial-years', ['start_date' => '2027-01-15'])
            ->assertUnprocessable()->assertJsonValidationErrors(['start_date']);
    }

    public function test_periods_close_in_order_and_reopen_is_audited(): void
    {
        [$user, $company] = $this->financeUser(['periods.manage']);
        $jan = $this->period($company, '2026-01-01');
        $feb = $this->period($company, '2026-02-01');
        $this->actAs($user, $company);

        $this->postJson("/api/v1/financial-periods/{$feb->id}/close")
            ->assertStatus(409)->assertJsonPath('code', 'finance.earlier_period_open');

        $this->postJson("/api/v1/financial-periods/{$jan->id}/close")
            ->assertOk()->assertJsonPath('data.status', 'closed')->assertJsonPath('data.closed_by.id', $user->id);
        $this->postJson("/api/v1/financial-periods/{$feb->id}/close")->assertOk();

        $this->postJson("/api/v1/financial-periods/{$jan->id}/reopen")
            ->assertStatus(409)->assertJsonPath('code', 'finance.later_period_closed');

        $this->postJson("/api/v1/financial-periods/{$feb->id}/reopen", ['reason' => 'Late supplier bill'])
            ->assertOk()->assertJsonPath('data.status', 'open')->assertJsonPath('data.closed_at', null);

        $this->assertDatabaseHas('audit_logs', ['action' => 'periods.reopened', 'entity_id' => $feb->id, 'actor_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'periods.closed', 'entity_id' => $jan->id]);
    }

    public function test_reopened_period_accepts_postings_again(): void
    {
        [$user, $company] = $this->financeUser(['periods.manage', 'journals.view', 'journals.create', 'journals.post']);
        $jan = $this->period($company, '2026-01-01');
        $this->actAs($user, $company)->postJson("/api/v1/financial-periods/{$jan->id}/close")->assertOk();

        $id = $this->postJson('/api/v1/journal-entries', $this->draftPayload($company, '2026-01-20'))->json('data.id');
        $this->postJson("/api/v1/journal-entries/{$id}/post")->assertStatus(409)->assertJsonPath('code', 'finance.period_closed');

        $this->postJson("/api/v1/financial-periods/{$jan->id}/reopen")->assertOk();
        $this->postJson("/api/v1/journal-entries/{$id}/post")->assertOk()->assertJsonPath('data.status', 'posted');
    }

    public function test_period_management_requires_permission_and_tenant(): void
    {
        [$viewer, $company] = $this->financeUser(['journals.view']);
        $jan = $this->period($company, '2026-01-01');
        $foreign = $this->period($this->seededCompany(), '2026-01-01');

        $this->actAs($viewer, $company)->postJson("/api/v1/financial-periods/{$jan->id}/close")->assertForbidden();
        $this->postJson('/api/v1/financial-years', ['start_date' => '2027-01-01'])->assertForbidden();
        $this->getJson('/api/v1/financial-periods')->assertOk();
        $this->getJson("/api/v1/financial-periods/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseHas('financial_periods', ['id' => $jan->id, 'status' => 'open']);
    }
}
