<?php

namespace Database\Seeders\Demo;

use App\Actions\Documents\AddDocumentVersionAction;
use App\Actions\Documents\UploadDocumentAction;
use App\Actions\Projects\SaveProjectAction;
use App\Actions\Projects\SaveTaskAction;
use App\Models\Company;
use App\Models\Department;
use App\Models\Documents\Document;
use App\Models\Documents\DocumentFolder;
use App\Models\Projects\Milestone;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectExpense;
use App\Models\Projects\ProjectMember;
use App\Models\Projects\Task;
use App\Models\Projects\TaskComment;
use App\Models\Projects\TimeEntry;
use App\Models\Sales\Customer;
use Carbon\CarbonPeriod;
use Database\Seeders\Demo\Concerns\SeedsDemoCompany;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * DEVELOPMENT ONLY. Three projects (active / planning / completed) with
 * members, milestones, ~30 tasks across the board (some overdue), comments,
 * a month of time entries and a project expense; plus a small document
 * library (Contracts / Policies / HR) uploaded through the document actions,
 * one document linked to a project.
 */
class ProjectsDemoSeeder extends Seeder
{
    use SeedsDemoCompany;

    private string $tmpDir;

    public function run(Company $company): void
    {
        $this->bootDemo($company, 'projects');

        [$active, $planning, $completed] = $this->projects();
        $this->info(Task::query()->count().' tasks, '.TimeEntry::query()->count().' time entries');

        $this->tmpDir = rtrim(sys_get_temp_dir(), '/\\').DIRECTORY_SEPARATOR.'erp-demo-'.Str::random(8);
        File::ensureDirectoryExists($this->tmpDir);

        try {
            $this->documents($active);
        } finally {
            File::deleteDirectory($this->tmpDir);
        }

        $this->info(Document::query()->count().' documents');
    }

