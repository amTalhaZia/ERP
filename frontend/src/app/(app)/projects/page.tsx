'use client';

import { Suspense, useMemo, useState } from 'react';
import Link from 'next/link';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { AlertTriangle, CalendarClock, CheckSquare, FolderKanban, LayoutGrid, List, ListTodo, Plus } from 'lucide-react';
import {
  Avatar, Button, DataTable, EmptyState, ErrorState, FilterBar, PageHeader, SearchInput, SegmentedControl, Select, Skeleton,
  Switch, Tab, TabList, TabPanel, Tabs, MetricCard, paginationFromMeta, type DataTableColumn,
} from '@/components/ui';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';
import { cn } from '@/lib/cn';
import crud from '@/components/crud/crud.module.scss';
import {
  PRIORITIES, PROJECT_STATUSES, TASK_STATUSES, TASK_STATUS_LABELS, projectsApi, usePmQuery,
  type OverviewProject, type Task,
} from '@/features/projects/api';
import { ProjectForm } from '@/features/projects/ProjectForm';
import { PriorityBadge, ProjectStatusBadge, Progress, TaskStatusBadge, enumOptions, todayIso } from '@/features/projects/shared';
import styles from '@/features/projects/projects.module.scss';

type View = 'portfolio' | 'mine';
type Layout = 'cards' | 'list';

function useParam(name: string) {
  const search = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const value = search.get(name);
  const set = (v: string | null) => {
    const next = new URLSearchParams(search.toString());
    if (v === null) next.delete(name);
    else next.set(name, v);
    // Switching views resets paging & filters that belong to the other view.
    if (name === 'view') [...next.keys()].forEach((k) => (k.startsWith('f.') || k === 'page' || k === 'search') && next.delete(k));
    const qs = next.toString();
    router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
  };
  return [value, set] as const;
}

function budgetUsed(p: OverviewProject): string {
  if (!p.budget || Number(p.budget) === 0) return '—';
  return formatMoney(p.budget, p.currency, undefined, { maximumFractionDigits: 0 });
}

