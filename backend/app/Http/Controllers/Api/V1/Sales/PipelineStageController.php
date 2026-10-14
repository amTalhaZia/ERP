<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Sales\StorePipelineStageRequest;
use App\Http\Resources\Sales\PipelineStageResource;
use App\Models\Sales\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PipelineStageController extends ApiController
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PipelineStage::class);

        return PipelineStageResource::collection(
            PipelineStage::query()->withCount('opportunities')->orderBy('position')->orderBy('id')->get(),
        );
    }

    public function store(StorePipelineStageRequest $request): JsonResponse
    {
        Gate::authorize('create', PipelineStage::class);

        $data = $request->validated();
        $data['position'] ??= ((int) PipelineStage::query()->max('position')) + 1;

        $stage = PipelineStage::query()->create($data);

        return (new PipelineStageResource($stage))->response()->setStatusCode(201);
    }

    public function show(PipelineStage $pipelineStage): PipelineStageResource
    {
        Gate::authorize('view', $pipelineStage);

        return new PipelineStageResource($pipelineStage->loadCount('opportunities'));
    }

    public function update(StorePipelineStageRequest $request, PipelineStage $pipelineStage): PipelineStageResource
    {
        Gate::authorize('update', $pipelineStage);

        $pipelineStage->fill($request->validated())->save();

        return new PipelineStageResource($pipelineStage->refresh());
    }

    public function destroy(PipelineStage $pipelineStage): JsonResponse
    {
        Gate::authorize('delete', $pipelineStage);

        if ($pipelineStage->opportunities()->exists()) {
            throw new ConflictHttpException('This stage still has opportunities; move them first.');
        }

        $pipelineStage->delete();

        return $this->noContent();
    }

    /** Persist a new order: `{ ids: [stageId, ...] }` → positions 1..n. */
    public function reorder(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('create', PipelineStage::class);

        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ])['ids'];

        $known = PipelineStage::query()->whereKey($ids)->pluck('id')->all();

        if (count($known) !== count($ids)) {
            throw new ConflictHttpException('One or more stages do not exist.');
        }

        DB::transaction(function () use ($ids): void {
            foreach (array_values($ids) as $index => $id) {
                PipelineStage::query()->whereKey($id)->update(['position' => $index + 1]);
            }
        });

        return PipelineStageResource::collection(PipelineStage::query()->orderBy('position')->orderBy('id')->get());
    }
}
