<?php

namespace Tests\Feature\HR;

use App\Models\Company;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HR\Concerns\SwitchesUsers;
use Tests\TestCase;

class EmployeeDocumentTest extends TestCase
{
    use SwitchesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * @return array{0: User, 1: Company, 2: Employee}
     */
    private function owner(): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->inCompany($company, ['leave.request'])->create();
        $employee = Employee::factory()->for($company)->forUser($user)->create();

        return [$user, $company, $employee];
    }

    private function storedDocument(Employee $employee, bool $confidential): EmployeeDocument
    {
        $document = EmployeeDocument::factory()->create([
            'company_id' => $employee->company_id, 'employee_id' => $employee->id, 'is_confidential' => $confidential,
        ]);
        Storage::disk('local')->put($document->file_path, 'PDF-CONTENT');

        return $document;
    }

    public function test_uploads_a_document_to_the_private_disk(): void
    {
        [$hr, $company] = $this->userWith(['employees.view', 'employees.update', 'employees.documents.view']);
        $employee = Employee::factory()->for($company)->create();

        $response = $this->switchTo($hr, $company)->post("/api/v1/employees/{$employee->id}/documents", [
            'title' => 'Work visa',
            'category' => 'visa',
            'file' => UploadedFile::fake()->create('visa.pdf', 120, 'application/pdf'),
            'expires_at' => '2027-12-31',
            'is_confidential' => '1',
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.category', 'visa')->assertJsonPath('data.is_confidential', true);

        $document = EmployeeDocument::withoutTenancy()->findOrFail($response->json('data.id'));
        $this->assertSame($company->id, $document->company_id);
        $this->assertSame($hr->id, $document->uploaded_by);
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee.documents.created', 'entity_id' => $document->id]);
    }

    public function test_empty_upload_returns_422(): void
    {
        [$hr, $company] = $this->userWith(['employees.update']);
        $employee = Employee::factory()->for($company)->create();

        $this->switchTo($hr, $company)->postJson("/api/v1/employees/{$employee->id}/documents", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'category', 'file']);
    }

    public function test_upload_without_permission_is_403_and_nothing_is_stored(): void
    {
        [$viewer, $company] = $this->userWith(['employees.view']);
        $employee = Employee::factory()->for($company)->create();

        $this->switchTo($viewer, $company)->post("/api/v1/employees/{$employee->id}/documents", [
            'title' => 'X', 'category' => 'other', 'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertDatabaseCount('employee_documents', 0);
    }

    public function test_owner_cannot_download_a_confidential_document_without_permission(): void
    {
        [$user, $company, $employee] = $this->owner();
        $document = $this->storedDocument($employee, confidential: true);

        $this->switchTo($user, $company)->get("/api/v1/employee-documents/{$document->id}/download", ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_owner_can_download_their_non_confidential_document(): void
    {
        [$user, $company, $employee] = $this->owner();
        $document = $this->storedDocument($employee, confidential: false);

        $response = $this->switchTo($user, $company)->get("/api/v1/employee-documents/{$document->id}/download");

        $response->assertOk()->assertDownload();
        $this->assertSame('PDF-CONTENT', $response->streamedContent());
    }

    public function test_documents_view_holder_downloads_confidential_document_and_it_is_audited(): void
    {
        [, $company, $employee] = $this->owner();
        $hr = User::factory()->inCompany($company, ['employees.documents.view'])->create();
        $document = $this->storedDocument($employee, confidential: true);

        $response = $this->switchTo($hr, $company)->get("/api/v1/employee-documents/{$document->id}/download");

        $response->assertOk()->assertDownload();
        $this->assertSame('PDF-CONTENT', $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee_documents.downloaded', 'entity_id' => $document->id, 'actor_id' => $hr->id,
        ]);
    }

    public function test_other_employee_without_permission_cannot_download(): void
    {
        [, $company, $employee] = $this->owner();
        $colleague = User::factory()->inCompany($company)->create();
        $document = $this->storedDocument($employee, confidential: false);

        $this->switchTo($colleague, $company)->getJson("/api/v1/employee-documents/{$document->id}/download")->assertForbidden();
    }

    public function test_owner_list_hides_confidential_documents(): void
    {
        [$user, $company, $employee] = $this->owner();
        $this->storedDocument($employee, confidential: true);
        $public = $this->storedDocument($employee, confidential: false);

        $this->switchTo($user, $company)->getJson("/api/v1/employees/{$employee->id}/documents")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $public->id);
    }

    public function test_cross_tenant_document_download_and_delete_are_404(): void
    {
        [$hr, $company] = $this->userWith(['employees.update', 'employees.documents.view']);
        $foreign = $this->storedDocument(Employee::factory()->create(), confidential: false);

        $this->switchTo($hr, $company)->getJson("/api/v1/employee-documents/{$foreign->id}/download")->assertNotFound();
        $this->deleteJson("/api/v1/employee-documents/{$foreign->id}")->assertNotFound();
        $this->getJson("/api/v1/employees/{$foreign->employee_id}/documents")->assertNotFound();

        $this->assertDatabaseHas('employee_documents', ['id' => $foreign->id]);
        Storage::disk('local')->assertExists($foreign->file_path);
    }

    public function test_delete_removes_record_and_file(): void
    {
        [$hr, $company] = $this->userWith(['employees.update']);
        $document = $this->storedDocument(Employee::factory()->for($company)->create(), confidential: false);

        $this->switchTo($hr, $company)->deleteJson("/api/v1/employee-documents/{$document->id}")->assertNoContent();

        $this->assertDatabaseMissing('employee_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->file_path);
    }
}