function Portfolio({ layout, setLayout }: { layout: Layout; setLayout: (l: Layout) => void }) {
  const router = useRouter();
  const list = useListState();
  const { state, update, clearFilters, activeFilterCount } = list;
  const status = state.filters.status ?? '';
  const overview = usePmQuery(['overview', status], () => projectsApi.overview(status || undefined), { keepPrevious: true });
  const o = overview.data;

  const managers = useMemo(() => {
    const m = new Map<number, string>();
    for (const p of o?.projects ?? []) if (p.manager) m.set(p.manager.id, p.manager.name);
    return [...m.entries()].sort((a, b) => a[1].localeCompare(b[1])).map(([id, name]) => ({ value: String(id), label: name }));
  }, [o]);

  const rows = useMemo(() => {
    const term = state.search.trim().toLowerCase();
    return (o?.projects ?? []).filter(
      (p) =>
        (!state.filters.priority || p.priority === state.filters.priority) &&
        (!state.filters.manager_id || String(p.manager?.id ?? '') === state.filters.manager_id) &&
        (!term || p.name.toLowerCase().includes(term) || p.number.toLowerCase().includes(term)),
    );
  }, [o, state.search, state.filters.priority, state.filters.manager_id]);

  const columns: DataTableColumn<OverviewProject>[] = [
    {
      id: 'name',
      header: 'Project',
      cell: ({ row }) => (
        <div className={crud.cellMain}>
          <strong>
            {row.original.color && <span className={styles.swatch} style={{ background: row.original.color, marginRight: 6 }} aria-hidden />}
            {row.original.name}
          </strong>
          <span>{row.original.number}{row.original.manager ? ` · ${row.original.manager.name}` : ''}</span>
        </div>
      ),
    },
    { id: 'status', header: 'Status', cell: ({ row }) => <ProjectStatusBadge status={row.original.status} /> },
    { id: 'priority', header: 'Priority', cell: ({ row }) => <PriorityBadge priority={row.original.priority} /> },
    {
      id: 'progress',
      header: 'Progress',
      meta: { minWidth: 160 },
      cell: ({ row }) => <Progress size="sm" value={row.original.percent_complete} label={`${row.original.name} completion`} color={row.original.color} />,
    },
    {
      id: 'due',
      header: 'Due',
      cell: ({ row }) => <span className={cn(row.original.is_overdue && styles.danger)}>{formatDate(row.original.due_date)}{row.original.is_overdue && <span className={styles.srOnly}> (overdue)</span>}</span>,
    },
    { id: 'overdue', header: 'Overdue tasks', meta: { numeric: true }, cell: ({ row }) => <span className={cn(row.original.overdue_tasks > 0 && styles.danger)}>{row.original.overdue_tasks}</span> },
    { id: 'hours', header: 'Hours', meta: { numeric: true }, cell: ({ row }) => formatNumber(row.original.hours_logged, { maximumFractionDigits: 1 }) },
    { id: 'budget', header: 'Budget', meta: { numeric: true }, cell: ({ row }) => budgetUsed(row.original) },
  ];

  const filtered = !!state.search || activeFilterCount > 0;
  return (
    <div className={styles.stack}>
      <section className={styles.metrics} aria-label="Portfolio totals">
        <MetricCard label="Open projects" icon={<FolderKanban size={18} />} loading={overview.isLoading} value={o?.totals.open ?? 0} footer={o ? `${o.totals.projects} total · ${o.totals.by_status.completed} completed` : undefined} />
        <MetricCard label="Overdue projects" icon={<CalendarClock size={18} />} loading={overview.isLoading} value={o?.totals.overdue_projects ?? 0} />
        <MetricCard label="Overdue tasks" icon={<AlertTriangle size={18} />} loading={overview.isLoading} value={o?.totals.overdue_tasks ?? 0} />
        <MetricCard label="My open tasks" icon={<CheckSquare size={18} />} loading={overview.isLoading} value={o?.totals.my_open_tasks ?? 0} />
      </section>

      <div className={crud.toolbar}>
        <SearchInput className={crud.search} placeholder="Search name or number" aria-label="Search projects" defaultValue={state.search} onDebouncedChange={(v: string) => update({ search: v })} />
        <FilterBar activeCount={activeFilterCount} onClear={clearFilters}>
          <Select
            aria-label="Status"
            placeholder="Any status"
            value={status}
            onChange={(e) => update({ filters: { status: e.target.value || undefined } })}
            options={PROJECT_STATUSES.map((s) => ({ value: s, label: `${enumOptions([s])[0]!.label}${o ? ` (${o.totals.by_status[s]})` : ''}` }))}
          />
          <Select aria-label="Priority" placeholder="Any priority" value={state.filters.priority ?? ''} onChange={(e) => update({ filters: { priority: e.target.value || undefined } })} options={enumOptions(PRIORITIES)} />
          <Select aria-label="Manager" placeholder="Any manager" value={state.filters.manager_id ?? ''} onChange={(e) => update({ filters: { manager_id: e.target.value || undefined } })} options={managers} />
        </FilterBar>
        <span className={styles.spacer} />
        <SegmentedControl<Layout>
          aria-label="Layout"
          size="sm"
          value={layout}
          onChange={setLayout}
          options={[{ value: 'cards', label: 'Cards', icon: <LayoutGrid size={14} /> }, { value: 'list', label: 'List', icon: <List size={14} /> }]}
        />
      </div>

      {overview.error ? (
        <ErrorState error={overview.error} onRetry={() => void overview.refetch()} />
      ) : layout === 'list' ? (
        <DataTable<OverviewProject>
          aria-label="Projects"
          columns={columns}
          data={rows}
          isLoading={overview.isLoading}
          onRowClick={(p) => router.push(`/projects/${p.id}`)}
          emptyState={<EmptyState icon={<FolderKanban size={22} />} title={filtered ? 'No matching projects' : 'No projects yet'} />}
        />
      ) : overview.isLoading ? (
        <div className={styles.cards}>{[0, 1, 2].map((i) => <Skeleton key={i} height={190} />)}</div>
      ) : rows.length === 0 ? (
        <EmptyState icon={<FolderKanban size={22} />} title={filtered ? 'No matching projects' : 'No projects yet'} description={filtered ? 'Try a different search or clear the filters.' : 'Create a project to start planning tasks and tracking time.'} />
      ) : (
        <ul className={styles.cards} style={{ listStyle: 'none', margin: 0, padding: 0 }}>
          {rows.map((p) => (
            <li key={p.id}>
              <Link href={`/projects/${p.id}`} className={styles.projectCard} style={{ ['--project-color' as string]: p.color ?? undefined }}>
                <div className={styles.cardHead}>
                  <div>
                    <strong>{p.name}</strong>
                    <small>{p.number}</small>
                  </div>
                  <ProjectStatusBadge status={p.status} size="sm" />
                </div>
                <Progress value={p.percent_complete} label={`${p.name} completion`} color={p.color} />
                <dl className={styles.cardStats}>
                  <div><dt>Tasks</dt><dd>{p.tasks_done}/{p.tasks_total}</dd></div>
                  <div><dt>Overdue tasks</dt><dd className={cn(p.overdue_tasks > 0 && styles.danger)}>{p.overdue_tasks}</dd></div>
                  <div><dt>Due</dt><dd className={cn(p.is_overdue && styles.danger)}>{formatDate(p.due_date)}{p.is_overdue && <span className={styles.srOnly}> (overdue)</span>}</dd></div>
                  <div><dt>Budget</dt><dd>{budgetUsed(p)}</dd></div>
                </dl>
                <div className={styles.cardFoot}>
                  {p.manager ? <><Avatar name={p.manager.name} src={p.manager.avatar_url} size="xs" /><span>{p.manager.name}</span></> : <span>No manager</span>}
                  <span className={styles.spacer} />
                  <PriorityBadge priority={p.priority} />
                  <span>{formatNumber(p.hours_logged, { maximumFractionDigits: 1 })} h</span>
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}
      {(o?.projects.length ?? 0) >= 200 && <p className={styles.note}>Showing the first 200 projects by due date.</p>}
    </div>
  );
}

function MyTasks() {
  const router = useRouter();
  const list = useListState({ sort: 'due_date' });
  const { state, update, clearFilters, activeFilterCount } = list;
  const includeDone = state.filters.include_done === '1';
  const params = useMemo(() => {
    const p: Record<string, unknown> = { ...list.apiParams };
    delete p['filter[include_done]'];
    delete p['filter[due]'];
    if (includeDone) p.include_done = 1;
    if (state.filters.due) p.due = state.filters.due;
    return p;
  }, [list.apiParams, includeDone, state.filters.due]);
  const q = usePmQuery(['my-tasks', params], () => projectsApi.myTasks(params), { keepPrevious: true });
  const today = todayIso();
  const meta = q.data?.meta.pagination;

  const columns: DataTableColumn<Task>[] = [
    {
      id: 'title',
      accessorKey: 'title',
      header: 'Task',
      enableSorting: true,
      cell: ({ row }) => (
        <div className={crud.cellMain}>
          <strong>{row.original.title}</strong>
          <span>{row.original.project ? `${row.original.project.number} · ${row.original.project.name}` : ''}{row.original.milestone ? ` · ${row.original.milestone.name}` : ''}</span>
        </div>
      ),
    },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <TaskStatusBadge status={row.original.status} /> },
    { id: 'priority', accessorKey: 'priority', header: 'Priority', enableSorting: true, cell: ({ row }) => <PriorityBadge priority={row.original.priority} /> },
    {
      id: 'due_date',
      accessorKey: 'due_date',
      header: 'Due',
      enableSorting: true,
      cell: ({ row }) => {
        const overdue = row.original.status !== 'done' && !!row.original.due_date && row.original.due_date < today;
        return <span className={cn(overdue && styles.danger)}>{formatDate(row.original.due_date)}{overdue && <span className={styles.srOnly}> (overdue)</span>}</span>;
      },
    },
  ];

  return (
    <div className={styles.stack}>
      <div className={crud.toolbar}>
        <SearchInput className={crud.search} placeholder="Search my tasks" aria-label="Search my tasks" defaultValue={state.search} onDebouncedChange={(v: string) => update({ search: v })} />
        <FilterBar activeCount={activeFilterCount} onClear={clearFilters}>
          <Select aria-label="Status" placeholder="Any open status" value={state.filters.status ?? ''} onChange={(e) => update({ filters: { status: e.target.value || undefined } })} options={enumOptions(TASK_STATUSES, TASK_STATUS_LABELS)} />
          <Select aria-label="Priority" placeholder="Any priority" value={state.filters.priority ?? ''} onChange={(e) => update({ filters: { priority: e.target.value || undefined } })} options={enumOptions(PRIORITIES)} />
          <Select
            aria-label="Due"
            placeholder="Any due date"
            value={state.filters.due ?? ''}
            onChange={(e) => update({ filters: { due: e.target.value || undefined } })}
            options={[{ value: 'overdue', label: 'Overdue' }, { value: 'today', label: 'Due today' }, { value: 'week', label: 'Due within 7 days' }, { value: 'none', label: 'No due date' }]}
          />
          <Switch size="sm" checked={includeDone} onCheckedChange={(v) => update({ filters: { include_done: v ? '1' : undefined } })} label="Include done" />
        </FilterBar>
      </div>
      <DataTable<Task>
        aria-label="My tasks"
        columns={columns}
        data={q.data?.data ?? []}
        isLoading={q.isLoading || (q.isFetching && q.isPlaceholderData)}
        error={q.error}
        onRetry={() => void q.refetch()}
        sort={state.sort}
        onSortChange={(sort) => update({ sort })}
        pagination={meta ? { ...paginationFromMeta(meta), onPageChange: (page: number) => update({ page }, false), onPerPageChange: (perPage: number) => update({ perPage }) } : undefined}
        onRowClick={(t) => router.push(`/projects/${t.project_id}?task=${t.id}`)}
        emptyState={<EmptyState icon={<ListTodo size={22} />} title={activeFilterCount || state.search ? 'No matching tasks' : 'Nothing assigned to you'} description={activeFilterCount || state.search ? 'Try clearing the filters.' : 'Tasks assigned to you across all projects show up here.'} />}
      />
    </div>
  );
}

function ProjectsInner() {
  const router = useRouter();
  const can = useCan();
  const [viewParam, setView] = useParam('view');
  const [layoutParam, setLayout] = useParam('layout');
  const [creating, setCreating] = useState(false);
  const view: View = viewParam === 'mine' ? 'mine' : 'portfolio';
  const layout: Layout = layoutParam === 'list' ? 'list' : 'cards';
  const canPortfolio = can(['projects.view', 'projects.manage', 'tasks.view'], 'any');

  return (
    <div className={styles.page}>
      <PageHeader
        title="Projects"
        description="Plan work, track progress and log time across every project."
        actions={can('projects.manage') && <Button leftIcon={<Plus size={16} />} onClick={() => setCreating(true)}>New project</Button>}
      />
      <Tabs value={view} onValueChange={(v) => setView(v === 'mine' ? 'mine' : null)}>
        <TabList>
          <Tab value="portfolio" icon={<FolderKanban size={15} />}>Portfolio</Tab>
          <Tab value="mine" icon={<CheckSquare size={15} />}>My tasks</Tab>
        </TabList>
        <div style={{ paddingTop: 16 }}>
          <TabPanel value="portfolio">
            {canPortfolio ? <Portfolio layout={layout} setLayout={(l) => setLayout(l === 'list' ? 'list' : null)} /> : <EmptyState title="You don’t have access to projects" />}
          </TabPanel>
          <TabPanel value="mine">
            <MyTasks />
          </TabPanel>
        </div>
      </Tabs>
      {creating && <ProjectForm key="new" open project={null} onClose={() => setCreating(false)} onSaved={(p) => router.push(`/projects/${p.id}`)} />}
    </div>
  );
}

export default function ProjectsPage() {
  return (
    <Suspense>
      <ProjectsInner />
    </Suspense>
  );
}
