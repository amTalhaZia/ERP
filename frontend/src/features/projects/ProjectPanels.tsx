'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { AlertTriangle, CheckCircle2, Clock, Flag, Gauge, MoreHorizontal, Pencil, Plus, RotateCcw, Trash2, UserPlus, Users, Wallet } from 'lucide-react';
import {
  Alert, Avatar, Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, DateInput, EmptyState, ErrorState, FormField, IconButton, Input, Menu,
  MenuItem, MenuSeparator, MetricCard, Modal, Select, Skeleton, Textarea,
} from '@/components/ui';
import { ChartCard } from '@/components/charts/ChartCard';
import { ChartTooltip } from '@/components/charts/ChartTooltip';
import type { ApiError } from '@/lib/api/errors';
import { formatDate, formatMoney, formatNumber, formatPercent, humanize } from '@/lib/format';
import { cn } from '@/lib/cn';
import crud from '@/components/crud/crud.module.scss';
import {
  MEMBER_ROLES, TASK_STATUSES, TASK_STATUS_LABELS, projectsApi, useMilestones, usePmMutation, usePmQuery,
  type MemberRole, type Milestone, type MilestoneInput, type Project, type ProjectMember,
} from './api';
import { PriorityBadge, Progress, TASK_STATUS_COLORS, TaskStatusBadge, enumOptions, todayIso, usePeople } from './shared';
import styles from './projects.module.scss';

// ---------------------------------------------------------------------------
// Overview
// ---------------------------------------------------------------------------