    /** @return array{0: Project, 1: Project, 2: Project} */
    private function projects(): array
    {
        $save = app(SaveProjectAction::class);
        $today = $this->today();
        $start = $this->historyStart();
        $customer = Customer::query()->orderBy('id')->first();

        $active = $this->at($start->copy()->addDays(20), fn () => $this->as('manager', fn ($user) => $save->create([
            'name' => 'Warehouse Automation Rollout',
            'code' => 'WH-AUTO',
            'description' => 'Introduce handheld scanning and zone picking in the main warehouse (demo project).',
            'department_id' => Department::query()->where('code', 'OPS')->value('id'),
            'status' => 'active',
            'priority' => 'high',
            'start_date' => $start->copy()->addDays(20)->toDateString(),
            'due_date' => $today->copy()->addDays(60)->toDateString(),
            'budget' => $this->amount(85000),
            'currency' => $this->company->currency,
            'color' => '#2563eb',
            'is_billable' => false,
        ], $user)));

        $planning = $this->as('sales', fn ($user) => $save->create([
            'name' => 'Customer Portal Launch',
            'code' => 'PORTAL',
            'description' => 'Self-service order tracking portal for key accounts (demo project).',
            'customer_id' => $customer?->id,
            'department_id' => Department::query()->where('code', 'SAL')->value('id'),
            'status' => 'planning',
            'priority' => 'medium',
            'start_date' => $today->copy()->addDays(14)->toDateString(),
            'due_date' => $today->copy()->addDays(120)->toDateString(),
            'budget' => $this->amount(40000),
            'currency' => $this->company->currency,
            'color' => '#16a34a',
            'is_billable' => true,
        ], $user));

        $completedStart = $start->copy();
        $completedEnd = $start->copy()->addDays(60)->min($today->copy()->subDays(10));
        $completed = $this->at($completedEnd, fn () => $this->as('admin', fn ($user) => $save->create([
            'name' => 'Head Office Relocation',
            'code' => 'HO-MOVE',
            'description' => 'Move the head office to the new building (demo project).',
            'status' => 'completed',
            'priority' => 'medium',
            'start_date' => $completedStart->toDateString(),
            'due_date' => $completedEnd->toDateString(),
            'budget' => $this->amount(30000),
            'currency' => $this->company->currency,
            'color' => '#9333ea',
            'is_billable' => false,
        ], $user)));

        $this->members($active, ['employee' => 'member', 'inventory' => 'member', 'procurement' => 'member', 'admin' => 'viewer']);
        $this->members($planning, ['employee' => 'member', 'manager' => 'member']);
        $this->members($completed, ['inventory' => 'member', 'employee' => 'member', 'manager' => 'member']);

        $m1 = $this->milestones($active, [
            ['Requirements signed off', $start->copy()->addDays(45), true],
            ['Pilot zone live', $today->copy()->addDays(10), false],
            ['Full rollout', $today->copy()->addDays(55), false],
        ]);
        $m2 = $this->milestones($planning, [
            ['Scope approved', $today->copy()->addDays(20), false],
            ['Beta release', $today->copy()->addDays(90), false],
        ]);
        $m3 = $this->milestones($completed, [
            ['Move-out complete', $completedEnd->copy()->subDays(15), true],
            ['Move-in complete', $completedEnd->copy(), true],
        ]);

        // [title, status, priority, assignee, due (days from today), milestone index]
        $this->tasks($active, 'manager', $m1, [
            ['Map current picking workflow', 'done', 'medium', 'employee', -40, 0],
            ['Shortlist barcode scanner vendors', 'done', 'high', 'procurement', -35, 0],
            ['Approve automation budget', 'done', 'high', 'manager', -30, 0],
            ['Measure pick-rate baseline', 'done', 'medium', 'inventory', -12, 0],
            ['Network coverage survey', 'done', 'medium', 'employee', -20, 1],
            ['Install shelf labels in Zone A', 'in_progress', 'medium', 'inventory', -3, 1],
            ['Configure scanner handhelds', 'in_progress', 'high', 'employee', 4, 1],
            ['Integrate scanners with ERP stock module', 'in_review', 'critical', 'employee', 2, 1],
            ['Train pickers on new workflow', 'todo', 'medium', 'inventory', 9, 1],
            ['Pilot zone go-live checklist', 'todo', 'high', 'manager', 10, 1],
            ['Order conveyor extension', 'blocked', 'high', 'procurement', -5, 2],
            ['Draft SOP for returns handling', 'in_progress', 'medium', 'employee', -1, 2],
            ['Vendor SLA review', 'in_review', 'medium', 'manager', 6, 2],
            ['Update safety signage', 'todo', 'low', 'inventory', 20, 2],
            ['Zone B layout drawings', 'todo', 'medium', 'employee', 25, 2],
            ['Rollout communication plan', 'todo', 'low', 'manager', 40, 2],
        ]);

        $this->tasks($planning, 'sales', $m2, [
            ['Draft project charter', 'done', 'medium', 'sales', -6, 0],
            ['Collect portal requirements', 'in_progress', 'high', 'sales', 7, 0],
            ['Pricing display rules', 'blocked', 'medium', 'sales', -2, 0],
            ['Customer interviews (5 accounts)', 'todo', 'medium', 'manager', 12, 0],
            ['Wireframes for order tracking', 'todo', 'medium', 'employee', 30, 1],
            ['Security review checklist', 'todo', 'high', 'manager', 45, 1],
        ]);

        $daysAgo = (int) $completedEnd->diffInDays($today);
        $this->tasks($completed, 'admin', $m3, [
            ['Sign new lease', 'done', 'high', 'admin', -$daysAgo - 50, 0],
            ['Book movers', 'done', 'medium', 'manager', -$daysAgo - 40, 0],
            ['Pack archive boxes', 'done', 'low', 'employee', -$daysAgo - 30, 0],
            ['Move-out inspection', 'done', 'medium', 'manager', -$daysAgo - 15, 0],
            ['IT cabling at new office', 'done', 'high', 'employee', -$daysAgo - 20, 1],
            ['Furniture setup', 'done', 'medium', 'inventory', -$daysAgo - 8, 1],
            ['Update address on documents', 'done', 'low', 'admin', -$daysAgo - 3, 1],
            ['Farewell lunch at the old office', 'done', 'low', 'manager', -$daysAgo - 2, 1],
        ]);

        $this->comments();
        $this->timeEntries($active, $planning);

        ProjectExpense::query()->create([
            'project_id' => $active->id,
            'date' => $this->weekday($today->copy()->subDays(15))->toDateString(),
            'category' => 'Equipment',
            'description' => 'Two demo barcode scanners for the pilot zone',
            'amount' => $this->amount(1240),
            'currency' => $this->company->currency,
            'created_by' => $this->demoUser('manager')->id,
        ]);

        return [$active, $planning, $completed];
    }

    /** @param  array<string, string>  $members */
    private function members(Project $project, array $members): void
    {
        foreach ($members as $local => $role) {
            ProjectMember::query()->firstOrCreate(
                ['project_id' => $project->id, 'user_id' => $this->demoUser($local)->id],
                ['role' => $role],
            );
        }
    }

