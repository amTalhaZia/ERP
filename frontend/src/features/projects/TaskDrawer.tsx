'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { CornerLeftUp, Pencil, Plus, Send, Trash2 } from 'lucide-react';
import {
  Alert, Avatar, Badge, Button, Checkbox, ConfirmDialog, Divider, Drawer, EmptyState, ErrorState, IconButton, Input, Skeleton, Textarea,
} from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime, formatRelative } from '@/lib/format';
import { LinkedDocuments } from '@/features/documents/components';
import {
  projectsApi, usePmMutation, usePmQuery,
  type Milestone, type ProjectMember, type Task, type TaskComment, type TaskInput,
} from './api';
import { PriorityBadge, TaskStatusBadge } from './shared';
import { TASK_FIELDS, TaskFields, taskSchema, taskToValues, valuesToTaskInput, type TaskValues } from './TaskForm';
import styles from './projects.module.scss';

interface Props {
  taskId: number;
  open: boolean;
  members: ProjectMember[];
  milestones: Milestone[];
  canWork: boolean;
  meId: number | undefined;
  onClose: () => void;
  onOpenTask: (id: number) => void;
}

export function TaskDrawer({ taskId, open, members, milestones, canWork, meId, onClose, onOpenTask }: Props) {
  const q = usePmQuery(['task', taskId], () => projectsApi.task(taskId));
  const [confirmDelete, setConfirmDelete] = useState(false);
  const del = usePmMutation(() => projectsApi.deleteTask(taskId), { success: 'Task deleted', errorTitle: 'Could not delete task', onSuccess: onClose });
  const t = q.data;

  return (
    <Drawer
      open={open}
      onClose={onClose}
      size="lg"
      title={t ? t.title : 'Task'}
      description={
        t ? (
          <span className={styles.row}>
            <TaskStatusBadge status={t.status} />
            <PriorityBadge priority={t.priority} />
            {t.project && <span className={styles.muted}>{t.project.number} · {t.project.name}</span>}
          </span>
        ) : undefined
      }
      footer={
        t && (
          <>
            {canWork && <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirmDelete(true)}>Delete</Button>}
            <span className={styles.spacer} />
            <Button variant="secondary" onClick={onClose}>Close</Button>
            {canWork && <Button type="submit" form={`task-form-${t.id}`}>Save changes</Button>}
          </>
        )
      }
    >
      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : !t ? (
        <Skeleton lines={10} />
      ) : (
        <div className={styles.stack}>
          {t.parent_id && (
            <Button variant="link" size="sm" leftIcon={<CornerLeftUp size={14} />} onClick={() => onOpenTask(t.parent_id!)} style={{ alignSelf: 'flex-start' }}>
              Back to parent task
            </Button>
          )}
          <p className={styles.activity}>
            Created {formatRelative(t.created_at)}{t.reporter ? ` by ${t.reporter.name}` : ''} · updated {formatRelative(t.updated_at)}
            {t.completed_at ? ` · completed ${formatDateTime(t.completed_at)}` : ''}
          </p>
          <TaskEditForm key={t.id} task={t} members={members} milestones={milestones} canWork={canWork} />

          {!t.parent_id && (
            <>
              <Divider />
              <Subtasks task={t} canWork={canWork} onOpenTask={onOpenTask} />
            </>
          )}

          <Divider />
          <LinkedDocuments type="task" id={t.id} title="Attachments" emptyText="No attachments yet." compact />

          <Divider />
          <Comments taskId={t.id} meId={meId} />
        </div>
      )}

      <ConfirmDialog
        open={confirmDelete}
        onClose={() => setConfirmDelete(false)}
        title={`Delete “${t?.title}”?`}
        description="Subtasks and comments are deleted too. Logged time is kept but no longer linked to the task."
        destructive
        confirmLabel="Delete task"
        onConfirm={async () => {
          await del.mutateAsync(undefined).catch(() => undefined);
          setConfirmDelete(false);
        }}
      />
    </Drawer>
  );
}

