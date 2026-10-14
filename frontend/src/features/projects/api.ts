'use client';

import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, get } from '@/lib/api/client';
import type { ApiError } from '@/lib/api/errors';
import type { Paginated, Single } from '@/lib/api/types';
import { resource } from '@/lib/crud/resource';
import { useAppSelector } from '@/store';
import { useToast } from '@/components/ui';

// ---------------------------------------------------------------------------
// Enums (mirror app/Enums/Projects)
// ---------------------------------------------------------------------------

export const PROJECT_STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled'] as const;
export type ProjectStatus = (typeof PROJECT_STATUSES)[number];

export const PRIORITIES = ['low', 'medium', 'high', 'critical'] as const;
export type Priority = (typeof PRIORITIES)[number];

export const TASK_STATUSES = ['todo', 'in_progress', 'in_review', 'done', 'blocked'] as const;
export type TaskStatus = (typeof TASK_STATUSES)[number];
export const TASK_STATUS_LABELS: Record<TaskStatus, string> = {
  todo: 'To do',
  in_progress: 'In progress',
  in_review: 'In review',
  done: 'Done',
  blocked: 'Blocked',
};

export const MEMBER_ROLES = ['manager', 'member', 'viewer'] as const;
export type MemberRole = (typeof MEMBER_ROLES)[number];

export type MilestoneStatus = 'pending' | 'completed';

// ---------------------------------------------------------------------------
// Types (mirror app/Http/Resources/Projects + ProjectInsightsService)
// ---------------------------------------------------------------------------

export interface UserSummary {
  id: number;
  name: string;
  avatar_url: string | null;
}

export interface Ref {
  id: number;
  name: string;
}

export interface ProjectMember {
  id: number;
  project_id: number;
  user_id: number;
  user?: UserSummary | null;
  role: MemberRole;
  created_at: string | null;
}

