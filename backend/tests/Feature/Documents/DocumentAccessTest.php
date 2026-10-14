<?php

namespace Tests\Feature\Documents;

use App\Models\Company;
use App\Models\Documents\Document;
use App\Models\Documents\DocumentFolder;
use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Documents\Concerns\InteractsWithDocuments;
use Tests\TestCase;

class DocumentAccessTest extends TestCase
{
    use InteractsWithDocuments;

    public function test_returns_404_for_another_companys_document(): void
    {
        Storage::fake('local');
        [$user, $company] = $this->userWith($this->documentManagerPermissions);
        $foreign = Document::factory()->withVersion()->create(['title' => 'Foreign']);
        Storage::disk('local')->put('documents/test.txt', 'hello');

        $this->actingAsIn($user, $company)->getJson("/api/v1/documents/{$foreign->id}")->assertNotFound();
        $this->get("/api/v1/documents/{$foreign->id}/download", ['Accept' => 'application/json'])->assertNotFound();
        $this->getJson("/api/v1/documents/{$foreign->id}/versions")->assertNotFound();
        $this->patchJson("/api/v1/documents/{$foreign->id}", ['title' => 'Hacked'])->assertNotFound();
        $this->postJson("/api/v1/documents/{$foreign->id}/share-link")->assertNotFound();
        $this->deleteJson("/api/v1/documents/{$foreign->id}")->assertNotFound();

        $this->assertDatabaseHas('documents', ['id' => $foreign->id, 'title' => 'Foreign', 'deleted_at' => null]);
    }

    public function test_returns_422_when_referencing_another_companys_folder_or_record(): void
    {
        Storage::fake('local');
        [$user, $company] = $this->userWith($this->documentManagerPermissions);
        $other = Company::factory()->create();
        $foreignFolder = DocumentFolder::factory()->create(['company_id' => $other->id]);
        $foreignProject = Project::factory()->create(['company_id' => $other->id]);
        $colleague = User::factory()->inCompany($company)->create();
        $privateFolder = DocumentFolder::factory()->privateTo($colleague)->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('a.txt', 'x'),
            'folder_id' => $foreignFolder->id,
            'documentable_type' => 'project',
            'documentable_id' => $foreignProject->id,
            'visibility' => 'restricted',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder_id', 'documentable_id', 'allowed_role_ids']);

        $this->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('a.txt', 'x'),
            'folder_id' => $privateFolder->id,
            'documentable_type' => 'spaceship',
            'documentable_id' => 1,
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['folder_id', 'documentable_type']);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_private_documents_are_visible_to_their_owner_only(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/test.txt', 'hello');
        [$owner, $company] = $this->userWith($this->documentManagerPermissions);
        $other = User::factory()->inCompany($company, $this->documentManagerPermissions)->create();
        $private = Document::factory()->private()->ownedBy($owner)->withVersion()->create(['company_id' => $company->id, 'title' => 'Secret']);
        $public = Document::factory()->ownedBy($owner)->create(['company_id' => $company->id, 'title' => 'Handbook']);

        $this->actingAsIn($other, $company)->getJson("/api/v1/documents/{$private->id}")->assertForbidden();
        $this->get("/api/v1/documents/{$private->id}/download", ['Accept' => 'application/json'])->assertForbidden();
        $this->patchJson("/api/v1/documents/{$private->id}", ['title' => 'Mine now'])->assertForbidden();
        $this->deleteJson("/api/v1/documents/{$private->id}")->assertForbidden();
        $this->getJson('/api/v1/documents')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $public->id);

