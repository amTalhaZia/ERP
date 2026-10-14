<?php

namespace Tests\Feature\Documents;

use App\Models\Documents\Document;
use App\Models\Documents\DocumentVersion;
use App\Models\Projects\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Documents\Concerns\InteractsWithDocuments;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use InteractsWithDocuments;

    public function test_upload_stores_the_file_privately_with_checksum_and_metadata(): void
    {
        Storage::fake('local');
        [$user, $company] = $this->userWith($this->documentManagerPermissions);
        $project = Project::factory()->create(['company_id' => $company->id]);
        $contents = 'Master services agreement v1';

        $response = $this->actingAsIn($user, $company)->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('MSA.txt', $contents),
            'category' => 'contract',
            'tags' => ['Legal', 'msa'],
            'description' => 'Signed copy',
            'documentable_type' => 'project',
            'documentable_id' => $project->id,
            'retention_until' => '2033-01-01',
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'MSA')
            ->assertJsonPath('data.category', 'contract')
            ->assertJsonPath('data.tags', ['legal', 'msa'])
            ->assertJsonPath('data.visibility', 'company')
            ->assertJsonPath('data.owner_id', $user->id)
            ->assertJsonPath('data.documentable_type', 'project')
            ->assertJsonPath('data.current_version.version', 1)
            ->assertJsonPath('data.current_version.original_name', 'MSA.txt')
            ->assertJsonPath('data.current_version.checksum_sha256', hash('sha256', $contents))
            ->assertJsonMissingPath('data.current_version.file_path');

        $version = DocumentVersion::withoutTenancy()->where('document_id', $response->json('data.id'))->firstOrFail();
        $this->assertMatchesRegularExpression("#^documents/{$company->id}/[0-9a-f-]{36}\\.txt$#", $version->file_path);
        Storage::disk('local')->assertExists($version->file_path);
        $this->assertSame($contents, Storage::disk('local')->get($version->file_path));
        $this->assertSame(hash('sha256', $contents), $version->checksum_sha256);
        $this->assertDatabaseHas('audit_logs', ['action' => 'documents.created', 'entity_type' => 'document', 'entity_id' => $response->json('data.id')]);
    }

    public function test_returns_422_for_empty_payload(): void
    {
        [$user, $company] = $this->userWith($this->documentManagerPermissions);

        $this->actingAsIn($user, $company)->postJson('/api/v1/documents', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_rejects_disallowed_file_types(): void
    {
        Storage::fake('local');
        [$user, $company] = $this->userWith($this->documentManagerPermissions);
        $this->actingAsIn($user, $company);

        $files = [
            UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]); ?>'),
            UploadedFile::fake()->create('setup.exe', 20, 'application/x-msdownload'),
            // Allowed extension, but the content is PHP: content sniffing (finfo) must reject it.
            // A real UploadedFile is needed — fakes derive their MIME type from the name.
            $this->realUpload('innocent.txt', "<?php\nsystem(\$_GET['c']);\necho 'pwned';\n"),
        ];

        foreach ($files as $file) {
            $this->post('/api/v1/documents', ['file' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['file']);
        }

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    private function realUpload(string $clientName, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $contents);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return new UploadedFile($path, $clientName, null, null, true);
    }

    public function test_rejects_files_over_the_configured_size(): void
    {
        Storage::fake('local');
        config(['erp.uploads.max_kb' => 1]);
        [$user, $company] = $this->userWith($this->documentManagerPermissions);

        $this->actingAsIn($user, $company)
            ->post('/api/v1/documents', ['file' => UploadedFile::fake()->createWithContent('big.txt', str_repeat('a', 4096))], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_returns_403_and_stores_nothing_without_manage_permission(): void
    {
        Storage::fake('local');
        [$user, $company] = $this->userWith(['documents.view']);

        $this->actingAsIn($user, $company)
            ->post('/api/v1/documents', ['file' => UploadedFile::fake()->createWithContent('a.txt', 'x')], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_download_streams_the_file_with_safe_headers_and_is_audited(): void
    {
        Storage::fake('local');
        [$user, $company] = $this->userWith($this->documentManagerPermissions);

        $id = $this->actingAsIn($user, $company)->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('Q3 "report".txt', 'quarterly numbers'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $response = $this->get("/api/v1/documents/{$id}/download");

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertDownload('Q3 _report_.txt');
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame('quarterly numbers', $response->streamedContent());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'documents.downloaded',
            'entity_type' => 'document',
            'entity_id' => $id,
            'actor_id' => $user->id,
        ]);
    }

    public function test_new_versions_increment_and_old_versions_stay_downloadable(): void
    {
        Storage::fake('local');
        [$user, $company] = $this->userWith($this->documentManagerPermissions);

        $id = $this->actingAsIn($user, $company)->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('policy.txt', 'v1 text'),
        ], ['Accept' => 'application/json'])->json('data.id');

        $this->post("/api/v1/documents/{$id}/versions", [], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['file']);

        $this->post("/api/v1/documents/{$id}/versions", [
            'file' => UploadedFile::fake()->createWithContent('policy.txt', 'v2 text'),
            'note' => 'Updated clause 4',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.note', 'Updated clause 4')
            ->assertJsonPath('data.checksum_sha256', hash('sha256', 'v2 text'));

        $this->post("/api/v1/documents/{$id}/versions", [
            'file' => UploadedFile::fake()->createWithContent('policy.txt', 'v3 text'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.version', 3);

        $this->getJson("/api/v1/documents/{$id}")
            ->assertOk()
            ->assertJsonPath('data.current_version.version', 3)
            ->assertJsonPath('data.versions_count', 3)
            ->assertJsonPath('data.versions.0.version', 3);

        $this->assertSame('v3 text', $this->get("/api/v1/documents/{$id}/download")->streamedContent());
        $this->assertSame('v1 text', $this->get("/api/v1/documents/{$id}/download?version=1")->streamedContent());
        $this->get("/api/v1/documents/{$id}/download?version=9")->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'documents.version_added', 'entity_id' => $id]);
        $this->assertCount(3, Storage::disk('local')->allFiles("documents/{$company->id}"));
    }

    public function test_delete_is_a_soft_delete_and_hides_the_document(): void
    {
        [$user, $company] = $this->userWith($this->documentManagerPermissions);
        $document = Document::factory()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->deleteJson("/api/v1/documents/{$document->id}")->assertNoContent();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'documents.archived', 'entity_id' => $document->id]);
        $this->getJson("/api/v1/documents/{$document->id}")->assertNotFound();
    }
}
