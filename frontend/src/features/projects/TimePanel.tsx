'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Clock, Pencil, Plus, Timer, Trash2, Wallet } from 'lucide-react';
import {
  Alert, Badge, Button, ConfirmDialog, CurrencyInput, DataTable, DateInput, Drawer, EmptyState, FormField, FormGrid, Input, MenuItem, MenuSeparator,
  MetricCard, Select, Switch, Textarea, paginationFromMeta, type DataTableColumn,
} from '@/components/ui';
import { ChartCard } from '@/components/charts/ChartCard';
import { ChartTooltip } from '@/components/charts/ChartTooltip';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';
import { api } from '@/lib/api/client';
import type { Single } from '@/lib/api/types';
import crud from '@/components/crud/crud.module.scss';
import {
  projectsApi, timeEntries, usePmMutation, usePmQuery,
  type Project, type TimeEntry, type TimeEntryInput,
} from './api';
import { nullable, todayIso, useProjectAccess } from './shared';
import styles from './projects.module.scss';

const hoursFmt = (v: number) => `${formatNumber(v, { maximumFractionDigits: 2 })} h`;

export function TimePanel({ project }: { project: Project }) {
  const access = useProjectAccess(project);
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [editing, setEditing] = useState<TimeEntry | 'new' | null>(null);
  const [deleting, setDeleting] = useState<TimeEntry | null>(null);

  const summary = usePmQuery(['time-summary', project.id, from, to], () => projectsApi.timeSummary(project.id, from, to));
  const listParams: Record<string, unknown> = { 'filter[project_id]': project.id, page, per_page: perPage, sort: '-date' };
  if (from) listParams.from = from;
  if (to) listParams.to = to;
  const entries = timeEntries.useList(listParams);
  const del = usePmMutation((id: number) => api.delete(`/time-entries/${id}`).then(() => undefined), { success: 'Time entry deleted', errorTitle: 'Could not delete entry' });

  const s = summary.data;
  const currency = s?.totals.currency ?? project.currency;
  const byUser = (s?.by_user ?? []).map((u) => ({ label: u.name, Billable: Number(u.billable_hours), 'Non-billable': Number(u.hours) - Number(u.billable_hours) }));
  const byTask = (s?.by_task ?? []).slice(0, 10).map((t) => ({ label: t.title, Hours: Number(t.hours) }));
  const canEdit = (e: TimeEntry) => access.manage || (e.user_id === access.me?.id && access.logTime);
  const meta = entries.data?.meta.pagination;

  const columns: DataTableColumn<TimeEntry>[] = [
    { id: 'date', header: 'Date', cell: ({ row }) => formatDate(row.original.date) },
    { id: 'user', header: 'Person', cell: ({ row }) => row.original.user?.name ?? `User #${row.original.user_id}` },
    { id: 'task', header: 'Task', cell: ({ row }) => row.original.task?.title ?? <span className={crud.muted}>No task</span> },
    {
      id: 'description',
      header: 'Notes',
      meta: { truncate: true, maxWidth: 260 },
      cell: ({ row }) => row.original.description ?? <span className={crud.muted}>—</span>,
    },
    {
      id: 'billable',
      header: 'Billable',
      cell: ({ row }) => (row.original.is_billable ? <Badge size="sm" variant="success">{row.original.hourly_rate ? `${formatMoney(row.original.hourly_rate, currency)}/h` : 'Billable'}</Badge> : <span className={crud.muted}>No</span>),
    },
    { id: 'hours', header: 'Hours', meta: { numeric: true }, cell: ({ row }) => hoursFmt(Number(row.original.hours)) },
  ];

  return (
    <div className={styles.stack}>
      <div className={styles.toolbar}>
        <FormField label="From" inline>
          <DateInput size="sm" value={from} max={to || undefined} onValueChange={(v) => { setFrom(v ?? ''); setPage(1); }} />
        </FormField>
        <FormField label="To" inline>
          <DateInput size="sm" value={to} min={from || undefined} onValueChange={(v) => { setTo(v ?? ''); setPage(1); }} />
        </FormField>
        {(from || to) && <Button size="sm" variant="ghost" onClick={() => { setFrom(''); setTo(''); }}>Clear dates</Button>}
        <span className={styles.spacer} />
        {access.logTime && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>Log time</Button>}
      </div>
      {!access.viewAllTime && <Alert variant="info">You see only your own time entries; totals and charts include the whole team.</Alert>}

      <section className={styles.metrics} aria-label="Time totals">
        <MetricCard label="Total hours" icon={<Clock size={18} />} loading={summary.isLoading} value={s ? hoursFmt(Number(s.totals.hours)) : '—'} />
        <MetricCard label="Billable hours" icon={<Timer size={18} />} loading={summary.isLoading} value={s ? hoursFmt(Number(s.totals.billable_hours)) : '—'} />
        <MetricCard label="Non-billable" icon={<Timer size={18} />} loading={summary.isLoading} value={s ? hoursFmt(Number(s.totals.non_billable_hours)) : '—'} />
        <MetricCard label="Billable amount" icon={<Wallet size={18} />} loading={summary.isLoading} value={s ? formatMoney(s.totals.billable_amount, currency) : '—'} footer="Σ billable hours × rate" />
      </section>

      <div className={styles.grid2}>
        <ChartCard
          title="Hours by person"
          loading={summary.isLoading}
          empty={!summary.isLoading && byUser.length === 0}
          emptyText="No time logged"
          legend={[{ label: 'Billable', color: 'var(--chart-1)' }, { label: 'Non-billable', color: 'var(--chart-4)' }]}
          height={Math.max(200, byUser.length * 40)}
          table={
            <table className={crud.lines}>
              <thead><tr><th>Person</th><th className={crud.r}>Billable</th><th className={crud.r}>Total</th></tr></thead>
              <tbody>{(s?.by_user ?? []).map((u) => <tr key={u.user_id}><td>{u.name}</td><td className={crud.r}>{u.billable_hours}</td><td className={crud.r}>{u.hours}</td></tr>)}</tbody>
            </table>
          }
        >
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={byUser} layout="vertical" margin={{ top: 4, right: 16, left: 8, bottom: 0 }}>
              <CartesianGrid horizontal={false} stroke="var(--chart-grid)" />
              <XAxis type="number" tickLine={false} axisLine={false} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} />
              <YAxis type="category" dataKey="label" width={120} tickLine={false} axisLine={false} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} interval={0} />
              <Tooltip content={<ChartTooltip formatValue={hoursFmt} />} cursor={{ fill: 'var(--color-surface-hover)' }} />
              <Bar dataKey="Billable" stackId="h" fill="var(--chart-1)" maxBarSize={22} />
              <Bar dataKey="Non-billable" stackId="h" fill="var(--chart-4)" radius={[0, 4, 4, 0]} maxBarSize={22} />
            </BarChart>
          </ResponsiveContainer>
        </ChartCard>
        <ChartCard
          title="Hours by task"
          description={(s?.by_task.length ?? 0) > 10 ? 'Top 10 tasks' : undefined}
          loading={summary.isLoading}
          empty={!summary.isLoading && byTask.length === 0}
          emptyText="No time logged"
          height={Math.max(200, byTask.length * 36)}
          table={
            <table className={crud.lines}>
              <thead><tr><th>Task</th><th className={crud.r}>Hours</th></tr></thead>
              <tbody>{(s?.by_task ?? []).map((t) => <tr key={t.task_id ?? 'none'}><td>{t.title}</td><td className={crud.r}>{t.hours}</td></tr>)}</tbody>
            </table>
          }
        >
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={byTask} layout="vertical" margin={{ top: 4, right: 16, left: 8, bottom: 0 }}>
              <CartesianGrid horizontal={false} stroke="var(--chart-grid)" />
              <XAxis type="number" tickLine={false} axisLine={false} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} />
              <YAxis type="category" dataKey="label" width={140} tickLine={false} axisLine={false} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} interval={0} tickFormatter={(v: string) => (v.length > 20 ? `${v.slice(0, 19)}…` : v)} />
              <Tooltip content={<ChartTooltip formatValue={hoursFmt} />} cursor={{ fill: 'var(--color-surface-hover)' }} />
              <Bar dataKey="Hours" fill="var(--chart-2)" radius={[0, 4, 4, 0]} maxBarSize={22} />
            </BarChart>
          </ResponsiveContainer>
        </ChartCard>
      </div>

      <DataTable<TimeEntry>
        aria-label="Time entries"
        columns={columns}
        data={entries.data?.data ?? []}
        isLoading={entries.isLoading || (entries.isFetching && entries.isPlaceholderData)}
        error={entries.error}
        onRetry={() => void entries.refetch()}
        pagination={meta ? { ...paginationFromMeta(meta), onPageChange: setPage, onPerPageChange: (n: number) => { setPerPage(n); setPage(1); } } : undefined}
        onRowClick={(e) => (canEdit(e) ? setEditing(e) : undefined)}
        rowActions={!(access.logTime || access.manage) ? undefined : (e) =>
          canEdit(e) ? (
            <>
              <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(e)}>Edit</MenuItem>
              <MenuSeparator />
              <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(e)}>Delete</MenuItem>
            </>
          ) : null
        }
        emptyState={<EmptyState icon={<Clock size={22} />} title="No time logged" action={access.logTime ? <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>Log time</Button> : undefined} />}
      />

      {editing && (
        <TimeEntryDrawer key={editing === 'new' ? 'new' : editing.id} project={project} entry={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      )}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title="Delete this time entry?"
        description={deleting ? `${hoursFmt(Number(deleting.hours))} on ${formatDate(deleting.date)}` : undefined}
        destructive
        confirmLabel="Delete entry"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </div>
  );
}