    /**
     * @param  list<array{0: string, 1: Carbon, 2: bool}>  $rows
     * @return list<Milestone>
     */
    private function milestones(Project $project, array $rows): array
    {
        return array_map(fn (array $row, int $i) => Milestone::query()->create([
            'project_id' => $project->id,
            'name' => $row[0],
            'due_date' => $row[1]->toDateString(),
            'status' => $row[2] ? 'completed' : 'pending',
            'completed_at' => $row[2] ? $row[1]->copy()->setTime(16, 0) : null,
            'position' => $i,
        ]), $rows, array_keys($rows));
    }

    /**
     * @param  list<Milestone>  $milestones
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: int, 5: int}>  $rows
     */
    private function tasks(Project $project, string $actor, array $milestones, array $rows): void
    {
        $save = app(SaveTaskAction::class);

        foreach ($rows as [$title, $status, $priority, $assignee, $dueIn, $milestone]) {
            $due = $this->today()->addDays($dueIn);
            $createdAt = $status === 'done' ? $due->copy()->subDays(1)->setTime(11, 0) : $this->realNow()->subDays(fake()->numberBetween(5, 25));

            $this->at($createdAt, fn () => $this->as($actor, fn ($user) => $save->create($project, [
                'title' => $title,
                'description' => 'Demo task — fictional.',
                'status' => $status,
                'priority' => $priority,
                'assignee_id' => $this->demoUser($assignee)->id,
                'milestone_id' => $milestones[$milestone]->id ?? null,
                'start_date' => $due->copy()->subDays(7)->toDateString(),
                'due_date' => $due->toDateString(),
                'estimate_hours' => fake()->randomElement([2, 4, 6, 8, 12, 16]),
            ], $user)));
        }
    }

    private function comments(): void
    {
        $bodies = [
            'Started on this today — will share an update by end of week.',
            'Blocked on vendor confirmation; following up.',
            'Looks good to me. Please attach the final checklist.',
            'Moved the due date after the team sync.',
            'Done — notes are in the shared folder.',
        ];

        Task::query()->orderBy('id')->get()->each(function (Task $task, int $i) use ($bodies): void {
            if ($i % 3 !== 0) {
                return;
            }

            foreach (range(0, $i % 2) as $n) {
                $user = $n === 0 && $task->assignee_id ? $task->assignee_id : $task->reporter_id;
                $comment = TaskComment::query()->create([
                    'task_id' => $task->id,
                    'user_id' => $user,
                    'body' => $bodies[($i + $n) % count($bodies)],
                ]);
                $comment->forceFill(['created_at' => $task->created_at->copy()->addDays($n + 1)->min($this->realNow())])->saveQuietly();
            }
        });
    }

    private function timeEntries(Project $active, Project $planning): void
    {
        $rate = $this->amount(45);
        $activeTasks = Task::query()->where('project_id', $active->id)->whereIn('status', ['in_progress', 'in_review', 'done'])->get();
        $people = ['employee', 'inventory', 'manager'];

        foreach (CarbonPeriod::create($this->today()->subDays(30), $this->today()->subDay()) as $day) {
            if ($day->isWeekend()) {
                continue;
            }

            foreach ($people as $p => $local) {
                if (fake()->boolean(45)) {
                    continue;
                }

                $user = $this->demoUser($local);
                $mine = $activeTasks->where('assignee_id', $user->id)->values();
                $task = $mine->isNotEmpty() ? $mine[fake()->numberBetween(0, $mine->count() - 1)] : null;

                TimeEntry::query()->create([
                    'project_id' => $active->id,
                    'task_id' => $task?->id,
                    'user_id' => $user->id,
                    'date' => $day->toDateString(),
                    'hours' => fake()->randomElement(['1.50', '2.00', '3.00', '4.00', '5.50', '6.00']),
                    'description' => $task ? "Work on: {$task->title}" : 'Project coordination',
                    'is_billable' => false,
                    'hourly_rate' => $rate,
                ]);
            }
        }

        foreach ([3, 6, 9] as $daysAgo) {
            TimeEntry::query()->create([
                'project_id' => $planning->id,
                'user_id' => $this->demoUser('sales')->id,
                'date' => $this->weekday($this->today()->subDays($daysAgo))->min($this->today()->subDay())->toDateString(),
                'hours' => '2.00',
                'description' => 'Requirement workshops with key accounts',
                'is_billable' => true,
                'hourly_rate' => $this->amount(85),
            ]);
        }
    }

