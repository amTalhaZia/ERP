'use client';

import { useState, type DragEvent } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { ArrowDown, ArrowDownToLine, ArrowUp, ArrowUpToLine, CalendarClock, CheckSquare, KanbanSquare, MessageSquare, MoreHorizontal, Plus } from 'lucide-react';
import {
  Avatar, Badge, EmptyState, ErrorState, IconButton, Menu, MenuItem, MenuLabel, MenuSeparator, Select, Skeleton, useToast,
} from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/cn';
import {
  PROJECTS_KEY, TASK_STATUS_LABELS, projectsApi, useCompanyId, usePmQuery,
  type Board, type BoardColumn, type BoardTask, type Milestone, type ProjectMember, type TaskStatus,
} from './api';
import { PriorityBadge, TASK_STATUS_COLORS } from './shared';
import styles from './projects.module.scss';

interface DropTarget {
  status: TaskStatus;
  /** Insertion index among the column's tasks, excluding the dragged task. */
  index: number;
}

/** Pure optimistic move: take the task out and insert it at `index` of `status`, renumbering positions. */
export function moveOnBoard(columns: BoardColumn[], taskId: number, status: TaskStatus, index: number): BoardColumn[] {
  const task = columns.flatMap((c) => c.tasks).find((t) => t.id === taskId);
  if (!task) return columns;
  return columns.map((c) => {
    let tasks = c.tasks.filter((t) => t.id !== taskId);
    if (c.status === status) {
      const at = Math.max(0, Math.min(index, tasks.length));
      tasks = [...tasks.slice(0, at), { ...task, status, is_overdue: status === 'done' ? false : task.is_overdue }, ...tasks.slice(at)];
    }
    return tasks === c.tasks ? c : { ...c, tasks: tasks.map((t, i) => (t.position === i ? t : { ...t, position: i })) };
  });
}