const timeSchema = z.object({
  date: z.string().min(1, 'Date is required'),
  hours: z.string().trim().regex(/^\d{1,2}(\.\d{1,2})?$/, 'Hours with up to 2 decimals').refine((v) => Number(v) > 0 && Number(v) <= 24, 'Between 0 and 24 hours'),
  task_id: z.string(),
  user_id: z.string(),
  description: z.string().trim().max(500),
  is_billable: z.boolean(),
  hourly_rate: z.string().nullable(),
});
type TimeValues = z.infer<typeof timeSchema>;

export function TimeEntryDrawer({ project, entry, defaultTaskId, onClose }: { project: Project; entry: TimeEntry | null; defaultTaskId?: number; onClose: () => void }) {
  const access = useProjectAccess(project);
  const [formError, setFormError] = useState<string | null>(null);
  const tasks = usePmQuery(['tasks', project.id, 'time-picker'], () => projectsApi.tasks(project.id, { per_page: 100, sort: 'title' }), { staleTime: 30_000 });
  const form = useForm<TimeValues>({
    resolver: zodResolver(timeSchema),
    defaultValues: {
      date: entry?.date ?? todayIso(),
      hours: entry ? String(Number(entry.hours)) : '',
      task_id: entry?.task_id ? String(entry.task_id) : defaultTaskId ? String(defaultTaskId) : '',
      user_id: entry ? String(entry.user_id) : access.me ? String(access.me.id) : '',
      description: entry?.description ?? '',
      is_billable: entry?.is_billable ?? project.is_billable,
      hourly_rate: entry?.hourly_rate ?? null,
    },
  });
  const { register, handleSubmit, control, setError, formState, watch } = form;
  const save = usePmMutation(
    (body: TimeEntryInput) =>
      entry
        ? api.put<Single<TimeEntry>>(`/time-entries/${entry.id}`, body).then((r) => r.data.data)
        : api.post<Single<TimeEntry>>('/time-entries', body).then((r) => r.data.data),
    { success: entry ? 'Time entry saved' : 'Time logged', errorTitle: 'Could not save time entry' },
  );

  const onSubmit = async (v: TimeValues) => {
    setFormError(null);
    const body: TimeEntryInput = {
      project_id: project.id,
      task_id: v.task_id ? Number(v.task_id) : null,
      date: v.date,
      hours: v.hours,
      description: nullable(v.description),
      is_billable: v.is_billable,
      hourly_rate: v.is_billable ? v.hourly_rate || null : null,
    };
    if (access.manage && v.user_id) body.user_id = Number(v.user_id);
    try {
      await save.mutateAsync(body);
      onClose();
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(timeSchema.shape)));
    }
  };

  const e = formState.errors;
  const billable = watch('is_billable');
  const memberOptions = (project.members ?? []).filter((m) => m.user).map((m) => ({ value: String(m.user_id), label: m.user!.name }));
  if (entry?.user && !memberOptions.some((o) => o.value === String(entry.user_id))) memberOptions.push({ value: String(entry.user_id), label: entry.user.name });
  const taskOptions = (tasks.data?.data ?? []).map((t) => ({ value: String(t.id), label: t.parent_id ? `↳ ${t.title}` : t.title }));
  if (entry?.task && !taskOptions.some((o) => o.value === String(entry.task_id))) taskOptions.unshift({ value: String(entry.task.id), label: entry.task.title });

  return (
    <Drawer
      open
      onClose={onClose}
      size="md"
      title={entry ? 'Edit time entry' : 'Log time'}
      description={`${project.number} · ${project.name}`}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="time-form" loading={formState.isSubmitting}>{entry ? 'Save' : 'Log time'}</Button>
        </>
      }
    >
      <form id="time-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          <FormField label="Date" required error={e.date?.message}>
            {(p) => <Controller control={control} name="date" render={({ field }) => <DateInput {...p} value={field.value} onValueChange={(v) => field.onChange(v ?? '')} />} />}
          </FormField>
          <FormField label="Hours" required error={e.hours?.message} hint="e.g. 1.5">
            <Input inputMode="decimal" {...register('hours')} autoFocus />
          </FormField>
          <FormField label="Task" span="full" error={e.task_id?.message}>
            <Select {...register('task_id')} options={[{ value: '', label: tasks.isLoading ? 'Loading…' : 'No specific task' }, ...taskOptions]} />
          </FormField>
          {access.manage && (
            <FormField label="Person" span="full" error={e.user_id?.message} hint="Project managers can log time for others">
              <Select {...register('user_id')} options={memberOptions} />
            </FormField>
          )}
          <FormField label="Notes" span="full" error={e.description?.message}>
            <Textarea rows={3} maxLength={500} {...register('description')} />
          </FormField>
          <Controller control={control} name="is_billable" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Billable" />} />
          {billable && (
            <FormField label="Hourly rate" error={e.hourly_rate?.message}>
              {(p) => <Controller control={control} name="hourly_rate" render={({ field }) => <CurrencyInput {...p} currency={project.currency} value={field.value} onValueChange={field.onChange} />} />}
            </FormField>
          )}
        </FormGrid>
      </form>
    </Drawer>
  );
}
