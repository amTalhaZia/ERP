'use client';

import { useQuery } from '@tanstack/react-query';
import { Badge, StatusBadge, type BadgeVariant, type SelectOption } from '@/components/ui';
import { get } from '@/lib/api/client';
import { useCan } from '@/lib/auth/useCan';
import { formatPercent, humanize } from '@/lib/format';
import { cn } from '@/lib/cn';
import { useAppSelector } from '@/store';
import { selectUser } from '@/store/sessionSlice';
import { TASK_STATUS_LABELS, useCompanyId, type Priority, type Project, type TaskStatus } from './api';
import styles from './projects.module.scss';

// ---------------------------------------------------------------------------
// Badges & progress
// ---------------------------------------------------------------------------

const PRIORITY_VARIANT: Record<Priority, BadgeVariant> = { low: 'neutral', medium: 'info', high: 'warning', critical: 'danger' };

export function PriorityBadge({ priority, size = 'sm' }: { priority: Priority | null | undefined; size?: 'sm' | 'md' }) {
  if (!priority) return null;
  return <Badge size={size} variant={PRIORITY_VARIANT[priority]} dot>{humanize(priority)}</Badge>;
}

export function ProjectStatusBadge({ status, size }: { status: string; size?: 'sm' | 'md' }) {
  return <StatusBadge status={status} size={size} />;
}

export function TaskStatusBadge({ status, size = 'sm' }: { status: TaskStatus; size?: 'sm' | 'md' }) {
  return <StatusBadge status={status} size={size} label={TASK_STATUS_LABELS[status]} />;
}

export const TASK_STATUS_COLORS: Record<TaskStatus, string> = {
  todo: 'var(--chart-4)',
  in_progress: 'var(--chart-1)',
  in_review: 'var(--chart-5)',
  done: 'var(--chart-2)',
  blocked: 'var(--chart-6)',
};

/** Horizontal meter: value is a percentage (0–100+). Turns danger past 100 when `overflowDanger`. */
export function Progress({
  value,
  label,
  showValue = true,
  overflowDanger,
  color,
  size = 'md',
}: {
  value: number | null | undefined;
  label: string;
  showValue?: boolean;
  overflowDanger?: boolean;
  color?: string | null;
  size?: 'sm' | 'md';
}) {
  const v = value ?? 0;
  const clamped = Math.max(0, Math.min(100, v));
  const over = overflowDanger && v > 100;
  return (
    <div className={cn(styles.progress, size === 'sm' && styles.progressSm)}>
      <div
        className={styles.progressTrack}
        role="meter"
        aria-label={label}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={Math.round(clamped)}
        aria-valuetext={value === null || value === undefined ? 'Not available' : formatPercent(v)}
      >
        <span className={cn(styles.progressFill, over && styles.progressOver)} style={{ width: `${clamped}%`, background: over ? undefined : color ?? undefined }} />
      </div>
      {showValue && <span className={styles.progressValue}>{value === null || value === undefined ? '—' : formatPercent(v, { fractionDigits: 0 })}</span>}
    </div>
  );
}

export const enumOptions = (values: readonly string[], labels?: Record<string, string>): SelectOption[] =>
  values.map((v) => ({ value: v, label: labels?.[v] ?? humanize(v) }));

/** "a, b , c" → ['a','b','c'] (deduped, trimmed). */
export function splitLabels(raw: string): string[] {
  return [...new Set(raw.split(',').map((t) => t.trim()).filter(Boolean))];
}

export const todayIso = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

/** "" → null, trimmed otherwise. */
export const nullable = (v: string | null | undefined) => {
  const t = (v ?? '').trim();
  return t === '' ? null : t;
};

// ---------------------------------------------------------------------------
// People pickers
// ---------------------------------------------------------------------------

export interface Person {
  id: number;
  name: string;
  email?: string | null;
  avatar_url?: string | null;
}

/**
 * Company people for manager / member pickers. Tries GET /members (company
 * directory, no special permission), then GET /users (needs users.view), then
 * falls back to the current user only. `extra` keeps existing values visible.
 */
export function usePeople(extra: (Person | null | undefined)[] = [], permission?: string) {
  const can = useCan();
  const me = useAppSelector(selectUser);
  const companyId = useCompanyId();
  const canUsers = can('users.view');
  const q = useQuery({
    queryKey: [companyId, 'people', 'projects-picker', permission ?? '', canUsers],
    staleTime: 5 * 60_000,
    retry: false,
    queryFn: async (): Promise<{ rows: Person[]; source: 'members' | 'users' | 'self' }> => {
      try {
        const r = await get<{ data: Person[] }>('/members', { per_page: 100, permission: permission || undefined });
        if (Array.isArray(r.data)) return { rows: r.data, source: 'members' };
      } catch {
        // fall through to /users
      }
      if (canUsers) {
        try {
          const r = await get<{ data: Person[] }>('/users', { per_page: 100 });
          return { rows: r.data, source: 'users' };
        } catch {
          // fall through to self
        }
      }
      return { rows: [], source: 'self' };
    },
  });
  const map = new Map<number, Person>();
  for (const u of q.data?.rows ?? []) map.set(u.id, u);
  if (me && !map.has(me.id)) map.set(me.id, { id: me.id, name: me.name });
  for (const u of extra) if (u && !map.has(u.id)) map.set(u.id, u);
  const people = [...map.values()].sort((a, b) => a.name.localeCompare(b.name));
  const options: SelectOption[] = people.map((p) => ({ value: String(p.id), label: p.id === me?.id ? `${p.name} (me)` : p.name }));
  return { people, options, isLoading: q.isLoading, limited: q.data?.source === 'self' };
}

// ---------------------------------------------------------------------------
// Access helpers (mirror ChecksProjectAccess)
// ---------------------------------------------------------------------------

export function useProjectAccess(project: Pick<Project, 'members'> | null | undefined) {
  const can = useCan();
  const me = useAppSelector(selectUser);
  const isMember = !!me && !!project?.members?.some((m) => m.user_id === me.id);
  const manage = can('projects.manage');
  return {
    me,
    isMember,
    /** Edit project, milestones, members, expenses. */
    manage,
    /** Create/update/move/delete tasks. */
    work: manage || (can('tasks.manage') && isMember),
    /** Log time. */
    logTime: can('time_entries.manage') || manage,
    /** See financial tabs (expenses). */
    financials: can('projects.view') || manage,
    /** See all time entries (otherwise only own). */
    viewAllTime: can('projects.view') || manage,
  };
}
