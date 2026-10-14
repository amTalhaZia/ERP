<?php

namespace App\Notifications\Projects;

use App\Models\Projects\Task;
use App\Notifications\ErpNotification;

/**
 * Sent to a task's new assignee. In-app by default; e-mail only when the
 * recipient opts in via preferences.notifications['task.assigned'].
 */
class TaskAssigned extends ErpNotification
{
    protected bool $emailByDefault = false;

    public function __construct(
        private readonly Task $task,
        private readonly string $projectName,
        private readonly ?string $assignedBy = null,
    ) {
        parent::__construct();
        $this->companyId = $task->company_id;
    }

    public function type(): string
    {
        return 'task.assigned';
    }

    public function title(): string
    {
        return 'New task assigned: '.$this->task->title;
    }

    public function body(): string
    {
        return sprintf(
            '%s assigned you "%s" in %s%s.',
            $this->assignedBy ?? 'Someone',
            $this->task->title,
            $this->projectName,
            $this->task->due_date ? ' (due '.$this->task->due_date->toDateString().')' : '',
        );
    }

    public function url(): string
    {
        return "/projects/{$this->task->project_id}?task={$this->task->id}";
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return [
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
        ];
    }

    public function taskId(): int
    {
        return $this->task->id;
    }
}
