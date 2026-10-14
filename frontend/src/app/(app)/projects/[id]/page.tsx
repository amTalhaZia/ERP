'use client';

import { Suspense, use, useCallback, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { Clock, FileText, Flag, KanbanSquare, LayoutDashboard, ListChecks, Pencil, Plus, Receipt, Trash2, Users } from 'lucide-react';
import {
  Alert, Button, Card, ConfirmDialog, ErrorState, PageHeader, SkeletonCard, Tab, TabList, TabPanel, Tabs,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate } from '@/lib/format';
import { LinkedDocuments } from '@/features/documents/components';
import { projects, useMilestones, useProject, type Project, type TaskStatus } from '@/features/projects/api';
import { ExpensesPanel } from '@/features/projects/ExpensesPanel';
import { MembersPanel, MilestonesPanel, OverviewPanel } from '@/features/projects/ProjectPanels';
import { ProjectForm } from '@/features/projects/ProjectForm';
import { PriorityBadge, ProjectStatusBadge, Progress, useProjectAccess } from '@/features/projects/shared';
import { TaskBoard } from '@/features/projects/TaskBoard';
import { TaskDrawer } from '@/features/projects/TaskDrawer';
import { NewTaskDrawer } from '@/features/projects/TaskForm';
import { TaskList } from '@/features/projects/TaskList';
import { TimePanel } from '@/features/projects/TimePanel';
import styles from '@/features/projects/projects.module.scss';

const TABS = ['overview', 'board', 'list', 'milestones', 'members', 'time', 'expenses', 'files'] as const;
type TabId = (typeof TABS)[number];

function ProjectDetail({ id }: { id: number }) {
  const router = useRouter();
  const pathname = usePathname();
  const search = useSearchParams();
  const can = useCan();
  const q = useProject(id);
  const project = q.data;
  const access = useProjectAccess(project);
  const milestones = useMilestones(id, !!project);
  const del = projects.useDelete({ onSuccess: () => router.replace('/projects') });
  const [editing, setEditing] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [newTask, setNewTask] = useState<TaskStatus | null>(null);

  const rawTab = search.get('tab') as TabId | null;
  const taskParam = Number(search.get('task'));
  const taskId = Number.isInteger(taskParam) && taskParam > 0 ? taskParam : null;

  const setParams = useCallback(
    (patch: Record<string, string | null>) => {
      const next = new URLSearchParams(search.toString());
      for (const [k, v] of Object.entries(patch)) {
        if (v === null) next.delete(k);
        else next.set(k, v);
      }
      const qs = next.toString();
      router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
    },
    [search, pathname, router],
  );
  const openTask = useCallback((tid: number) => setParams({ task: String(tid) }), [setParams]);
  const closeTask = useCallback(() => setParams({ task: null }), [setParams]);

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!project) return <SkeletonCard lines={6} />;

  const visibleTabs = TABS.filter((t) => t !== 'expenses' || access.financials);
  const tab: TabId = rawTab && visibleTabs.includes(rawTab) ? rawTab : 'overview';
  const members = project.members ?? [];
  const milestoneRows = milestones.data ?? [];
  const pct = project.tasks_count ? ((project.done_tasks_count ?? 0) * 100) / project.tasks_count : 0;
  const overdue = !!project.due_date && ['planning', 'active', 'on_hold'].includes(project.status) && project.due_date < new Date().toISOString().slice(0, 10);

  return (
    <div className={styles.page}>
      <PageHeader
        title={
          <span className={styles.row}>
            {project.color && <span className={styles.swatch} style={{ background: project.color }} aria-hidden />}
            {project.name}
          </span>
        }
        description={
          <span className={styles.row}>
            <span>{project.number}</span>
            {project.manager && <span>· Manager {project.manager.name}</span>}
            {project.due_date && <span className={overdue ? styles.danger : undefined}>· Due {formatDate(project.due_date)}{overdue ? ' (overdue)' : ''}</span>}
          </span>
        }
        backHref="/projects"
        backLabel="Projects"
        meta={
          <span className={styles.row}>
            <ProjectStatusBadge status={project.status} />
            <PriorityBadge priority={project.priority} />
          </span>
        }
        actions={
          <>
            {access.manage && <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setDeleting(true)}>Delete</Button>}
            {access.manage && <Button variant="secondary" leftIcon={<Pencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>}
            {access.work && <Button leftIcon={<Plus size={16} />} onClick={() => setNewTask('todo')}>New task</Button>}
          </>
        }
      />

      <div style={{ maxWidth: 420 }}>
        <Progress value={pct} label="Project completion" color={project.color} />
        <span className={styles.note}>{project.done_tasks_count ?? 0} of {project.tasks_count ?? 0} tasks done</span>
      </div>

      {!can(['projects.view', 'projects.manage'], 'any') && !access.isMember && (
        <Alert variant="info">You can see this project through your task access.</Alert>
      )}

      <Card>
        <Tabs value={tab} onValueChange={(v) => setParams({ tab: v === 'overview' ? null : v })}>
          <div style={{ padding: '0 16px', overflowX: 'auto' }}>
            <TabList>
              <Tab value="overview" icon={<LayoutDashboard size={15} />}>Overview</Tab>
              <Tab value="board" icon={<KanbanSquare size={15} />}>Board</Tab>
              <Tab value="list" icon={<ListChecks size={15} />} count={project.tasks_count}>List</Tab>
              <Tab value="milestones" icon={<Flag size={15} />} count={milestoneRows.length || undefined}>Milestones</Tab>
              <Tab value="members" icon={<Users size={15} />} count={members.length}>Members</Tab>
              <Tab value="time" icon={<Clock size={15} />}>Time</Tab>
              {access.financials && <Tab value="expenses" icon={<Receipt size={15} />}>Expenses</Tab>}
              <Tab value="files" icon={<FileText size={15} />}>Files</Tab>
            </TabList>
          </div>
          <div style={{ padding: 16 }}>
            <TabPanel value="overview">
              <OverviewPanel project={project} onOpenTask={openTask} onShowTab={(t) => setParams({ tab: t })} />
            </TabPanel>
            <TabPanel value="board">
              <TaskBoard projectId={project.id} members={members} milestones={milestoneRows} canWork={access.work} onOpenTask={openTask} onNewTask={setNewTask} />
            </TabPanel>
            <TabPanel value="list">
              <TaskList projectId={project.id} members={members} milestones={milestoneRows} canWork={access.work} onOpenTask={openTask} onNewTask={() => setNewTask('todo')} />
            </TabPanel>
            <TabPanel value="milestones">
              <MilestonesPanel projectId={project.id} canManage={access.manage} />
            </TabPanel>
            <TabPanel value="members">
              <MembersPanel project={project} canManage={access.manage} />
            </TabPanel>
            <TabPanel value="time">
              <TimePanel project={project} />
            </TabPanel>
            {access.financials && (
              <TabPanel value="expenses">
                <ExpensesPanel project={project} canManage={access.manage} />
              </TabPanel>
            )}
            <TabPanel value="files">
              <LinkedDocuments type="project" id={project.id} title="" emptyText="No files uploaded to this project yet." />
            </TabPanel>
          </div>
        </Tabs>
      </Card>

      {taskId !== null && (
        <TaskDrawer
          key={taskId}
          taskId={taskId}
          open
          members={members}
          milestones={milestoneRows}
          canWork={access.work}
          meId={access.me?.id}
          onClose={closeTask}
          onOpenTask={openTask}
        />
      )}
      {newTask && (
        <NewTaskDrawer
          key={newTask}
          open
          projectId={project.id}
          status={newTask}
          members={members}
          milestones={milestoneRows}
          onClose={() => setNewTask(null)}
        />
      )}
      {editing && <ProjectForm key={`edit-${project.id}`} open project={project as Project} onClose={() => setEditing(false)} />}
      <ConfirmDialog
        open={deleting}
        onClose={() => setDeleting(false)}
        title={`Delete ${project.name}?`}
        description="The project is archived with its tasks, milestones and time. This can be undone only by an administrator."
        destructive
        typeToConfirm={project.number}
        confirmLabel="Delete project"
        onConfirm={async () => {
          await del.mutateAsync(project.id).catch(() => undefined);
          setDeleting(false);
        }}
      />
    </div>
  );
}

export default function ProjectPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  return (
    <Suspense fallback={<SkeletonCard lines={6} />}>
      <ProjectDetail id={Number(id)} />
    </Suspense>
  );
}
