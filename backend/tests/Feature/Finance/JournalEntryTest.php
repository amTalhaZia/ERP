<?php

namespace Tests\Feature\Finance;

use App\Enums\Finance\JournalEntryStatus;
use App\Enums\Finance\PeriodStatus;
use App\Exceptions\Finance\FinanceException;
use App\Models\Finance\FinancialPeriod;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalLine;
use App\Services\Finance\LedgerPoster;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Finance\Concerns\InteractsWithLedger;
use Tests\TestCase;

class JournalEntryTest extends TestCase
{
    use InteractsWithLedger;

    private const ALL = ['journals.view', 'journals.create', 'journals.post', 'accounts.view'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeFinanceClock();
    }

    public function test_creates_a_draft_with_sequential_numbers_and_computed_totals(): void
    {
        [$user, $company] = $this->financeUser(self::ALL);

        $first = $this->actAs($user, $company)->postJson('/api/v1/journal-entries', $this->draftPayload($company))
            ->assertCreated()
            ->assertJsonPath('data.number', 'JE-2026-000001')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.total_debit', '250.0000')
            ->assertJsonPath('data.total_credit', '250.0000')
            ->assertJsonPath('data.lines.0.account.code', '6300')
            ->assertJsonPath('data.source_type', 'manual');

        $this->postJson('/api/v1/journal-entries', $this->draftPayload($company))
            ->assertCreated()
            ->assertJsonPath('data.number', 'JE-2026-000002');

        $this->assertDatabaseHas('journal_entries', [
            'id' => $first->json('data.id'), 'company_id' => $company->id, 'created_by' => $user->id,
            'financial_period_id' => FinancialPeriod::covering($company->id, '2026-03-15')->id,
        ]);
        $this->assertSame(2, JournalLine::forCompany($company->id)->where('journal_entry_id', $first->json('data.id'))->count());
    }

