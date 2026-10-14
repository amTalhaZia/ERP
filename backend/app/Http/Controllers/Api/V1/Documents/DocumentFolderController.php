<?php

namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Documents\StoreDocumentFolderRequest;
use App\Http\Resources\Documents\DocumentFolderResource;
use App\Models\Documents\DocumentFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DocumentFolderController extends ApiController
{
    /** All visible folders (flat; build the tree client-side from parent_id). filter[parent_id] narrows. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', DocumentFolder::class);

        $query = DocumentFolder::query()->visibleTo($request->user())->withCount(['documents', 'children']);
        $this->applySearch($query, $request, ['name']);
        $this->applyFilters($query, $request, ['parent_id', 'is_private']);
        $this->applySort($query, $request, ['name', 'created_at'], 'name');

        return $this->paginated($query->paginate($this->perPage($request)), DocumentFolderResource::class);
    }

    public function store(StoreDocumentFolderRequest $request): JsonResponse
    {
        Gate::authorize('create', DocumentFolder::class);

        $folder = DocumentFolder::query()->create($request->validated() + ['owner_id' => $request->user()->id]);

        return (new DocumentFolderResource($folder->refresh()))->response()->setStatusCode(201);
    }

    public function show(DocumentFolder $folder): DocumentFolderResource
    {
        Gate::authorize('view', $folder);

        return new DocumentFolderResource($folder->loadCount(['documents', 'children']));
    }

    public function update(StoreDocumentFolderRequest $request, DocumentFolder $folder): DocumentFolderResource
    {
        Gate::authorize('update', $folder);

        $folder->fill($request->validated())->save();

        return new DocumentFolderResource($folder->refresh()->loadCount(['documents', 'children']));
    }

    public function destroy(DocumentFolder $folder): JsonResponse
    {
        Gate::authorize('delete', $folder);

        if ($folder->children()->exists() || $folder->documents()->withTrashed()->exists()) {
            return $this->message('Move or delete the folder contents first.', ['code' => 'conflict'], 409);
        }

        $folder->delete();

        return $this->noContent();
    }
}