    private function documents(Project $project): void
    {
        $admin = $this->demoUser('admin');
        $folder = fn (string $name, ?int $parent = null, string $owner = 'admin') => DocumentFolder::query()->create([
            'name' => $name, 'parent_id' => $parent, 'is_private' => false, 'owner_id' => $this->demoUser($owner)->id,
        ]);

        $contracts = $folder('Contracts');
        $customerContracts = $folder('Customer Agreements', $contracts->id);
        $policies = $folder('Policies');
        $hr = $folder('HR', null, 'hr');

        $upload = app(UploadDocumentAction::class);
        $addVersion = app(AddDocumentVersionAction::class);
        $company = $this->company->name;

        $msa = $this->as('admin', fn ($user) => $upload->handle([
            'title' => 'Master Services Agreement — template (Demo)',
            'description' => 'Standard customer agreement template. Fictional demo content.',
            'category' => 'contract',
            'tags' => ['template', 'customers'],
            'folder_id' => $customerContracts->id,
            'visibility' => 'company',
            'note' => 'Initial draft',
        ], $this->pdf('msa-template-v1.pdf', 'Master Services Agreement (Demo)', ["{$company}", 'Template version 1 — fictional demo document.']), $user));
        $this->as('admin', fn ($user) => $addVersion->handle($msa, $this->pdf('msa-template-v2.pdf', 'Master Services Agreement (Demo)', ["{$company}", 'Template version 2 — liability clause updated (demo).']), $user, 'Updated liability clause'));

        $handbook = $this->as('hr', fn ($user) => $upload->handle([
            'title' => 'Employee Handbook (Demo)',
            'description' => 'Company policies for all staff. Fictional demo content.',
            'category' => 'policy',
            'tags' => ['handbook', 'policy'],
            'folder_id' => $policies->id,
            'visibility' => 'company',
            'note' => 'First edition',
        ], $this->pdf('employee-handbook-v1.pdf', 'Employee Handbook (Demo)', ["{$company}", 'Edition 1 — fictional demo document.']), $user));
        $this->as('hr', fn ($user) => $addVersion->handle($handbook, $this->pdf('employee-handbook-v2.pdf', 'Employee Handbook (Demo)', ["{$company}", 'Edition 2 — remote work section added (demo).']), $user, 'Added remote-work section'));

        $this->as('hr', fn ($user) => $upload->handle([
            'title' => 'Leave Policy (Demo)',
            'description' => 'How annual, sick and unpaid leave work. Fictional demo content.',
            'category' => 'policy',
            'tags' => ['leave', 'hr'],
            'folder_id' => $hr->id,
            'visibility' => 'company',
        ], $this->text('leave-policy.txt', "Leave Policy (Demo)\n{$company}\n\nAnnual leave: 20 days per year, up to 5 days carried forward.\nSick leave: 10 days per year.\nThis is fictional demo content.\n"), $user));

        $this->as('manager', fn ($user) => $upload->handle([
            'title' => 'Project Charter — Warehouse Automation (Demo)',
            'description' => 'Goals, scope and milestones of the warehouse automation project.',
            'category' => 'report',
            'tags' => ['project', 'charter'],
            'documentable_type' => 'project',
            'documentable_id' => $project->id,
            'visibility' => 'company',
        ], $this->pdf('project-charter.pdf', 'Project Charter (Demo)', [$project->name, 'Scope: zone picking, handheld scanners, ERP integration.', 'Fictional demo document.']), $user));
    }

    private function text(string $name, string $content): UploadedFile
    {
        $path = $this->tmpDir.DIRECTORY_SEPARATOR.$name;
        File::put($path, $content);

        return new UploadedFile($path, $name, 'text/plain', null, true);
    }

    /** A tiny, valid single-page PDF with a few lines of text. @param list<string> $lines */
    private function pdf(string $name, string $title, array $lines): UploadedFile
    {
        $escape = fn (string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], Str::ascii($s));
        $stream = "BT /F1 18 Tf 72 760 Td ({$escape($title)}) Tj ET\n";
        foreach (array_values($lines) as $i => $line) {
            $y = 730 - $i * 20;
            $stream .= "BT /F1 11 Tf 72 {$y} Td ({$escape($line)}) Tj ET\n";
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        $path = $this->tmpDir.DIRECTORY_SEPARATOR.$name;
        File::put($path, $pdf);

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }
}
