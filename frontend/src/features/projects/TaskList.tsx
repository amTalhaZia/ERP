'use client';

import { useState } from 'react';
import { ListChecks, Pencil, Plus } from 'lucide-react';
import {
  Avatar, Button, DataTable, EmptyState, FilterBar, MenuItem, SearchInput, Select, paginationFromMeta, type DataTableColumn,
} from '@/components/ui';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/cn';
import crud from '@/components/crud/crud.module.scss';
import {
  PRIORITIES, TASK_STATUSES, TASK_STATUS_LABELS, projectsApi, usePmQuery,
  type Milestone, type ProjectMember, type Task,
} from './api';
import { PriorityBadge, TaskStatusBadge, enumOptions, todayIso } from './shared';
import styles from './projects.module.scss';

type Filters = { status?: string; priority?: string; assignee_id?: string; milestone_id?: string; due?: string };

export function TaskList({
  projectId,
  members,
  milestones,
  canWork,
  onOpenTask,
  onNewTask,
}: {
  projectId: number;
  members: ProjectMember[];
  milestones: Milestone[];
  canWork: boolean;
  onOpenTask: (id: number) => void;
  onNewTask: () => void;
}) {
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [sort, setSort] = useState<string | undefined>('position');
  const [filters, setFilters] = useState<Filters>({});
  const [topLevel, setTopLevel] = useState(true);

  const params: Record<string, unknown> = { page, per_page: perPage, sort, search: search || undefined, due: filters.due || undefined };
  if (topLevel) params.top_level = 1;
  for (const k of ['status', 'priority', 'assignee_id', 'milestone_id'] as const) if (filters[k]) params[`filter[${k}]`] = filters[k];
  const q = usePmQuery(['tasks', projectId, params], () => projectsApi.tasks(projectId, params), { keepPrevious: true });

  const setFilter = (k: keyof Filters, v: string) => {
    setFilters((f) => ({ ...f, [k]: v || undefined }));
    setPage(1);
  };
  const activeCount = Object.values(filters).filter(Boolean).length + (topLevel ? 0 : 1);
  const today = todayIso();
  const meta = q.data?.meta.pagination;

  const columns: DataTableColumn<Task>[] = [
    {
      id: 'title',
      accessorKey: 'title',
      header: 'Task',
      enableSorting: true,
      cell: ({ row }) => {
        const t = row.original;
        return (
          <div className={crud.cellMain}>
            <strong>{t.title}</strong>
            <span>
              {[t.milestone?.name, t.labels.join(', '), t.subtasks_count ? `${t.subtasks_count} subtasks` : null, t.comments_count ? `${t.comments_count} comments` : null]
                .filter(Boolean)
                .join(' · ') || (t.parent_id ? 'Subtask' : ' ')}
            </span>
          </div>
        );
      },
    },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <TaskStatusBadge status={row.original.status} /> },
    { id: 'priority', accessorKey: 'priority', header: 'Priority', enableSorting: true, cell: ({ row }) => <PriorityBadge priority={row.original.priority} /> },
    {
      id: 'assignee',
      header: 'Assignee',
      cell: ({ row }) =>
        row.original.assignee ? (
          <span className={styles.row} style={{ flexWrap: 'nowrap' }}>
            <Avatar name={row.original.assignee.name} src={row.original.assignee.avatar_url} size="xs" />
            {row.original.assignee.name}
          </span>
        ) : (
          <span className={crud.muted}>Unassigned</span>
        ),
    },
    {
      id: 'due_date',
      accessorKey: 'due_date',
      header: 'Due',
      enableSorting: true,
      cell: ({ row }) => {
        const t = row.original;
        const overdue = t.status !== 'done' && !!t.due_date && t.due_date < today;
        return <span className={cn(overdue && styles.danger)}>{formatDate(t.due_date)}{overdue && <span className={styles.srOnly}> (overdue)</span>}</span>;
      },
    },
    {
      id: 'estimate_hours',
      header: 'Estimate',
      meta: { numeric: true },
      cell: ({ row }) => (row.original.estimate_hours ? `${Number(row.original.estimate_hours)} h` : '—'),
    },
  ];

  const memberOptions = members.filter((m) => m.user).map((m) => ({ value: String(m.user_id), label: m.user!.name }));

  return (
    <div className={styles.stack}>
      <div className={crud.toolbar}>
        <SearchInput
          className={crud.search}
          placeholder="Search tasks"
          aria-label="Search tasks"
          defaultValue={search}
          onDebouncedChange={(v: string) => {
            setSearch(v);
            setPage(1);
          }}
        />
        <FilterBar
          activeCount={activeCount}
          onClear={() => {
            setFilters({});
            setTopLevel(true);
            setPage(1);
          }}
        >
          <Select aria-label="Status" placeholder="Any status" value={filters.status ?? ''} onChange={(e) => setFilter('status', e.target.value)} options={enumOptions(TASK_STATUSES, TASK_STATUS_LABELS)} />
          <Select aria-label="Priority" placeholder="Any priority" value={filters.priority ?? ''} onChange={(e) => setFilter('priority', e.target.value)} options={enumOptions(PRIORITIES)} />
          <Select aria-label="Assignee" placeholder="Any assignee" value={filters.assignee_id ?? ''} onChange={(e) => setFilter('assignee_id', e.target.value)} options={memberOptions} />
          {milestones.length > 0 && (
            <Select aria-label="Milestone" placeholder="Any milestone" value={filters.milestone_id ?? ''} onChange={(e) => setFilter('milestone_id', e.target.value)} options={milestones.map((m) => ({ value: String(m.id), label: m.name }))} />
          )}
          <Select
            aria-label="Due"
            placeholder="Any due date"
            value={filters.due ?? ''}
            onChange={(e) => setFilter('due', e.target.value)}
            options={[{ value: 'overdue', label: 'Overdue' }, { value: 'today', label: 'Due today' }, { value: 'week', label: 'Due within 7 days' }, { value: 'none', label: 'No due date' }]}
          />
          <Select
            aria-label="Include subtasks"
            value={topLevel ? 'top' : 'all'}
            onChange={(e) => {
              setTopLevel(e.target.value === 'top');
              setPage(1);
            }}
            options={[{ value: 'top', label: 'Top-level tasks' }, { value: 'all', label: 'Include subtasks' }]}
          />
        </FilterBar>
        {canWork && <Button leftIcon={<Plus size={16} />} onClick={onNewTask}>New task</Button>}
      </div>
      <DataTable<Task>
        aria-label="Tasks"
        columns={columns}
        data={q.data?.data ?? []}
        isLoading={q.isLoading || (q.isFetching && q.isPlaceholderData)}
        error={q.error}
        onRetry={() => void q.refetch()}
        sort={sort}
        onSortChange={(s) => {
          setSort(s);
          setPage(1);
        }}
        pagination={meta ? { ...paginationFromMeta(meta), onPageChange: setPage, onPerPageChange: (n: number) => { setPerPage(n); setPage(1); } } : undefined}
        onRowClick={(t) => onOpenTask(t.id)}
        rowActions={(t) => <MenuItem icon={<Pencil size={16} />} onSelect={() => onOpenTask(t.id)}>{canWork ? 'Open & edit' : 'Open'}</MenuItem>}
        emptyState={
          search || activeCount > 0 ? (
            <EmptyState icon={<ListChecks size={22} />} title="No matching tasks" description="Try a different search or clear the filters." />
          ) : (
            <EmptyState icon={<ListChecks size={22} />} title="No tasks yet" action={canWork ? <Button leftIcon={<Plus size={16} />} onClick={onNewTask}>Add the first task</Button> : undefined} />
          )
        }
      />
    </div>
  );
}