export function OverviewPanel({ project, onOpenTask, onShowTab }: { project: Project; onOpenTask: (id: number) => void; onShowTab: (tab: string) => void }) {
  const q = usePmQuery(['summary', project.id], () => projectsApi.summary(project.id));
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  const s = q.data;
  const loading = !s;
  const currency = s?.budget.currency ?? project.currency;
  const money = (v: string | null | undefined) => formatMoney(v, currency, undefined, { maximumFractionDigits: 0 });
  const statusData = TASK_STATUSES.map((st) => ({ name: TASK_STATUS_LABELS[st], value: s?.tasks.by_status[st] ?? 0, color: TASK_STATUS_COLORS[st], status: st }));

  return (
    <div className={styles.stack}>
      <section className={styles.metrics} aria-label="Project summary">
        <MetricCard
          label="Complete"
          icon={<Gauge size={18} />}
          loading={loading}
          value={s ? formatPercent(s.tasks.percent_complete, { fractionDigits: 0 }) : '—'}
          footer={s ? `${s.tasks.by_status.done} of ${s.tasks.total} tasks done` : undefined}
        />
        <MetricCard
          label="Hours logged"
          icon={<Clock size={18} />}
          loading={loading}
          value={s ? `${formatNumber(s.hours.logged, { maximumFractionDigits: 1 })} h` : '—'}
          footer={s ? (Number(s.hours.estimated) > 0 ? `of ${formatNumber(s.hours.estimated, { maximumFractionDigits: 1 })} h estimated` : 'No estimates yet') : undefined}
        />
        <MetricCard
          label="Budget used"
          icon={<Wallet size={18} />}
          loading={loading}
          value={s?.budget.percent_used !== null && s?.budget.percent_used !== undefined ? formatPercent(s.budget.percent_used, { fractionDigits: 0 }) : '—'}
          unavailable={!loading && !s?.budget.budget}
          unavailableText="No budget set"
          footer={s ? `${money(s.budget.spent)} spent` : undefined}
        />
        <MetricCard
          label="Overdue tasks"
          icon={<AlertTriangle size={18} />}
          loading={loading}
          value={s?.tasks.overdue_count ?? 0}
          footer={s ? `${s.milestones.completed}/${s.milestones.total} milestones done` : undefined}
        />
      </section>

      <div className={styles.grid2}>
        <ChartCard
          title="Tasks by status"
          loading={loading}
          empty={!loading && s!.tasks.total === 0}
          emptyText="No tasks yet"
          legend={statusData.map((d) => ({ label: `${d.name} (${d.value})`, color: d.color }))}
          height={240}
          table={
            <table className={crud.lines}>
              <thead><tr><th>Status</th><th className={crud.r}>Tasks</th></tr></thead>
              <tbody>{statusData.map((d) => <tr key={d.status}><td>{d.name}</td><td className={crud.r}>{d.value}</td></tr>)}</tbody>
            </table>
          }
        >
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie data={statusData.filter((d) => d.value > 0)} dataKey="value" nameKey="name" innerRadius="58%" outerRadius="85%" paddingAngle={2} stroke="var(--color-surface)" isAnimationActive={false}>
                {statusData.filter((d) => d.value > 0).map((d) => <Cell key={d.status} fill={d.color} />)}
              </Pie>
              <Tooltip content={<ChartTooltip formatValue={(v) => `${v} task${v === 1 ? '' : 's'}`} />} />
            </PieChart>
          </ResponsiveContainer>
        </ChartCard>

        <Card>
          <CardHeader title="Effort & budget" />
          <CardBody>
            {loading ? <Skeleton lines={6} /> : (
              <div className={styles.stack}>
                <div className={styles.stackSm}>
                  <div className={styles.kv}><span>Task completion</span><strong>{s!.tasks.by_status.done}/{s!.tasks.total}</strong></div>
                  <Progress value={s!.tasks.percent_complete} label="Tasks complete" color={project.color} />
                </div>
                <div className={styles.stackSm}>
                  <div className={styles.kv}><span>Hours vs estimate</span><strong>{formatNumber(s!.hours.logged)} / {formatNumber(s!.hours.estimated)} h</strong></div>
                  <Progress value={s!.hours.percent_of_estimate} label="Hours logged against estimate" overflowDanger />
                  {Number(s!.hours.remaining) < 0 && <span className={styles.note}>{formatNumber(-Number(s!.hours.remaining))} h over estimate</span>}
                </div>
                <div className={styles.stackSm}>
                  <div className={styles.kv}><span>Budget vs spend</span><strong>{money(s!.budget.spent)} / {s!.budget.budget ? money(s!.budget.budget) : 'no budget'}</strong></div>
                  {s!.budget.budget && <Progress value={s!.budget.percent_used} label="Budget used" overflowDanger />}
                  <span className={styles.note}>
                    Expenses {money(s!.budget.expenses)} · billable time {money(s!.budget.billable_time_cost)}
                    {s!.budget.remaining !== null ? ` · remaining ${money(s!.budget.remaining)}` : ''}
                  </span>
                </div>
              </div>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="Milestones" actions={<Button variant="link" size="sm" onClick={() => onShowTab('milestones')}>Manage</Button>} />
          <CardBody>
            {loading ? <Skeleton lines={4} /> : s!.milestones.items.length === 0 ? (
              <EmptyState size="sm" icon={<Flag size={20} />} title="No milestones" />
            ) : (
              <div>
                {s!.milestones.items.map((m) => {
                  const late = m.status !== 'completed' && !!m.due_date && m.due_date < todayIso();
                  return (
                    <div key={m.id} className={styles.milestoneRow}>
                      <div className={styles.row}>
                        {m.status === 'completed' ? <CheckCircle2 size={14} color="var(--color-success)" aria-label="Completed" /> : <Flag size={14} aria-hidden />}
                        <strong style={{ flex: 1, minWidth: 0 }}>{m.name}</strong>
                        <span className={cn(styles.note, late && styles.danger)}>{m.due_date ? formatDate(m.due_date) : 'No date'}</span>
                      </div>
                      <Progress size="sm" value={m.percent_complete} label={`${m.name} progress`} />
                      <span className={styles.note}>{m.tasks_done}/{m.tasks_total} tasks</span>
                    </div>
                  );
                })}
              </div>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="Overdue tasks" description={s && s.tasks.overdue_count > s.tasks.overdue.length ? `Showing ${s.tasks.overdue.length} of ${s.tasks.overdue_count}` : undefined} />
          <CardBody>
            {loading ? <Skeleton lines={4} /> : s!.tasks.overdue.length === 0 ? (
              <EmptyState size="sm" icon={<CheckCircle2 size={20} />} title="Nothing overdue" />
            ) : (
              <ul className={styles.overdueList}>
                {s!.tasks.overdue.map((t) => (
                  <li key={t.id}>
                    <button type="button" className={styles.linkBtn} onClick={() => onOpenTask(t.id)}>{t.title}</button>
                    <TaskStatusBadge status={t.status} />
                    <span className={styles.danger} style={{ fontSize: 12 }}>{formatDate(t.due_date, 'dd MMM')}</span>
                    {t.assignee && <Avatar name={t.assignee.name} src={t.assignee.avatar_url} size="xs" title={t.assignee.name} />}
                  </li>
                ))}
              </ul>
            )}
          </CardBody>
        </Card>
      </div>

      <Card>
        <CardHeader title="Details" />
        <CardBody>
          {project.description && <p style={{ marginTop: 0, whiteSpace: 'pre-wrap' }}>{project.description}</p>}
          <dl className={crud.dl}>
            <dt>Number</dt><dd className={crud.mono}>{project.number}{project.code ? ` · ${project.code}` : ''}</dd>
            <dt>Manager</dt><dd>{project.manager?.name ?? '—'}</dd>
            <dt>Priority</dt><dd>{project.priority ? <PriorityBadge priority={project.priority} /> : '—'}</dd>
            <dt>Dates</dt><dd>{formatDate(project.start_date)} → {formatDate(project.due_date)}</dd>
            <dt>Department</dt><dd>{project.department?.name ?? '—'}</dd>
            <dt>Branch</dt><dd>{project.branch?.name ?? 'All branches'}</dd>
            <dt>Customer</dt><dd>{project.customer_id ? <Link href={`/sales/customers/${project.customer_id}`}>Customer #{project.customer_id}</Link> : 'Internal'}</dd>
            <dt>Budget</dt><dd>{project.budget ? formatMoney(project.budget, project.currency) : '—'} {project.is_billable && <Badge size="sm" variant="success">Billable</Badge>}</dd>
            {project.completed_at && (<><dt>Completed</dt><dd>{formatDate(project.completed_at)}</dd></>)}
          </dl>
        </CardBody>
      </Card>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Milestones
// ---------------------------------------------------------------------------

export function MilestonesPanel({ projectId, canManage }: { projectId: number; canManage: boolean }) {
  const q = useMilestones(projectId);
  const summary = usePmQuery(['summary', projectId], () => projectsApi.summary(projectId));
  const [editing, setEditing] = useState<Milestone | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Milestone | null>(null);
  const setStatus = usePmMutation(({ id, status }: { id: number; status: 'pending' | 'completed' }) => projectsApi.updateMilestone(id, { status }), {
    success: (m: Milestone) => (m.status === 'completed' ? `“${m.name}” completed` : `“${m.name}” reopened`),
    errorTitle: 'Could not update milestone',
    toastOn422: true,
  });
  const del = usePmMutation((id: number) => projectsApi.deleteMilestone(id), { success: 'Milestone deleted', errorTitle: 'Could not delete milestone' });

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  const rows = q.data ?? [];
  const progress = new Map((summary.data?.milestones.items ?? []).map((m) => [m.id, m]));
  const today = todayIso();

  return (
    <div className={styles.stack}>
      <div className={styles.row}>
        <p className={styles.note} style={{ flex: 1 }}>Milestones group tasks into deliverables. Deleting a milestone keeps its tasks.</p>
        {canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New milestone</Button>}
      </div>
      {q.isLoading ? <Skeleton lines={5} /> : rows.length === 0 ? (
        <EmptyState icon={<Flag size={22} />} title="No milestones yet" action={canManage ? <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>Add a milestone</Button> : undefined} />
      ) : (
        <Card>
          <CardBody>
            {rows.map((m) => {
              const p = progress.get(m.id);
              const late = m.status !== 'completed' && !!m.due_date && m.due_date < today;
              return (
                <div key={m.id} className={styles.milestoneRow}>
                  <div className={styles.row}>
                    {m.status === 'completed' ? <CheckCircle2 size={16} color="var(--color-success)" aria-hidden /> : <Flag size={16} aria-hidden />}
                    <strong style={{ flex: 1, minWidth: 0 }}>{m.name}</strong>
                    <Badge size="sm" variant={m.status === 'completed' ? 'success' : late ? 'danger' : 'neutral'}>{m.status === 'completed' ? 'Completed' : late ? 'Late' : 'Pending'}</Badge>
                    <span className={cn(styles.note, late && styles.danger)}>{m.due_date ? `Due ${formatDate(m.due_date)}` : 'No due date'}</span>
                    {canManage && (
                      <Menu align="end" trigger={<IconButton size="sm" variant="ghost" icon={<MoreHorizontal size={14} />} aria-label={`Actions for ${m.name}`} />}>
                        {m.status === 'completed' ? (
                          <MenuItem icon={<RotateCcw size={15} />} onSelect={() => setStatus.mutate({ id: m.id, status: 'pending' })}>Reopen</MenuItem>
                        ) : (
                          <MenuItem icon={<CheckCircle2 size={15} />} onSelect={() => setStatus.mutate({ id: m.id, status: 'completed' })}>Mark complete</MenuItem>
                        )}
                        <MenuItem icon={<Pencil size={15} />} onSelect={() => setEditing(m)}>Edit</MenuItem>
                        <MenuSeparator />
                        <MenuItem icon={<Trash2 size={15} />} destructive onSelect={() => setDeleting(m)}>Delete</MenuItem>
                      </Menu>
                    )}
                  </div>
                  {m.description && <span className={styles.note}>{m.description}</span>}
                  <Progress size="sm" value={p?.percent_complete ?? 0} label={`${m.name} progress`} />
                  <span className={styles.note}>{p ? `${p.tasks_done}/${p.tasks_total} tasks done` : `${m.tasks_count ?? 0} tasks`}{m.completed_at ? ` · completed ${formatDate(m.completed_at)}` : ''}</span>
                </div>
              );
            })}
          </CardBody>
        </Card>
      )}

      {editing && (
        <MilestoneModal key={editing === 'new' ? 'new' : editing.id} projectId={projectId} milestone={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      )}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete milestone “${deleting?.name}”?`}
        description="Its tasks are kept and simply unlinked from the milestone."
        destructive
        confirmLabel="Delete milestone"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </div>
  );
}

function MilestoneModal({ projectId, milestone, onClose }: { projectId: number; milestone: Milestone | null; onClose: () => void }) {
  const [name, setName] = useState(milestone?.name ?? '');
  const [description, setDescription] = useState(milestone?.description ?? '');
  const [due, setDue] = useState(milestone?.due_date ?? '');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const save = usePmMutation(
    (body: MilestoneInput) => (milestone ? projectsApi.updateMilestone(milestone.id, body) : projectsApi.createMilestone(projectId, body)),
    { success: milestone ? 'Milestone saved' : 'Milestone created', errorTitle: 'Could not save milestone' },
  );
  const submit = async () => {
    setErrors({});
    setMessage(null);
    if (!name.trim()) {
      setErrors({ name: 'Name is required' });
      return;
    }
    try {
      await save.mutateAsync({ name: name.trim(), description: description.trim() || null, due_date: due || null });
      onClose();
    } catch (e) {
      const err = e as ApiError;
      if (err.status === 422 && err.errors) setErrors(Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, v[0] ?? 'Invalid'])));
      else setMessage(err.message);
    }
  };
  return (
    <Modal
      open
      onClose={onClose}
      size="sm"
      title={milestone ? 'Edit milestone' : 'New milestone'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button onClick={() => void submit()} loading={save.isPending}>{milestone ? 'Save' : 'Create'}</Button>
        </>
      }
    >
      <form className={styles.stack} onSubmit={(e) => { e.preventDefault(); void submit(); }}>
        {message && <Alert variant="danger">{message}</Alert>}
        <FormField label="Name" required error={errors.name}>
          <Input value={name} maxLength={150} onChange={(e) => setName(e.target.value)} autoFocus />
        </FormField>
        <FormField label="Due date" error={errors.due_date}>
          <DateInput value={due} onValueChange={(v) => setDue(v ?? '')} />
        </FormField>
        <FormField label="Description" error={errors.description}>
          <Textarea rows={3} maxLength={1000} value={description} onChange={(e) => setDescription(e.target.value)} />
        </FormField>
        <button type="submit" hidden />
      </form>
    </Modal>
  );
}

// ---------------------------------------------------------------------------
// Members
// ---------------------------------------------------------------------------

export function MembersPanel({ project, canManage }: { project: Project; canManage: boolean }) {
  const members = project.members ?? [];
  const people = usePeople();
  const [userId, setUserId] = useState('');
  const [role, setRole] = useState<MemberRole>('member');
  const [addError, setAddError] = useState<string | null>(null);
  const [removing, setRemoving] = useState<ProjectMember | null>(null);
  const add = usePmMutation((body: { user_id: number; role: MemberRole }) => projectsApi.addMember(project.id, body), {
    success: (m: ProjectMember) => `${m.user?.name ?? 'Member'} added`,
    errorTitle: 'Could not add member',
    onSuccess: () => setUserId(''),
  });
  const changeRole = usePmMutation(({ id, r }: { id: number; r: MemberRole }) => projectsApi.updateMember(project.id, id, r), {
    success: 'Role updated',
    errorTitle: 'Could not change role',
    toastOn422: true,
  });
  const remove = usePmMutation((id: number) => projectsApi.removeMember(project.id, id), { success: 'Member removed', errorTitle: 'Could not remove member' });

  const memberIds = new Set(members.map((m) => m.user_id));
  const candidates = people.options.filter((o) => !memberIds.has(Number(o.value)));

  const submit = async () => {
    setAddError(null);
    if (!userId) {
      setAddError('Choose a person to add.');
      return;
    }
    try {
      await add.mutateAsync({ user_id: Number(userId), role });
    } catch (e) {
      const err = e as ApiError;
      if (err.status === 422) setAddError(err.errors?.user_id?.[0] ?? err.errors?.role?.[0] ?? err.message);
    }
  };

  return (
    <div className={styles.stack}>
      {canManage && (
        <Card>
          <CardHeader title="Add a member" description="Members with task access can work on this project’s tasks; viewers can only see it." />
          <CardBody>
            <div className={styles.row} style={{ alignItems: 'flex-end' }}>
              <FormField label="Person" error={addError ?? undefined} hint={people.limited ? 'The company directory isn’t available to you, so only you are listed.' : undefined}>
                <Select value={userId} onChange={(e) => setUserId(e.target.value)} placeholder={people.isLoading ? 'Loading…' : 'Select a person'} options={candidates} style={{ minWidth: 220 }} />
              </FormField>
              <FormField label="Role">
                <Select value={role} onChange={(e) => setRole(e.target.value as MemberRole)} options={enumOptions(MEMBER_ROLES)} />
              </FormField>
              <Button leftIcon={<UserPlus size={16} />} loading={add.isPending} onClick={() => void submit()}>Add</Button>
            </div>
          </CardBody>
        </Card>
      )}
      <Card>
        <CardHeader title={`Members (${members.length})`} />
        <CardBody>
          {members.length === 0 ? (
            <EmptyState size="sm" icon={<Users size={20} />} title="No members" />
          ) : (
            members.map((m) => {
              const isManager = m.user_id === project.manager_id;
              return (
                <div key={m.id} className={styles.memberRow}>
                  <Avatar name={m.user?.name} src={m.user?.avatar_url} size="sm" />
                  <div>
                    <strong>{m.user?.name ?? `User #${m.user_id}`}</strong>
                    <span>{isManager ? 'Project manager' : `Joined ${formatDate(m.created_at)}`}</span>
                  </div>
                  {canManage ? (
                    <Select
                      size="sm"
                      aria-label={`Role for ${m.user?.name ?? 'member'}`}
                      value={m.role}
                      disabled={changeRole.isPending && changeRole.variables?.id === m.id}
                      onChange={(e) => changeRole.mutate({ id: m.id, r: e.target.value as MemberRole })}
                      options={enumOptions(MEMBER_ROLES)}
                    />
                  ) : (
                    <Badge size="sm" variant={m.role === 'manager' ? 'primary' : 'neutral'}>{humanize(m.role)}</Badge>
                  )}
                  {canManage && (
                    <IconButton
                      size="sm"
                      variant="ghost"
                      icon={<Trash2 size={15} />}
                      aria-label={`Remove ${m.user?.name ?? 'member'}`}
                      disabled={isManager}
                      title={isManager ? 'Change the project manager before removing them' : undefined}
                      onClick={() => setRemoving(m)}
                    />
                  )}
                </div>
              );
            })
          )}
        </CardBody>
      </Card>
      <ConfirmDialog
        open={removing !== null}
        onClose={() => setRemoving(null)}
        title={`Remove ${removing?.user?.name ?? 'this member'}?`}
        description="They lose access to the project’s tasks unless they hold a company-wide projects permission."
        destructive
        confirmLabel="Remove member"
        onConfirm={async () => {
          if (removing) await remove.mutateAsync(removing.id).catch(() => undefined);
          setRemoving(null);
        }}
      />
    </div>
  );
}
