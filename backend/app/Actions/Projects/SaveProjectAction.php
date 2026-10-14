<?php

namespace App\Actions\Projects;

use App\Enums\Projects\ProjectMemberRole;
use App\Enums\Projects\ProjectStatus;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectMember;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use Illuminate\Support\Facades\DB;

/**
 * Creates (numbered PRJ-YYYY-000001) and updates projects. The manager is
 * always kept as a project member with the `manager` role, and
 * `completed_at` follows the Completed status.
 */
class SaveProjectAction
{
    public function __construct(private readonly DocumentNumberer $numberer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Project
    {
        return DB::transaction(function () use ($data, $actor): Project {
            $data = $this->normalize($data);
            $data['number'] = $this->numberer->next('project');
            $data['created_by'] = $actor->id;
            $data['manager_id'] ??= $actor->id;
            $data['status'] ??= ProjectStatus::Planning->value;

            if (($data['status'] instanceof ProjectStatus ? $data['status'] : ProjectStatus::from($data['status'])) === ProjectStatus::Completed) {
                $data['completed_at'] = now();
            }

            $project = Project::query()->create($data);
            $this->ensureManagerMember($project);

            return $project->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data): Project {
            $data = $this->normalize($data);
            $project->fill($data);

            if ($project->isDirty('status')) {
                $project->completed_at = $project->status === ProjectStatus::Completed ? now() : null;
            }

            $project->save();

            if ($project->wasChanged('manager_id')) {
                $this->ensureManagerMember($project);
            }

            return $project->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        if (array_key_exists('budget', $data) && $data['budget'] !== null) {
            $data['budget'] = Money::normalize((string) $data['budget']);
        }

        return $data;
    }

    private function ensureManagerMember(Project $project): void
    {
        if ($project->manager_id === null) {
            return;
        }

        ProjectMember::query()->updateOrCreate(
            ['project_id' => $project->id, 'user_id' => $project->manager_id],
            ['role' => ProjectMemberRole::Manager->value],
        );
    }
}