export interface Project {
  id: number;
  number: string;
  name: string;
  code: string | null;
  description: string | null;
  customer_id: number | null;
  department_id: number | null;
  department?: Ref | null;
  branch_id: number | null;
  branch?: Ref | null;
  manager_id: number | null;
  manager?: UserSummary | null;
  status: ProjectStatus;
  priority: Priority | null;
  start_date: string | null;
  due_date: string | null;
  completed_at: string | null;
  budget: string | null;
  currency: string;
  color: string | null;
  is_billable: boolean;
  members?: ProjectMember[];
  members_count?: number;
  tasks_count?: number;
  done_tasks_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface ProjectInput {
  name?: string;
  code?: string | null;
  description?: string | null;
  customer_id?: number | null;
  department_id?: number | null;
  branch_id?: number | null;
  manager_id?: number | null;
  status?: ProjectStatus;
  priority?: Priority;
  start_date?: string | null;
  due_date?: string | null;
  budget?: string | null;
  currency?: string;
  color?: string | null;
  is_billable?: boolean;
}

export interface OverviewProject {
  id: number;
  number: string;
  name: string;
  status: ProjectStatus;
  priority: Priority | null;
  color: string | null;
  due_date: string | null;
  is_overdue: boolean;
  manager: UserSummary | null;
  tasks_total: number;
  tasks_done: number;
  overdue_tasks: number;
  percent_complete: number;
  hours_logged: string;
  budget: string | null;
  currency: string;
}

export interface ProjectsOverview {
  totals: {
    projects: number;
    by_status: Record<ProjectStatus, number>;
    open: number;
    overdue_projects: number;
    overdue_tasks: number;
    my_open_tasks: number;
  };
  projects: OverviewProject[];
}

export interface Task {
  id: number;
  project_id: number;
  project?: { id: number; number: string; name: string } | null;
  parent_id: number | null;
  milestone_id: number | null;
  milestone?: Ref | null;
  title: string;
  description: string | null;
  status: TaskStatus;
  priority: Priority | null;
  assignee_id: number | null;
  assignee?: UserSummary | null;
  reporter_id: number | null;
  reporter?: UserSummary | null;
  start_date: string | null;
  due_date: string | null;
  estimate_hours: string | null;
  position: number;
  completed_at: string | null;
  labels: string[];
  subtasks?: Task[];
  subtasks_count?: number;
  comments_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface TaskInput {
  title?: string;
  description?: string | null;
  status?: TaskStatus;
  priority?: Priority;
  milestone_id?: number | null;
  assignee_id?: number | null;
  start_date?: string | null;
  due_date?: string | null;
  estimate_hours?: string | null;
  labels?: string[] | null;
}

export interface BoardTask {
  id: number;
  title: string;
  status: TaskStatus;
  priority: Priority | null;
  position: number;
  milestone_id: number | null;
  due_date: string | null;
  is_overdue: boolean;
  labels: string[];
  estimate_hours: string | null;
  assignee: UserSummary | null;
  subtasks_count: number;
  comments_count: number;
}

export interface BoardColumn {
  status: TaskStatus;
  label: string;
  tasks: BoardTask[];
}

export interface Board {
  project: { id: number; number: string; name: string };
  columns: BoardColumn[];
}

export interface ProjectSummary {
  project: { id: number; number: string; name: string; status: ProjectStatus };
  tasks: {
    total: number;
    by_status: Record<TaskStatus, number>;
    percent_complete: number;
    overdue_count: number;
    overdue: { id: number; title: string; status: TaskStatus; due_date: string | null; assignee: UserSummary | null }[];
  };
  hours: { logged: string; estimated: string; remaining: string; percent_of_estimate: number | null };
  budget: {
    currency: string;
    budget: string | null;
    expenses: string;
    billable_time_cost: string;
    spent: string;
    remaining: string | null;
    percent_used: number | null;
  };
  milestones: {
    total: number;
    completed: number;
    items: { id: number; name: string; status: MilestoneStatus; due_date: string | null; tasks_total: number; tasks_done: number; percent_complete: number }[];
  };
}

export interface TimeSummary {
  project_id: number;
  from: string | null;
  to: string | null;
  totals: { hours: string; billable_hours: string; non_billable_hours: string; billable_amount: string; currency: string };
  by_user: { user_id: number; name: string; hours: string; billable_hours: string }[];
  by_task: { task_id: number | null; title: string; hours: string }[];
}

export interface Milestone {
  id: number;
  project_id: number;
  name: string;
  description: string | null;
  due_date: string | null;
  status: MilestoneStatus;
  completed_at: string | null;
  position: number;
  tasks_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface MilestoneInput {
  name?: string;
  description?: string | null;
  due_date?: string | null;
  status?: MilestoneStatus;
  position?: number;
}

export interface TaskComment {
  id: number;
  task_id: number;
  user_id: number;
  user?: UserSummary | null;
  body: string;
  edited_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface TimeEntry {
  id: number;
  project_id: number;
  project?: { id: number; number: string; name: string } | null;
  task_id: number | null;
  task?: { id: number; title: string } | null;
  user_id: number;
  user?: UserSummary | null;
  date: string;
  hours: string;
  description: string | null;
  is_billable: boolean;
  hourly_rate: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface TimeEntryInput {
  project_id?: number;
  task_id?: number | null;
  user_id?: number;
  date?: string;
  hours?: string;
  description?: string | null;
  is_billable?: boolean;
  hourly_rate?: string | null;
}

export interface ProjectExpense {
  id: number;
  project_id: number;
  date: string;
  category: string;
  description: string | null;
  amount: string;
  currency: string;
  receipt_document_id: number | null;
  created_by: number | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface ProjectExpenseInput {
  date?: string;
  category?: string;
  description?: string | null;
  amount?: string;
  currency?: string;
  receipt_document_id?: number | null;
}

// ---------------------------------------------------------------------------
// Raw API
// ---------------------------------------------------------------------------

const data = <T>(p: Promise<{ data: Single<T> }>) => p.then((r) => r.data.data);

export const projectsApi = {
  overview: (status?: string) => get<Single<ProjectsOverview>>('/projects/overview', status ? { status } : undefined).then((r) => r.data),
  summary: (id: number) => get<Single<ProjectSummary>>(`/projects/${id}/summary`).then((r) => r.data),
  timeSummary: (id: number, from?: string, to?: string) =>
    get<Single<TimeSummary>>(`/projects/${id}/time-summary`, { from: from || undefined, to: to || undefined }).then((r) => r.data),
  board: (id: number, params: { assignee_id?: number; milestone_id?: number }) => get<Single<Board>>(`/projects/${id}/board`, params).then((r) => r.data),

  members: (id: number) => get<{ data: ProjectMember[] }>(`/projects/${id}/members`).then((r) => r.data),
  addMember: (id: number, body: { user_id: number; role?: MemberRole }) => data<ProjectMember>(api.post(`/projects/${id}/members`, body)),
  updateMember: (id: number, memberId: number, role: MemberRole) => data<ProjectMember>(api.put(`/projects/${id}/members/${memberId}`, { role })),
  removeMember: (id: number, memberId: number) => api.delete(`/projects/${id}/members/${memberId}`).then(() => undefined),

  milestones: (id: number) => get<{ data: Milestone[] }>(`/projects/${id}/milestones`).then((r) => r.data),
  createMilestone: (id: number, body: MilestoneInput) => data<Milestone>(api.post(`/projects/${id}/milestones`, body)),
  updateMilestone: (milestoneId: number, body: MilestoneInput) => data<Milestone>(api.put(`/milestones/${milestoneId}`, body)),
  deleteMilestone: (milestoneId: number) => api.delete(`/milestones/${milestoneId}`).then(() => undefined),

  tasks: (id: number, params: Record<string, unknown>) => get<Paginated<Task>>(`/projects/${id}/tasks`, params),
  myTasks: (params: Record<string, unknown>) => get<Paginated<Task>>('/my-tasks', params),
  task: (taskId: number) => get<Single<Task>>(`/tasks/${taskId}`).then((r) => r.data),
  createTask: (id: number, body: TaskInput) => data<Task>(api.post(`/projects/${id}/tasks`, body)),
  createSubtask: (taskId: number, body: TaskInput) => data<Task>(api.post(`/tasks/${taskId}/subtasks`, body)),
  updateTask: (taskId: number, body: TaskInput) => data<Task>(api.put(`/tasks/${taskId}`, body)),
  moveTask: (taskId: number, body: { status: TaskStatus; position: number }) => data<Task>(api.patch(`/tasks/${taskId}/move`, body)),
  deleteTask: (taskId: number) => api.delete(`/tasks/${taskId}`).then(() => undefined),

  comments: (taskId: number) => get<Paginated<TaskComment>>(`/tasks/${taskId}/comments`, { per_page: 100 }),
  addComment: (taskId: number, body: string) => data<TaskComment>(api.post(`/tasks/${taskId}/comments`, { body })),
  updateComment: (taskId: number, commentId: number, body: string) => data<TaskComment>(api.put(`/tasks/${taskId}/comments/${commentId}`, { body })),
  deleteComment: (taskId: number, commentId: number) => api.delete(`/tasks/${taskId}/comments/${commentId}`).then(() => undefined),

  expenses: (id: number, params: Record<string, unknown>) => get<Paginated<ProjectExpense>>(`/projects/${id}/expenses`, params),
  createExpense: (id: number, body: ProjectExpenseInput) => data<ProjectExpense>(api.post(`/projects/${id}/expenses`, body)),
  updateExpense: (id: number, expenseId: number, body: ProjectExpenseInput) => data<ProjectExpense>(api.put(`/projects/${id}/expenses/${expenseId}`, body)),
  deleteExpense: (id: number, expenseId: number) => api.delete(`/projects/${id}/expenses/${expenseId}`).then(() => undefined),
};

/** Standard CRUD for projects and time entries (list/one/create/update/delete). */
export const projects = resource<Project, ProjectInput>('projects', 'Project');
export const timeEntries = resource<TimeEntry, TimeEntryInput>('time-entries', 'Time entry');

// ---------------------------------------------------------------------------
// Hooks
// ---------------------------------------------------------------------------

/** Every projects-module query key contains this tag so one invalidation refreshes them all. */
export const PROJECTS_KEY = 'projects';

export function useCompanyId() {
  return useAppSelector((s) => s.session.current_company?.id ?? 0);
}

export function usePmQuery<T>(key: unknown[], fn: () => Promise<T>, opts: { enabled?: boolean; keepPrevious?: boolean; staleTime?: number } = {}) {
  const companyId = useCompanyId();
  return useQuery({
    queryKey: [companyId, PROJECTS_KEY, ...key],
    queryFn: fn,
    enabled: opts.enabled ?? true,
    placeholderData: opts.keepPrevious ? keepPreviousData : undefined,
    staleTime: opts.staleTime,
  });
}

/** Mutation that refreshes projects, time entries and (optionally) documents queries. */
export function usePmMutation<TVars, TRes = unknown>(
  fn: (vars: TVars) => Promise<TRes>,
  opts: { success?: string | ((r: TRes, v: TVars) => string); errorTitle?: string; onSuccess?: (r: TRes, v: TVars) => void; toastOn422?: boolean } = {},
) {
  const qc = useQueryClient();
  const toast = useToast();
  return useMutation<TRes, ApiError, TVars>({
    mutationFn: fn,
    onSuccess: (r, v) => {
      void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes(PROJECTS_KEY) || q.queryKey.includes('time-entries') });
      if (opts.success) toast.success(typeof opts.success === 'function' ? opts.success(r, v) : opts.success);
      opts.onSuccess?.(r, v);
    },
    onError: (e) => {
      if (e.status !== 422 || opts.toastOn422) {
        toast.error(opts.errorTitle ?? 'Action failed', e.status === 422 ? Object.values(e.errors ?? {})[0]?.[0] ?? e.message : e.message);
      }
    },
  });
}

export function useProject(id: number) {
  return usePmQuery(['one', id], () => get<Single<Project>>(`/projects/${id}`).then((r) => r.data), { enabled: Number.isFinite(id) && id > 0 });
}

export function useMilestones(projectId: number, enabled = true) {
  return usePmQuery(['milestones', projectId], () => projectsApi.milestones(projectId), { enabled, staleTime: 30_000 });
}
