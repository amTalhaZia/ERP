<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Enums\Projects\ProjectMemberRole;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Projects\StoreProjectMemberRequest;
use App\Http\Resources\Projects\ProjectMemberResource;
use App\Http\Resources\Projects\UserSummary;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectMemberController extends ApiController
{
    public function index(Project $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        return ProjectMemberResource::collection(
            $project->members()->with('user:'.UserSummary::COLUMNS)->orderBy('id')->get(),
        );
    }

    public function store(StoreProjectMemberRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('manageMembers', $project);

        $member = $project->members()->create([
            'user_id' => $request->integer('user_id'),
            'role' => $request->input('role', ProjectMemberRole::Member->value),
        ]);

        return (new ProjectMemberResource($member->load('user:'.UserSummary::COLUMNS)))->response()->setStatusCode(201);
    }

    public function update(StoreProjectMemberRequest $request, Project $project, ProjectMember $member): ProjectMemberResource
    {
        Gate::authorize('manageMembers', $project);

        if ($member->user_id === $project->manager_id && $request->input('role') !== ProjectMemberRole::Manager->value) {
            abort(409, 'Change the project manager before changing their role.');
        }

        $member->update(['role' => $request->input('role')]);

        return new ProjectMemberResource($member->load('user:'.UserSummary::COLUMNS));
    }

    public function destroy(Project $project, ProjectMember $member): JsonResponse
    {
        Gate::authorize('manageMembers', $project);

        if ($member->user_id === $project->manager_id) {
            abort(409, 'The project manager cannot be removed from the project.');
        }

        $member->delete();

        return $this->noContent();
    }
}
