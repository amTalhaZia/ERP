'use client';

import { useState } from 'react';
import { Controller, useForm, type Control, type FieldErrors, type UseFormRegister } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Alert, Button, DateInput, Drawer, FormField, FormGrid, Input, Select, Textarea } from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import {
  PRIORITIES, TASK_STATUSES, TASK_STATUS_LABELS, projectsApi, usePmMutation,
  type Milestone, type ProjectMember, type Task, type TaskInput, type TaskStatus, type UserSummary,
} from './api';
import { enumOptions, nullable, splitLabels } from './shared';

export const taskSchema = z
  .object({
    title: z.string().trim().min(1, 'Title is required').max(200),
    description: z.string().trim().max(20000),
    status: z.enum(TASK_STATUSES),
    priority: z.enum(PRIORITIES),
    assignee_id: z.string(),
    milestone_id: z.string(),
    start_date: z.string(),
    due_date: z.string(),
    estimate_hours: z.string().trim().regex(/^(\d{1,6}(\.\d{0,2})?)?$/, 'Hours with up to 2 decimals'),
    labels: z.string().max(1000),
  })
  .superRefine((v, ctx) => {
    if (v.start_date && v.due_date && v.due_date < v.start_date) ctx.addIssue({ code: 'custom', path: ['due_date'], message: 'Due date must be on or after the start date' });
    const labels = splitLabels(v.labels);
    if (labels.length > 20) ctx.addIssue({ code: 'custom', path: ['labels'], message: 'At most 20 labels' });
    if (labels.some((l) => l.length > 40)) ctx.addIssue({ code: 'custom', path: ['labels'], message: 'Labels can be at most 40 characters' });
  });
export type TaskValues = z.infer<typeof taskSchema>;
export const TASK_FIELDS = Object.keys(taskSchema.shape);

export const taskToValues = (t: Partial<Task> | null, status: TaskStatus = 'todo'): TaskValues => ({
  title: t?.title ?? '',
  description: t?.description ?? '',
  status: t?.status ?? status,
  priority: t?.priority ?? 'medium',
  assignee_id: t?.assignee_id ? String(t.assignee_id) : '',
  milestone_id: t?.milestone_id ? String(t.milestone_id) : '',
  start_date: t?.start_date ?? '',
  due_date: t?.due_date ?? '',
  estimate_hours: t?.estimate_hours ? String(Number(t.estimate_hours)) : '',
  labels: (t?.labels ?? []).join(', '),
});

export const valuesToTaskInput = (v: TaskValues): TaskInput => ({
  title: v.title,
  description: nullable(v.description),
  status: v.status,
  priority: v.priority,
  assignee_id: v.assignee_id ? Number(v.assignee_id) : null,
  milestone_id: v.milestone_id ? Number(v.milestone_id) : null,
  start_date: v.start_date || null,
  due_date: v.due_date || null,
  estimate_hours: v.estimate_hours || null,
  labels: splitLabels(v.labels),
});

/** Assignee options: project members, plus the current assignee if no longer a member. */
export function assigneeOptions(members: ProjectMember[], current?: UserSummary | null) {
  const opts = members.filter((m) => m.user && m.role !== 'viewer').map((m) => ({ value: String(m.user_id), label: m.user!.name }));
  if (current && !opts.some((o) => o.value === String(current.id))) opts.push({ value: String(current.id), label: current.name });
  return opts.sort((a, b) => a.label.localeCompare(b.label));
}

export function TaskFields({
  register,
  control,
  errors,
  members,
  milestones,
  currentAssignee,
  autoFocusTitle,
}: {
  register: UseFormRegister<TaskValues>;
  control: Control<TaskValues>;
  errors: FieldErrors<TaskValues>;
  members: ProjectMember[];
  milestones: Milestone[];
  currentAssignee?: UserSummary | null;
  autoFocusTitle?: boolean;
}) {
  const e = errors;
  return (
    <FormGrid columns={2}>
      <FormField label="Title" required span="full" error={e.title?.message}>
        <Input maxLength={200} {...register('title')} autoFocus={autoFocusTitle} />
      </FormField>
      <FormField label="Description" span="full" error={e.description?.message}>
        <Textarea rows={4} maxLength={20000} autoResize maxRows={14} {...register('description')} />
      </FormField>
      <FormField label="Status" error={e.status?.message}>
        <Select {...register('status')} options={enumOptions(TASK_STATUSES, TASK_STATUS_LABELS)} />
      </FormField>
      <FormField label="Priority" error={e.priority?.message}>
        <Select {...register('priority')} options={enumOptions(PRIORITIES)} />
      </FormField>
      <FormField label="Assignee" error={e.assignee_id?.message}>
        <Select {...register('assignee_id')} options={[{ value: '', label: 'Unassigned' }, ...assigneeOptions(members, currentAssignee)]} />
      </FormField>
      <FormField label="Milestone" error={e.milestone_id?.message}>
        <Select {...register('milestone_id')} options={[{ value: '', label: 'None' }, ...milestones.map((m) => ({ value: String(m.id), label: m.name }))]} />
      </FormField>
      <FormField label="Start date" error={e.start_date?.message}>
        {(p) => <Controller control={control} name="start_date" render={({ field }) => <DateInput {...p} value={field.value} onValueChange={(v) => field.onChange(v ?? '')} />} />}
      </FormField>
      <FormField label="Due date" error={e.due_date?.message}>
        {(p) => <Controller control={control} name="due_date" render={({ field }) => <DateInput {...p} value={field.value} onValueChange={(v) => field.onChange(v ?? '')} />} />}
      </FormField>
      <FormField label="Estimate (hours)" error={e.estimate_hours?.message}>
        <Input inputMode="decimal" {...register('estimate_hours')} />
      </FormField>
      <FormField label="Labels" error={e.labels?.message} hint="Comma-separated">
        <Input {...register('labels')} placeholder="frontend, bug" />
      </FormField>
    </FormGrid>
  );
}

export function NewTaskDrawer({
  open,
  projectId,
  status,
  members,
  milestones,
  onClose,
  onCreated,
}: {
  open: boolean;
  projectId: number;
  status: TaskStatus;
  members: ProjectMember[];
  milestones: Milestone[];
  onClose: () => void;
  onCreated?: (t: Task) => void;
}) {
  const [formError, setFormError] = useState<string | null>(null);
  const form = useForm<TaskValues>({ resolver: zodResolver(taskSchema), defaultValues: taskToValues(null, status) });
  const create = usePmMutation((body: TaskInput) => projectsApi.createTask(projectId, body), { success: (t: Task) => `Task “${t.title}” created`, errorTitle: 'Could not create task' });

  const onSubmit = async (v: TaskValues) => {
    setFormError(null);
    try {
      const t = await create.mutateAsync(valuesToTaskInput(v));
      onCreated?.(t);
      onClose();
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, form.setError as never, TASK_FIELDS));
    }
  };

  return (
    <Drawer
      open={open}
      onClose={onClose}
      size="md"
      title="New task"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="new-task-form" loading={form.formState.isSubmitting}>Create task</Button>
        </>
      }
    >
      <form id="new-task-form" onSubmit={form.handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <TaskFields register={form.register} control={form.control} errors={form.formState.errors} members={members} milestones={milestones} autoFocusTitle />
      </form>
    </Drawer>
  );
}