function TaskEditForm({ task, members, milestones, canWork }: { task: Task; members: ProjectMember[]; milestones: Milestone[]; canWork: boolean }) {
  const [formError, setFormError] = useState<string | null>(null);
  const form = useForm<TaskValues>({ resolver: zodResolver(taskSchema), defaultValues: taskToValues(task) });
  const save = usePmMutation((body: TaskInput) => projectsApi.updateTask(task.id, body), { success: 'Task saved', errorTitle: 'Could not save task' });

  const onSubmit = async (v: TaskValues) => {
    setFormError(null);
    try {
      const saved = await save.mutateAsync(valuesToTaskInput(v));
      form.reset(taskToValues(saved));
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, form.setError as never, TASK_FIELDS));
    }
  };

  return (
    <form id={`task-form-${task.id}`} onSubmit={form.handleSubmit(onSubmit)} noValidate>
      {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
      {!canWork && <Alert variant="info" style={{ marginBottom: 16 }}>You can view this task and comment on it, but only project members with task access can edit it.</Alert>}
      <fieldset disabled={!canWork} style={{ border: 0, margin: 0, padding: 0, minWidth: 0 }}>
        <TaskFields register={form.register} control={form.control} errors={form.formState.errors} members={members} milestones={milestones} currentAssignee={task.assignee} />
      </fieldset>
      {task.labels.length > 0 && (
        <div className={styles.labelChips} style={{ marginTop: 12 }} aria-label="Labels">
          {task.labels.map((l) => <Badge key={l} size="sm" variant="neutral" outline>{l}</Badge>)}
        </div>
      )}
    </form>
  );
}

function Subtasks({ task, canWork, onOpenTask }: { task: Task; canWork: boolean; onOpenTask: (id: number) => void }) {
  const [title, setTitle] = useState('');
  const [error, setError] = useState<string | null>(null);
  const subtasks = task.subtasks ?? [];
  const done = subtasks.filter((s) => s.status === 'done').length;
  const add = usePmMutation((t: string) => projectsApi.createSubtask(task.id, { title: t }), {
    errorTitle: 'Could not add subtask',
    onSuccess: () => setTitle(''),
  });
  const toggle = usePmMutation(({ id, isDone }: { id: number; isDone: boolean }) => projectsApi.updateTask(id, { status: isDone ? 'done' : 'todo' }), {
    errorTitle: 'Could not update subtask',
    toastOn422: true,
  });

  const submit = async () => {
    setError(null);
    if (!title.trim()) return;
    try {
      await add.mutateAsync(title.trim());
    } catch (e) {
      const err = e as ApiError;
      setError(err.errors?.title?.[0] ?? (err.status === 422 ? Object.values(err.errors ?? {})[0]?.[0] ?? err.message : null));
    }
  };

  return (
    <section className={styles.stackSm} aria-labelledby={`subtasks-${task.id}`}>
      <h3 id={`subtasks-${task.id}`} className={styles.sectionTitle}>
        Subtasks {subtasks.length > 0 && <span className={styles.muted}>({done}/{subtasks.length})</span>}
      </h3>
      {subtasks.length === 0 ? (
        <p className={styles.note}>No subtasks.</p>
      ) : (
        <ul className={styles.subtasks}>
          {subtasks.map((s) => (
            <li key={s.id}>
              <Checkbox
                aria-label={`Mark “${s.title}” ${s.status === 'done' ? 'not done' : 'done'}`}
                checked={s.status === 'done'}
                disabled={!canWork || (toggle.isPending && toggle.variables?.id === s.id)}
                onChange={(e) => toggle.mutate({ id: s.id, isDone: e.target.checked })}
              />
              <button type="button" className={`${styles.linkBtn} ${s.status === 'done' ? styles.subDone : ''}`} onClick={() => onOpenTask(s.id)}>
                {s.title}
              </button>
              {s.assignee && <Avatar name={s.assignee.name} src={s.assignee.avatar_url} size="xs" title={s.assignee.name} />}
              {s.due_date && <span className={styles.note}>{formatDate(s.due_date, 'dd MMM')}</span>}
            </li>
          ))}
        </ul>
      )}
      {canWork && (
        <form
          className={styles.row}
          onSubmit={(e) => {
            e.preventDefault();
            void submit();
          }}
        >
          <Input size="sm" aria-label="New subtask title" placeholder="Add a subtask…" maxLength={200} value={title} onChange={(e) => setTitle(e.target.value)} style={{ flex: 1, minWidth: 160 }} invalid={!!error} />
          <Button size="sm" type="submit" variant="secondary" leftIcon={<Plus size={14} />} loading={add.isPending} disabled={!title.trim()}>Add</Button>
        </form>
      )}
      {error && <span role="alert" className={styles.danger} style={{ fontSize: 12 }}>{error}</span>}
    </section>
  );
}

