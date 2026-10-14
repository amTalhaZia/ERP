<?php

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\HR\StoreEmployeeDocumentRequest;
use App\Http\Resources\HR\EmployeeDocumentResource;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeDocument;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files live on the private `local` disk and are only ever served through the
 * authorized download endpoint. Confidential downloads are audited.
 */
class EmployeeDocumentController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Employee $employee): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [EmployeeDocument::class, $employee]);

        $query = $employee->documents()->with('uploader:id,name')->orderByDesc('created_at');

        if (! Gate::allows('viewAll', EmployeeDocument::class)) {
            $query->where('is_confidential', false);
        }

        return EmployeeDocumentResource::collection($query->get());
    }

    public function store(StoreEmployeeDocumentRequest $request, Employee $employee): JsonResponse
    {
        Gate::authorize('create', [EmployeeDocument::class, $employee]);

        $file = $request->file('file');
        $path = $file->store("companies/{$employee->company_id}/employees/{$employee->id}/documents", EmployeeDocument::DISK);

        $document = $employee->documents()->create([
            'title' => $request->validated('title'),
            'category' => $request->validated('category'),
            'file_path' => $path,
            'mime' => (string) $file->getClientMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'expires_at' => $request->validated('expires_at'),
            'uploaded_by' => $request->user()->id,
            'is_confidential' => (bool) $request->validated('is_confidential', false),
        ]);

        return (new EmployeeDocumentResource($document->load('uploader:id,name')))->response()->setStatusCode(201);
    }

    public function download(EmployeeDocument $employeeDocument): StreamedResponse
    {
        Gate::authorize('download', $employeeDocument);

        if (! Storage::disk(EmployeeDocument::DISK)->exists($employeeDocument->file_path)) {
            abort(404);
        }

        if ($employeeDocument->is_confidential) {
            $this->audit->log('employee_documents.downloaded', $employeeDocument, context: [
                'employee_id' => $employeeDocument->employee_id,
                'category' => $employeeDocument->category?->value,
            ]);
        }

        $extension = pathinfo($employeeDocument->file_path, PATHINFO_EXTENSION);
        $filename = Str::slug($employeeDocument->title).($extension ? ".{$extension}" : '');

        return Storage::disk(EmployeeDocument::DISK)->download($employeeDocument->file_path, $filename, [
            'Content-Type' => $employeeDocument->mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(EmployeeDocument $employeeDocument): JsonResponse
    {
        Gate::authorize('delete', $employeeDocument);

        Storage::disk(EmployeeDocument::DISK)->delete($employeeDocument->file_path);
        $employeeDocument->delete();

        return $this->noContent();
    }
}
