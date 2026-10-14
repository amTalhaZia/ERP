<?php

namespace App\Providers;

use App\Models\Documents\Document;
use App\Models\Documents\DocumentFolder;
use App\Models\Documents\DocumentVersion;
use App\Models\Projects\Milestone;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectExpense;
use App\Models\Projects\ProjectMember;
use App\Models\Projects\Task;
use App\Models\Projects\TaskComment;
use App\Models\Projects\TimeEntry;
use App\Policies\Documents\DocumentFolderPolicy;
use App\Policies\Documents\DocumentPolicy;
use App\Policies\Projects\MilestonePolicy;
use App\Policies\Projects\ProjectExpensePolicy;
use App\Policies\Projects\ProjectPolicy;
use App\Policies\Projects\TaskCommentPolicy;
use App\Policies\Projects\TaskPolicy;
use App\Policies\Projects\TimeEntryPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Projects & Tasks + Document Management wiring. Policies live under
 * App\Policies\Projects / App\Policies\Documents (outside auto-discovery), so
 * they are mapped here, along with short morph aliases for audit entity_type.
 */
class ProjectsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const POLICIES = [
        Project::class => ProjectPolicy::class,
        Milestone::class => MilestonePolicy::class,
        Task::class => TaskPolicy::class,
        TaskComment::class => TaskCommentPolicy::class,
        TimeEntry::class => TimeEntryPolicy::class,
        ProjectExpense::class => ProjectExpensePolicy::class,
        Document::class => DocumentPolicy::class,
        DocumentFolder::class => DocumentFolderPolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Relation::morphMap([
            'project' => Project::class,
            'project_member' => ProjectMember::class,
            'project_milestone' => Milestone::class,
            'task' => Task::class,
            'task_comment' => TaskComment::class,
            'time_entry' => TimeEntry::class,
            'project_expense' => ProjectExpense::class,
            'document' => Document::class,
            'document_version' => DocumentVersion::class,
            'document_folder' => DocumentFolder::class,
        ]);
    }
}