function Comments({ taskId, meId }: { taskId: number; meId: number | undefined }) {
  const can = useCan();
  const q = usePmQuery(['comments', taskId], () => projectsApi.comments(taskId));
  const [body, setBody] = useState('');
  const [editing, setEditing] = useState<{ id: number; body: string } | null>(null);
  const [deleting, setDeleting] = useState<TaskComment | null>(null);
  const [error, setError] = useState<string | null>(null);
  const post = usePmMutation((b: string) => projectsApi.addComment(taskId, b), { errorTitle: 'Could not post comment', onSuccess: () => setBody('') });
  const edit = usePmMutation(({ id, b }: { id: number; b: string }) => projectsApi.updateComment(taskId, id, b), { errorTitle: 'Could not edit comment', toastOn422: true, onSuccess: () => setEditing(null) });
  const del = usePmMutation((id: number) => projectsApi.deleteComment(taskId, id), { success: 'Comment deleted', errorTitle: 'Could not delete comment' });

  const submit = async () => {
    setError(null);
    if (!body.trim()) return;
    try {
      await post.mutateAsync(body.trim());
    } catch (e) {
      const err = e as ApiError;
      if (err.status === 422) setError(err.errors?.body?.[0] ?? err.message);
    }
  };

  const comments = q.data?.data ?? [];
  return (
    <section className={styles.stackSm} aria-labelledby={`comments-${taskId}`}>
      <h3 id={`comments-${taskId}`} className={styles.sectionTitle}>
        Comments {comments.length > 0 && <span className={styles.muted}>({q.data?.meta.pagination.total ?? comments.length})</span>}
      </h3>
      {q.error ? (
        <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} />
      ) : q.isLoading ? (
        <Skeleton lines={3} />
      ) : comments.length === 0 ? (
        <EmptyState size="sm" title="No comments yet" description="Start the conversation below." />
      ) : (
        <ul className={styles.comments}>
          {comments.map((c) => {
            const mine = c.user_id === meId;
            return (
              <li key={c.id} className={styles.comment}>
                <Avatar name={c.user?.name} src={c.user?.avatar_url} size="sm" />
                <div className={styles.commentBody}>
                  <header>
                    <strong>{c.user?.name ?? 'Unknown user'}</strong>
                    <span title={formatDateTime(c.created_at)}>{formatRelative(c.created_at)}</span>
                    {c.edited_at && <span title={formatDateTime(c.edited_at)}>(edited)</span>}
                    <span className={styles.spacer} />
                    {mine && editing?.id !== c.id && <IconButton size="sm" variant="ghost" icon={<Pencil size={13} />} aria-label="Edit comment" onClick={() => setEditing({ id: c.id, body: c.body })} />}
                    {(mine || can('projects.manage')) && <IconButton size="sm" variant="ghost" icon={<Trash2 size={13} />} aria-label="Delete comment" onClick={() => setDeleting(c)} />}
                  </header>
                  {editing?.id === c.id ? (
                    <div className={styles.stackSm} style={{ marginTop: 8 }}>
                      <Textarea aria-label="Edit comment" rows={3} maxLength={5000} value={editing.body} onChange={(e) => setEditing({ id: c.id, body: e.target.value })} autoFocus />
                      <div className={styles.row}>
                        <Button size="sm" variant="secondary" onClick={() => setEditing(null)}>Cancel</Button>
                        <Button size="sm" loading={edit.isPending} disabled={!editing.body.trim()} onClick={() => edit.mutate({ id: c.id, b: editing.body.trim() })}>Save</Button>
                      </div>
                    </div>
                  ) : (
                    <p>{c.body}</p>
                  )}
                </div>
              </li>
            );
          })}
        </ul>
      )}
      <form
        className={styles.stackSm}
        onSubmit={(e) => {
          e.preventDefault();
          void submit();
        }}
      >
        <Textarea
          aria-label="Write a comment"
          placeholder="Write a comment… (Ctrl+Enter to send)"
          rows={3}
          maxLength={5000}
          value={body}
          invalid={!!error}
          onChange={(e) => setBody(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
              e.preventDefault();
              void submit();
            }
          }}
        />
        {error && <span role="alert" className={styles.danger} style={{ fontSize: 12 }}>{error}</span>}
        <div className={styles.row} style={{ justifyContent: 'flex-end' }}>
          <Button size="sm" type="submit" leftIcon={<Send size={14} />} loading={post.isPending} disabled={!body.trim()}>Comment</Button>
        </div>
      </form>

      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title="Delete this comment?"
        description="This can’t be undone."
        destructive
        confirmLabel="Delete comment"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </section>
  );
}
