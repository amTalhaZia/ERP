<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Enums\Projects\MilestoneStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Projects\StoreMilestoneRequest;
use App\Http\Resources\Projects\MilestoneResource;
use App\Models\Projects\Milestone;
use App\Models\Projects\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MilestoneController extends ApiController
{
    public function index(Project $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        return MilestoneResource::collection($project->milestones()->withCount('tasks')->get());
    }

    public function store(StoreMilestoneRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $data = $request->validated();
        $data['position'] ??= ((int) $project->milestones()->max('position')) + 1;
        $data['status'] ??= MilestoneStatus::Pending->value;

        if ($data['status'] === MilestoneStatus::Completed->value) {
            $data['completed_at'] = now();
        }

        $milestone = $project->milestones()->create($data);

        return (new MilestoneResource($milestone->refresh()))->response()->setStatusCode(201);
    }

    public function show(Milestone $milestone): MilestoneResource
    {
        Gate::authorize('view', $milestone);

        return new MilestoneResource($milestone->loadCount('tasks'));
    }

    public function update(StoreMilestoneRequest $request, Milestone $milestone): MilestoneResource
    {
        Gate::authorize('update', $milestone);

        $milestone->fill($request->validated());

        if ($milestone->isDirty('status')) {
            $milestone->completed_at = $milestone->status === MilestoneStatus::Completed ? now() : null;
        }

        $milestone->save();

        return new MilestoneResource($milestone->refresh()->loadCount('tasks'));
    }

    /** Tasks keep existing; their milestone link is cleared (FK nullOnDelete). */
    public function destroy(Milestone $milestone): JsonResponse
    {
        Gate::authorize('delete', $milestone);

        $milestone->delete();

        return $this->noContent();
    }
}