        $this->switchTo($owner, $company)->get("/api/v1/documents/{$private->id}/download")->assertOk();
        $this->getJson('/api/v1/documents')->assertOk()->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('documents', ['id' => $private->id, 'title' => 'Secret', 'deleted_at' => null]);
    }

    public function test_restricted_documents_require_one_of_the_allowed_roles(): void
    {
        [$owner, $company] = $this->userWith($this->documentManagerPermissions);
        $insider = User::factory()->inCompany($company, ['documents.view'])->create();
        $outsider = User::factory()->inCompany($company, ['documents.view'])->create();
        $restricted = Document::factory()->ownedBy($owner)
            ->restrictedTo([$this->roleIdOf($insider, $company)])
            ->create(['company_id' => $company->id]);

        $this->actingAsIn($insider, $company)->getJson("/api/v1/documents/{$restricted->id}")->assertOk();
        $this->getJson('/api/v1/documents')->assertJsonCount(1, 'data');

        $this->switchTo($outsider, $company)->getJson("/api/v1/documents/{$restricted->id}")->assertForbidden();
        $this->getJson('/api/v1/documents')->assertJsonCount(0, 'data');
    }

    public function test_employee_documents_additionally_require_employee_document_permission(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/test.txt', 'hello');
        [$plain, $company] = $this->userWith(['documents.view']);
        $hr = User::factory()->inCompany($company, ['documents.view', 'employees.documents.view'])->create();
        $employeeDoc = Document::factory()->forEmployee(42)->withVersion()->create(['company_id' => $company->id]);

        $this->actingAsIn($plain, $company)->get("/api/v1/documents/{$employeeDoc->id}/download", ['Accept' => 'application/json'])->assertForbidden();
        $this->getJson("/api/v1/documents/{$employeeDoc->id}")->assertForbidden();
        $this->getJson('/api/v1/documents?documentable_type=employee')->assertOk()->assertJsonCount(0, 'data');

        $this->switchTo($hr, $company)->get("/api/v1/documents/{$employeeDoc->id}/download")->assertOk();
        $this->getJson('/api/v1/documents?documentable_type=employee&documentable_id=42')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_listing_requires_documents_view(): void
    {
        [$user, $company] = $this->userWith(['projects.view']);

        $this->actingAsIn($user, $company)->getJson('/api/v1/documents')->assertForbidden();
    }

    public function test_search_matches_title_description_and_tags_and_filters_archived(): void
    {
        [$user, $company] = $this->userWith($this->documentManagerPermissions);
        $byTitle = Document::factory()->create(['company_id' => $company->id, 'title' => 'Lease agreement', 'tags' => ['office']]);
        $byTag = Document::factory()->create(['company_id' => $company->id, 'title' => 'Scan 001', 'tags' => ['lease', 'scan']]);
        Document::factory()->create(['company_id' => $company->id, 'title' => 'Unrelated', 'tags' => ['misc']]);
        $archived = Document::factory()->create(['company_id' => $company->id, 'title' => 'Old lease', 'is_archived' => true]);

        $ids = collect($this->actingAsIn($user, $company)->getJson('/api/v1/documents?search=Lease')->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$byTitle->id, $byTag->id], $ids);

        $this->getJson('/api/v1/documents?search=lease&archived=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $archived->id);
        $this->getJson('/api/v1/documents?search=lease&archived=all')->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/documents?filter[category]=contract')->assertJsonCount(0, 'data');
    }

    public function test_share_link_is_short_lived_signed_and_still_requires_authorization(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/test.txt', 'hello');
        [$user, $company] = $this->userWith(['documents.view']);
        $document = Document::factory()->withVersion()->create(['company_id' => $company->id]);

        $this->actingAsIn($user, $company)->postJson("/api/v1/documents/{$document->id}/share-link", ['minutes' => 61])
            ->assertUnprocessable()->assertJsonValidationErrors(['minutes']);

        $url = $this->postJson("/api/v1/documents/{$document->id}/share-link", ['minutes' => 5])
            ->assertOk()
            ->assertJsonPath('data.requires_authentication', true)
            ->json('data.url');

        $this->assertStringContainsString('signature=', $url);
        $this->assertSame('hello', $this->get($url)->assertOk()->streamedContent());
        $this->get($url.'x', ['Accept' => 'application/json'])->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'documents.share_link_created', 'entity_id' => $document->id]);

        $this->travel(6)->minutes();
        $this->get($url, ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_folders_crud_respects_privacy_and_conflicts(): void
    {
        [$user, $company] = $this->userWith($this->documentManagerPermissions);
        $colleague = User::factory()->inCompany($company)->create();
        $theirPrivate = DocumentFolder::factory()->privateTo($colleague)->create(['company_id' => $company->id]);
        $foreign = DocumentFolder::factory()->create();

        $this->actingAsIn($user, $company)->postJson('/api/v1/document-folders', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);

        $parentId = $this->postJson('/api/v1/document-folders', ['name' => 'Contracts'])->assertCreated()->json('data.id');
        $childId = $this->postJson('/api/v1/document-folders', ['name' => '2026', 'parent_id' => $parentId])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/document-folders/{$parentId}", ['parent_id' => $childId])
            ->assertUnprocessable()->assertJsonValidationErrors(['parent_id']);
        $this->deleteJson("/api/v1/document-folders/{$parentId}")->assertStatus(409);

        $this->getJson("/api/v1/document-folders/{$theirPrivate->id}")->assertForbidden();
        $this->getJson("/api/v1/document-folders/{$foreign->id}")->assertNotFound();
        $this->getJson('/api/v1/document-folders')->assertOk()->assertJsonCount(2, 'data');

        $this->deleteJson("/api/v1/document-folders/{$childId}")->assertNoContent();
    }
}
