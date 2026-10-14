<?php

namespace Tests\Feature\Documents;

use App\Models\Company;
use App\Models\Documents\Document;
use Tests\Feature\Documents\Concerns\InteractsWithDocuments;
use Tests\TestCase;

class RetentionCommandTest extends TestCase
{
    use InteractsWithDocuments;

    public function test_archives_only_documents_past_their_retention_date_without_deleting(): void
    {
        $this->travelTo('2026-10-15 09:00:00');
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $expired = Document::factory()->create(['company_id' => $company->id, 'retention_until' => '2026-10-14']);
        $expiredElsewhere = Document::factory()->create(['company_id' => $otherCompany->id, 'retention_until' => '2025-01-01']);
        $endsToday = Document::factory()->create(['company_id' => $company->id, 'retention_until' => '2026-10-15']);
        $future = Document::factory()->create(['company_id' => $company->id, 'retention_until' => '2030-01-01']);
        $noRetention = Document::factory()->create(['company_id' => $company->id, 'retention_until' => null]);

        $this->artisan('documents:apply-retention', ['--dry-run' => true])
            ->expectsOutputToContain('2 document(s) past retention would be archived.')
            ->assertSuccessful();
        $this->assertDatabaseHas('documents', ['id' => $expired->id, 'is_archived' => false]);

        $this->artisan('documents:apply-retention')
            ->expectsOutputToContain('2 document(s) past retention archived.')
            ->assertSuccessful();

        foreach ([$expired, $expiredElsewhere] as $document) {
            $this->assertDatabaseHas('documents', ['id' => $document->id, 'is_archived' => true, 'deleted_at' => null]);
            $this->assertNotNull(Document::withoutTenancy()->find($document->id)->archived_at);
        }

        foreach ([$endsToday, $future, $noRetention] as $document) {
            $this->assertDatabaseHas('documents', ['id' => $document->id, 'is_archived' => false]);
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'documents.updated', 'entity_id' => $expired->id, 'company_id' => $company->id]);

        // Idempotent: a second run finds nothing new.
        $this->artisan('documents:apply-retention')
            ->expectsOutputToContain('0 document(s) past retention archived.')
            ->assertSuccessful();
    }
}