    public function test_empty_payload_is_rejected_with_field_errors(): void
    {
        [$user, $company] = $this->financeUser(self::ALL);

        $this->actAs($user, $company)->postJson('/api/v1/journal-entries', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entry_date', 'lines']);
    }

    public function test_a_line_with_both_debit_and_credit_is_rejected(): void
    {
        [$user, $company] = $this->financeUser(self::ALL);
        $payload = $this->draftPayload($company);
        $payload['lines'][0]['credit'] = '10';

        $this->actAs($user, $company)->postJson('/api/v1/journal-entries', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.debit']);

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_lines_referencing_another_companys_account_are_rejected(): void
    {
        [$user, $company] = $this->financeUser(self::ALL);
        $other = $this->seededCompany();
        $payload = $this->draftPayload($company);
        $payload['lines'][1]['account_id'] = $this->account($other, '1010')->id;

        $this->actAs($user, $company)->postJson('/api/v1/journal-entries', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.1.account_id']);

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_ledger_poster_rejects_another_companys_account_even_without_a_request(): void
    {
        $company = $this->seededCompany();
        $other = $this->seededCompany();

        $this->expectException(ValidationException::class);

        try {
            app(LedgerPoster::class)->post([
                'company_id' => $company->id, 'entry_date' => '2026-03-01', 'source_type' => 'invoice', 'source_id' => 1,
                'lines' => [
                    ['account_code' => '1100', 'debit' => '10', 'credit' => '0'],
                    ['account_id' => $this->account($other, '4000')->id, 'debit' => '0', 'credit' => '10'],
                ],
            ]);
        } finally {
            $this->assertDatabaseCount('journal_entries', 0);
        }
    }

    public function test_posting_an_unbalanced_entry_returns_422_and_leaves_it_draft(): void
    {
        [$user, $company] = $this->financeUser(self::ALL);
        $id = $this->actAs($user, $company)
            ->postJson('/api/v1/journal-entries', $this->draftPayload($company, debit: '250.00', credit: '249.99'))
            ->assertCreated()->json('data.id');

        $this->postJson("/api/v1/journal-entries/{$id}/post")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'finance.unbalanced');

        $this->assertDatabaseHas('journal_entries', ['id' => $id, 'status' => 'draft', 'posted_at' => null]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'journals.posted']);
    }

    public function test_ledger_poster_persists_nothing_when_unbalanced(): void
    {
        $company = $this->seededCompany();

        try {
            app(LedgerPoster::class)->post([
                'company_id' => $company->id, 'entry_date' => '2026-03-01', 'source_type' => 'bill', 'source_id' => 7,
                'lines' => [
                    ['account_code' => '6100', 'debit' => '400', 'credit' => '0'],
                    ['account_code' => '2000', 'debit' => '0', 'credit' => '399'],
                ],
            ]);
            $this->fail('Expected unbalanced exception');
        } catch (FinanceException $e) {
            $this->assertSame('finance.unbalanced', $e->errorCode);
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
    }

    public function test_posting_is_exact_with_fractional_amounts(): void
    {
        $company = $this->seededCompany();

        $entry = app(LedgerPoster::class)->post([
            'company_id' => $company->id, 'entry_date' => '2026-03-01', 'source_type' => 'manual',
            'lines' => [
                ['account_code' => '6300', 'debit' => '0.1', 'credit' => '0'],
                ['account_code' => '6200', 'debit' => '0.2', 'credit' => '0'],
                ['account_code' => '1000', 'debit' => '0', 'credit' => '0.3'],
            ],
        ]);

        $this->assertSame(JournalEntryStatus::Posted, $entry->status);
        $this->assertSame('0.3000', $entry->total_debit);
        $this->assertSame('0.3000', $entry->total_credit);
    }

    public function test_posts_a_balanced_draft_and_writes_an_audit_row(): void
    {
        [$user, $company] = $this->financeUser(self::ALL);
        $id = $this->actAs($user, $company)->postJson('/api/v1/journal-entries', $this->draftPayload($company))->json('data.id');

        $this->postJson("/api/v1/journal-entries/{$id}/post")
            ->assertOk()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.posted_by', $user->id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'journals.posted', 'entity_id' => $id, 'actor_id' => $user->id, 'company_id' => $company->id]);
        $this->postJson("/api/v1/journal-entries/{$id}/post")->assertStatus(409);
    }

    public function test_posting_into_a_closed_period_returns_409(): void
    {
        [$user, $company] = $this->financeUser(self::ALL);
        FinancialPeriod::forCompany($company->id)->whereDate('start_date', '<=', '2026-03-01')
            ->update(['status' => PeriodStatus::Closed->value, 'closed_at' => now()]);

        $id = $this->actAs($user, $company)->postJson('/api/v1/journal-entries', $this->draftPayload($company, '2026-03-15'))->json('data.id');

        $this->postJson("/api/v1/journal-entries/{$id}/post")
            ->assertStatus(409)
            ->assertJsonPath('code', 'finance.period_closed');

        $this->assertDatabaseHas('journal_entries', ['id' => $id, 'status' => 'draft']);
    }

    public function test_posting_a_date_without_a_period_returns_409(): void
    {
        [$user, $company] = $this->financeUser(self::ALL);
        $id = $this->actAs($user, $company)->postJson('/api/v1/journal-entries', $this->draftPayload($company, '2030-01-10'))->json('data.id');

        $this->postJson("/api/v1/journal-entries/{$id}/post")
            ->assertStatus(409)
            ->assertJsonPath('code', 'finance.no_period');
    }

    public function test_updating_a_posted_entry_returns_409_and_changes_nothing(): void
    {
        [$user, $company] = $this->financeUser(self::ALL);
        $entry = $this->postSimple($company, '2026-03-10', '1010', '3000', '1000.00');

        $this->actAs($user, $company)
            ->putJson("/api/v1/journal-entries/{$entry->id}", $this->draftPayload($company) + ['memo' => 'Tampered'])
            ->assertStatus(409);

        $this->deleteJson("/api/v1/journal-entries/{$entry->id}")->assertStatus(409);

        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id, 'memo' => 'Test posting', 'status' => 'posted', 'total_debit' => '1000.0000']);
        $this->assertSame(['1000.0000', '0.0000'], JournalLine::forCompany($company->id)->where('journal_entry_id', $entry->id)->orderBy('line_no')->pluck('debit')->all());
    }

    public function test_drafts_can_be_updated_and_deleted(): void
    {
        [$user, $company] = $this->financeUser(self::ALL);
        $id = $this->actAs($user, $company)->postJson('/api/v1/journal-entries', $this->draftPayload($company))->json('data.id');

        $this->putJson("/api/v1/journal-entries/{$id}", $this->draftPayload($company, '2026-04-02', '99.50', '99.50'))
            ->assertOk()
            ->assertJsonPath('data.entry_date', '2026-04-02')
            ->assertJsonPath('data.total_debit', '99.5000');

        $this->deleteJson("/api/v1/journal-entries/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('journal_entries', ['id' => $id]);
        $this->assertDatabaseMissing('journal_lines', ['journal_entry_id' => $id]);
    }

    public function test_model_guard_blocks_direct_changes_to_posted_entries_and_lines(): void
    {
        $company = $this->seededCompany();
        $entry = $this->postSimple($company, '2026-03-10', '1010', '3000', '500');

        try {
            $entry->update(['memo' => 'Edited']);
            $this->fail('Posted entry header must be immutable');
        } catch (FinanceException $e) {
            $this->assertSame('finance.immutable', $e->errorCode);
        }

        try {
            $entry->lines()->first()->update(['debit' => '1']);
            $this->fail('Posted lines must be immutable');
        } catch (FinanceException) {
            // expected
        }

        $this->expectException(FinanceException::class);
        JournalEntry::forCompany($company->id)->findOrFail($entry->id)->delete();
    }

    public function test_database_check_rejects_a_line_with_both_sides(): void
    {
        $company = $this->seededCompany();
        $entry = $this->postSimple($company, '2026-03-10', '1010', '3000', '500');

        $this->expectException(QueryException::class);

        DB::table('journal_lines')->insert([
            'company_id' => $company->id, 'journal_entry_id' => $entry->id, 'line_no' => 9,
            'account_id' => $this->account($company, '1010')->id, 'debit' => 1, 'credit' => 1,
        ]);
    }

    public function test_reversal_posts_a_mirror_entry_and_marks_the_original_reversed(): void
    {
        [$user, $company] = $this->financeUser(self::ALL);
        $entry = $this->postSimple($company, '2026-03-10', '6100', '1010', '400.00');

        $response = $this->actAs($user, $company)
            ->postJson("/api/v1/journal-entries/{$entry->id}/reverse", ['entry_date' => '2026-03-20', 'memo' => 'Booked twice'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.reverses_entry_id', $entry->id)
            ->assertJsonPath('data.memo', 'Booked twice')
            ->assertJsonPath('data.lines.0.account.code', '6100')
            ->assertJsonPath('data.lines.0.debit', '0.0000')
            ->assertJsonPath('data.lines.0.credit', '400.0000')
            ->assertJsonPath('data.lines.1.account.code', '1010')
            ->assertJsonPath('data.lines.1.debit', '400.0000');

        $reversalId = $response->json('data.id');
        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id, 'status' => 'reversed', 'reversed_by_entry_id' => $reversalId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'journals.reversed', 'entity_id' => $entry->id]);

        $this->postJson("/api/v1/journal-entries/{$entry->id}/reverse")->assertStatus(409);
    }

    public function test_list_filters_by_status_account_and_search(): void
    {
        [$user, $company] = $this->financeUser(self::ALL);
        $posted = $this->postSimple($company, '2026-03-10', '6100', '1010', '400.00', 'March rent');
        $this->postSimple($company, '2026-03-11', '1010', '3000', '1000.00', 'Capital');
        $this->actAs($user, $company)->postJson('/api/v1/journal-entries', $this->draftPayload($company));
        $rent = $this->account($company, '6100');

        $this->getJson('/api/v1/journal-entries?filter[status]=posted')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/journal-entries?account_id={$rent->id}")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $posted->id);
        $this->getJson('/api/v1/journal-entries?search=rent')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/journal-entries?from=2026-03-11&to=2026-03-11')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cross_company_entries_are_not_found(): void
    {
        [$user, $company] = $this->financeUser(self::ALL);
        $other = $this->seededCompany();
        $foreign = $this->postSimple($other, '2026-03-10', '1010', '3000', '10');

        $this->actAs($user, $company)->getJson("/api/v1/journal-entries/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/journal-entries/{$foreign->id}/reverse")->assertNotFound();
        $this->deleteJson("/api/v1/journal-entries/{$foreign->id}")->assertNotFound();
        $this->getJson('/api/v1/journal-entries')->assertOk()->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('journal_entries', ['id' => $foreign->id, 'status' => 'posted']);
    }

    public function test_permissions_are_enforced(): void
    {
        [$viewer, $company] = $this->financeUser(['journals.view']);
        $entry = $this->postSimple($company, '2026-03-10', '6100', '1010', '400.00');

        $this->actAs($viewer, $company)->postJson('/api/v1/journal-entries', $this->draftPayload($company))->assertForbidden();
        $this->postJson("/api/v1/journal-entries/{$entry->id}/reverse")->assertForbidden();
        $this->assertDatabaseCount('journal_entries', 1);

        [$creator] = $this->financeUser(['journals.view', 'journals.create'], $company);
        $id = $this->actAs($creator, $company)->postJson('/api/v1/journal-entries', $this->draftPayload($company))->assertCreated()->json('data.id');
        $this->postJson("/api/v1/journal-entries/{$id}/post")->assertForbidden()->assertJsonPath('code', 'auth.forbidden');
        $this->assertDatabaseHas('journal_entries', ['id' => $id, 'status' => 'draft']);

        [$nobody] = $this->financeUser(['accounts.view'], $company);
        $this->actAs($nobody, $company)->getJson('/api/v1/journal-entries')->assertForbidden();
    }
}
