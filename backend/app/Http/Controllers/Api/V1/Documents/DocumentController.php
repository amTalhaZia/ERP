<?php

namespace App\Http\Controllers\Api\V1\Documents;

use App\Actions\Documents\AddDocumentVersionAction;
use App\Actions\Documents\UploadDocumentAction;
use App\Enums\Documents\DocumentableType;
use App\Enums\Documents\DocumentVisibility;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Documents\ShareLinkRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\StoreDocumentVersionRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Http\Resources\Documents\DocumentResource;
use App\Http\Resources\Documents\DocumentVersionResource;
use App\Models\Documents\Document;
use App\Models\Documents\DocumentVersion;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends ApiController
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Search & filter: search (title, description, tags), filter[category|visibility|owner_id],
     * folder_id, documentable_type + documentable_id, archived=0|1|all (default 0), from/to (created).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Document::class);

        $request->validate([
            'folder_id' => ['nullable', 'integer'],
            'documentable_type' => ['nullable', Rule::enum(DocumentableType::class)],
            'documentable_id' => ['nullable', 'integer'],
            'archived' => ['nullable', Rule::in(['0', '1', 'all', 'true', 'false'])],
        ]);

        $query = Document::query()
            ->visibleTo($request->user())
            ->with(['folder:id,name', 'owner:id,name', 'currentVersion']);

        $term = trim((string) $request->string('search'));
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $query->where(fn (Builder $q) => $q->where('documents.title', 'like', $like)
                ->orWhere('documents.description', 'like', $like)
                ->orWhereRaw("JSON_SEARCH(documents.tags, 'one', ?) IS NOT NULL", ['%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($term)).'%']));
        }

        $this->applyFilters($query, $request, ['category', 'visibility', 'owner_id']);

        if ($request->filled('folder_id')) {
            $query->where('documents.folder_id', $request->integer('folder_id'));
        }
        if ($request->filled('documentable_type')) {
            $query->where('documents.documentable_type', $request->input('documentable_type'));
        }
        if ($request->filled('documentable_id')) {
            $query->where('documents.documentable_id', $request->integer('documentable_id'));
        }

        match ((string) $request->input('archived', '0')) {
            'all' => null,
            '1', 'true' => $query->where('documents.is_archived', true),
            default => $query->where('documents.is_archived', false),
        };

        $this->applyDateRange($query, $request, 'created_at');
        $this->applySort($query, $request, ['title', 'category', 'created_at', 'updated_at', 'retention_until'], '-created_at');

        return $this->paginated($query->paginate($this->perPage($request)), DocumentResource::class);
    }

    public function store(StoreDocumentRequest $request, UploadDocumentAction $action): JsonResponse
    {
        Gate::authorize('create', Document::class);

        $document = $action->handle($request->validated(), $request->file('file'), $request->user());

        return (new DocumentResource($this->loadDetail($document)))->response()->setStatusCode(201);
    }

    public function show(Document $document): DocumentResource
    {
        Gate::authorize('view', $document);

        return new DocumentResource($this->loadDetail($document)->load('versions.uploader:id,name'));
    }

    public function update(UpdateDocumentRequest $request, Document $document): DocumentResource
    {
        Gate::authorize('update', $document);

        $data = $request->validated();

        if (array_key_exists('is_archived', $data)) {
            $data['archived_at'] = $data['is_archived'] ? ($document->archived_at ?? now()) : null;
        }

        $document->fill($data);

        if ($document->visibility !== DocumentVisibility::Restricted) {
            $document->allowed_role_ids = null;
        }

        $document->save();

        return new DocumentResource($this->loadDetail($document->refresh()));
    }

    /** Soft delete (audited by Auditable as documents.archived); files are kept for recovery. */
    public function destroy(Document $document): JsonResponse
    {
        Gate::authorize('delete', $document);

        $document->delete();

        return $this->noContent();
    }

    public function versions(Document $document): AnonymousResourceCollection
    {
        Gate::authorize('view', $document);

        return DocumentVersionResource::collection($document->versions()->with('uploader:id,name')->get());
    }

    public function storeVersion(StoreDocumentVersionRequest $request, Document $document, AddDocumentVersionAction $action): JsonResponse
    {
        Gate::authorize('addVersion', $document);

        $version = $action->handle($document, $request->file('file'), $request->user(), $request->input('note'));

        return (new DocumentVersionResource($version->load('uploader:id,name')))->response()->setStatusCode(201);
    }

    /** Stream a version (default: current) from the private disk. Audited. */
    public function download(Request $request, Document $document): StreamedResponse
    {
        Gate::authorize('download', $document);

        return $this->stream($request, $document, 'documents.downloaded');
    }

    /**
     * Same as download, reached through a temporary signed URL. The route is
     * still inside the auth + tenant group: the link is a short-lived
     * convenience for opening in a new tab, never a public/anonymous link.
     */
    public function sharedDownload(Request $request, Document $document): StreamedResponse
    {
        Gate::authorize('download', $document);

        return $this->stream($request, $document, 'documents.downloaded', ['via' => 'signed_link']);
    }

    public function shareLink(ShareLinkRequest $request, Document $document, TenantContext $tenant): JsonResponse
    {
        Gate::authorize('share', $document);

        $minutes = $request->integer('minutes', 15);
        $version = $request->filled('version') ? $this->resolveVersion($document, $request->integer('version')) : null;
        $expiresAt = now()->addMinutes($minutes);

        $url = URL::temporarySignedRoute('api.v1.documents.shared-download', $expiresAt, array_filter([
            'document' => $document->id,
            'company_id' => $tenant->id(),
            'version' => $version?->version,
        ]));

        $this->audit->log('documents.share_link_created', $document, context: ['minutes' => $minutes, 'version' => $version?->version]);

        return response()->json(['data' => [
            'url' => $url,
            'expires_at' => $expiresAt->toJSON(),
            'requires_authentication' => true,
        ]]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function stream(Request $request, Document $document, string $action, array $context = []): StreamedResponse
    {
        $version = $this->resolveVersion($document, $request->filled('version') ? $request->integer('version') : null);
        $disk = Storage::disk(DocumentVersion::DISK);

        if (! $disk->exists($version->file_path)) {
            abort(404);
        }

        $this->audit->log($action, $document, context: $context + [
            'version' => $version->version,
            'checksum_sha256' => $version->checksum_sha256,
        ]);

        return $disk->download($version->file_path, $this->safeFilename($version->original_name), [
            'Content-Type' => $version->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function resolveVersion(Document $document, ?int $number): DocumentVersion
    {
        $query = DocumentVersion::query()->where('document_id', $document->id);

        $version = $number !== null
            ? $query->where('version', $number)->first()
            : ($document->current_version_id ? $query->whereKey($document->current_version_id)->first() : $query->orderByDesc('version')->first());

        if ($version === null) {
            abort(404);
        }

        return $version;
    }

    /** Strip path separators, quotes and control characters from the download name. */
    private function safeFilename(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F"\\\\\/:*?<>|]+/u', '_', $name) ?? 'download';
        $clean = trim($clean, ' ._');

        return Str::limit($clean !== '' ? $clean : 'download', 200, '');
    }

    private function loadDetail(Document $document): Document
    {
        return $document->load(['folder:id,name', 'owner:id,name', 'currentVersion.uploader:id,name'])->loadCount('versions');
    }
}