export function TaskBoard({
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
  onNewTask: (status: TaskStatus) => void;
}) {
  const toast = useToast();
  const qc = useQueryClient();
  const companyId = useCompanyId();
  const [assigneeId, setAssigneeId] = useState('');
  const [milestoneId, setMilestoneId] = useState('');
  const params: { assignee_id?: number; milestone_id?: number } = {};
  if (assigneeId) params.assignee_id = Number(assigneeId);
  if (milestoneId) params.milestone_id = Number(milestoneId);
  const boardKey = [companyId, PROJECTS_KEY, 'board', projectId, params];
  const board = usePmQuery(['board', projectId, params], () => projectsApi.board(projectId, params));

  const [dragId, setDragId] = useState<number | null>(null);
  const [target, setTarget] = useState<DropTarget | null>(null);
  const [announce, setAnnounce] = useState('');

  const move = useMutation<unknown, ApiError, { task: BoardTask; status: TaskStatus; index: number }, { previous?: Board }>({
    mutationFn: ({ task, status, index }) => projectsApi.moveTask(task.id, { status, position: index }),
    onMutate: async ({ task, status, index }) => {
      await qc.cancelQueries({ queryKey: boardKey });
      const previous = qc.getQueryData<Board>(boardKey);
      if (previous) qc.setQueryData<Board>(boardKey, { ...previous, columns: moveOnBoard(previous.columns, task.id, status, index) });
      setAnnounce(task.status === status ? `Moved ${task.title} to position ${index + 1} in ${TASK_STATUS_LABELS[status]}.` : `Moved ${task.title} to ${TASK_STATUS_LABELS[status]}.`);
      return { previous };
    },
    onError: (e, { task }, ctx) => {
      if (ctx?.previous) qc.setQueryData(boardKey, ctx.previous);
      setAnnounce(`Could not move ${task.title}; it was returned to its place.`);
      toast.error(`Could not move “${task.title}”`, e.status === 422 ? Object.values(e.errors ?? {})[0]?.[0] ?? e.message : e.message);
    },
    onSettled: () => {
      void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes(PROJECTS_KEY) });
    },
  });

  const columns = board.data?.columns ?? [];
  const findTask = (id: number) => columns.flatMap((c) => c.tasks).find((t) => t.id === id);

  const requestMove = (task: BoardTask, status: TaskStatus, index: number) => {
    const col = columns.find((c) => c.status === task.status);
    const current = col ? col.tasks.findIndex((t) => t.id === task.id) : -1;
    if (task.status === status && current === index) return;
    move.mutate({ task, status, index });
  };

  const endDrag = () => {
    setDragId(null);
    setTarget(null);
  };

  const onDrop = (e: DragEvent<HTMLElement>, status: TaskStatus) => {
    e.preventDefault();
    const id = Number(e.dataTransfer.getData('text/plain')) || dragId;
    const t = target && target.status === status ? target : null;
    endDrag();
    const task = id ? findTask(id) : undefined;
    if (!task) return;
    const col = columns.find((c) => c.status === status);
    const othersCount = col ? col.tasks.filter((x) => x.id !== task.id).length : 0;
    requestMove(task, status, t ? t.index : othersCount);
  };

  const memberOptions = members.filter((m) => m.user).map((m) => ({ value: String(m.user_id), label: m.user!.name }));
  const today = new Date().toISOString().slice(0, 10);

  return (
    <div className={styles.stack}>
      <div className={styles.toolbar}>
        <Select size="sm" aria-label="Assignee" placeholder="All assignees" value={assigneeId} onChange={(e) => setAssigneeId(e.target.value)} options={memberOptions} />
        {milestones.length > 0 && (
          <Select size="sm" aria-label="Milestone" placeholder="All milestones" value={milestoneId} onChange={(e) => setMilestoneId(e.target.value)} options={milestones.map((m) => ({ value: String(m.id), label: m.name }))} />
        )}
        {canWork && <span className={styles.note}>Drag cards between columns or reorder them; each card’s menu moves it with the keyboard.</span>}
      </div>

      <div className={styles.srOnly} aria-live="polite" role="status">{announce}</div>

      {board.error ? (
        <ErrorState error={board.error} onRetry={() => void board.refetch()} />
      ) : board.isLoading ? (
        <div className={styles.board}>
          {[0, 1, 2, 3, 4].map((i) => <Skeleton key={i} style={{ flex: '0 0 280px', height: 360 }} />)}
        </div>
      ) : columns.length === 0 ? (
        <EmptyState icon={<KanbanSquare size={22} />} title="No board columns" />
      ) : (
        <div className={styles.board} role="list" aria-label="Task board">
          {columns.map((col) => {
            const others = col.tasks.filter((t) => t.id !== dragId);
            const over = target?.status === col.status;
            const targetIndex = over && target ? target.index : -1;
            return (
              <section
                key={col.status}
                role="listitem"
                aria-labelledby={`col-${col.status}`}
                className={cn(styles.column, over && styles.columnOver)}
                onDragOver={(e) => {
                  if (dragId === null) return;
                  e.preventDefault();
                  e.dataTransfer.dropEffect = 'move';
                  // Hovering empty column space → append at the end.
                  if (!over) setTarget({ status: col.status, index: others.length });
                }}
                onDragLeave={(e) => {
                  if (!e.currentTarget.contains(e.relatedTarget as Node | null)) setTarget((t) => (t?.status === col.status ? null : t));
                }}
                onDrop={(e) => onDrop(e, col.status)}
              >
                <header className={styles.columnHeader}>
                  <h3 id={`col-${col.status}`}>
                    <span className={styles.dot} style={{ background: TASK_STATUS_COLORS[col.status] }} aria-hidden />
                    <span className={styles.columnName}>{col.label}</span>
                    <span className={styles.count} aria-label={`${col.tasks.length} tasks`}>{col.tasks.length}</span>
                    {canWork && <IconButton icon={<Plus size={14} />} size="sm" aria-label={`Add task to ${col.label}`} onClick={() => onNewTask(col.status)} />}
                  </h3>
                </header>
                <ul className={styles.cards2} aria-label={`${col.label} tasks`}>
                  {col.tasks.length === 0 && <li className={styles.emptyColumn}>{canWork ? 'Drop tasks here' : 'No tasks'}</li>}
                  {col.tasks.map((t, i) => {
                    const overdue = t.is_overdue || (t.status !== 'done' && !!t.due_date && t.due_date < today);
                    const dropBefore = over && dragId !== t.id && others[targetIndex]?.id === t.id;
                    const dropAfter = over && dragId !== t.id && targetIndex === others.length && others[others.length - 1]?.id === t.id;
                    const otherStatuses = columns.filter((c) => c.status !== t.status);
                    return (
                      <li
                        key={t.id}
                        className={cn(
                          styles.card,
                          dragId === t.id && styles.dragging,
                          move.isPending && move.variables?.task.id === t.id && styles.pending,
                          dropBefore && styles.dropBefore,
                          dropAfter && styles.dropAfter,
                        )}
                        draggable={canWork}
                        onDragStart={(e) => {
                          e.dataTransfer.setData('text/plain', String(t.id));
                          e.dataTransfer.effectAllowed = 'move';
                          setDragId(t.id);
                        }}
                        onDragEnd={endDrag}
                        onDragOver={(e) => {
                          if (dragId === null) return;
                          e.preventDefault();
                          e.stopPropagation();
                          e.dataTransfer.dropEffect = 'move';
                          if (dragId === t.id) return;
                          const rect = e.currentTarget.getBoundingClientRect();
                          const idx = others.findIndex((x) => x.id === t.id);
                          const index = e.clientY < rect.top + rect.height / 2 ? idx : idx + 1;
                          if (target?.status !== col.status || target.index !== index) setTarget({ status: col.status, index });
                        }}
                      >
                        <div className={styles.cardTop}>
                          <button type="button" className={styles.cardTitle} onClick={() => onOpenTask(t.id)}>{t.title}</button>
                          {canWork && (
                            <Menu trigger={<IconButton icon={<MoreHorizontal size={14} />} size="sm" aria-label={`Move ${t.title}`} />} align="end">
                              <MenuLabel>Move to…</MenuLabel>
                              {otherStatuses.map((c) => (
                                <MenuItem key={c.status} onSelect={() => requestMove(t, c.status, c.tasks.length)}>{c.label}</MenuItem>
                              ))}
                              <MenuSeparator />
                              <MenuLabel>Reorder</MenuLabel>
                              <MenuItem icon={<ArrowUpToLine size={14} />} disabled={i === 0} onSelect={() => requestMove(t, t.status, 0)}>Move to top</MenuItem>
                              <MenuItem icon={<ArrowUp size={14} />} disabled={i === 0} onSelect={() => requestMove(t, t.status, i - 1)}>Move up</MenuItem>
                              <MenuItem icon={<ArrowDown size={14} />} disabled={i === col.tasks.length - 1} onSelect={() => requestMove(t, t.status, i + 1)}>Move down</MenuItem>
                              <MenuItem icon={<ArrowDownToLine size={14} />} disabled={i === col.tasks.length - 1} onSelect={() => requestMove(t, t.status, col.tasks.length - 1)}>Move to bottom</MenuItem>
                            </Menu>
                          )}
                        </div>
                        {(t.priority || t.labels.length > 0) && (
                          <div className={styles.cardLabels}>
                            <PriorityBadge priority={t.priority} />
                            {t.labels.slice(0, 3).map((l) => <Badge key={l} size="sm" variant="neutral" outline>{l}</Badge>)}
                            {t.labels.length > 3 && <Badge size="sm" variant="neutral" outline>+{t.labels.length - 3}</Badge>}
                          </div>
                        )}
                        <div className={styles.cardMeta}>
                          {t.due_date && (
                            <span className={cn(overdue && styles.danger)} title="Due date">
                              <CalendarClock size={12} aria-hidden /> {formatDate(t.due_date, 'dd MMM')}
                              {overdue && <span className={styles.srOnly}> (overdue)</span>}
                            </span>
                          )}
                          {t.subtasks_count > 0 && (
                            <span title={`${t.subtasks_count} subtasks`}><CheckSquare size={12} aria-hidden /> {t.subtasks_count}<span className={styles.srOnly}> subtasks</span></span>
                          )}
                          {t.comments_count > 0 && (
                            <span title={`${t.comments_count} comments`}><MessageSquare size={12} aria-hidden /> {t.comments_count}<span className={styles.srOnly}> comments</span></span>
                          )}
                          <span className={styles.cardAssignee} title={t.assignee ? `Assignee: ${t.assignee.name}` : 'Unassigned'}>
                            {t.assignee ? <Avatar name={t.assignee.name} src={t.assignee.avatar_url} size="xs" /> : <span className={styles.muted}>Unassigned</span>}
                          </span>
                        </div>
                      </li>
                    );
                  })}
                </ul>
              </section>
            );
          })}
        </div>
      )}
    </div>
  );
}
