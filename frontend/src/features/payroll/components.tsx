'use client';

import type { ReactNode } from 'react';
import { endOfMonth, format, parseISO } from 'date-fns';
import { Check, Plus, Trash2, X } from 'lucide-react';
import { z } from 'zod';
import { Badge, Button, IconButton, Input, Select, StatusBadge, Tooltip, type BadgeVariant } from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { formatDateTime, formatMoney, humanize } from '@/lib/format';
import { trimDecimal } from '@/features/finance/receivables/money';
import { RUN_STATUSES, SKIP_REASONS, WARNINGS, calcLabel, kindLabel, type PayComponent, type PayrollRun, type RunStatus, type SkippedEmployee } from './api';
import styles from './payroll.module.scss';

// ---- Dates ---------------------------------------------------------------------------

export const todayIso = () => format(new Date(), 'yyyy-MM-dd');
export const currentMonth = () => format(new Date(), 'yyyy-MM');

/** "2026-10" → full calendar month bounds. */
export function monthBounds(month: string): { start: string; end: string } | null {
  if (!/^\d{4}-\d{2}$/.test(month)) return null;
  const start = parseISO(`${month}-01`);
  if (Number.isNaN(start.getTime())) return null;
  return { start: format(start, 'yyyy-MM-dd'), end: format(endOfMonth(start), 'yyyy-MM-dd') };
}
export const monthLabel = (isoDate: string) => format(parseISO(isoDate), 'MMMM yyyy');
export const periodLabel = (start: string, end: string) =>
  start.slice(0, 7) === end.slice(0, 7) && start.endsWith('-01') ? monthLabel(start) : `${start} – ${end}`;

// ---- Status --------------------------------------------------------------------------

const RUN_VARIANTS: Record<RunStatus, BadgeVariant> = {
  draft: 'neutral',
  calculated: 'info',
  approved: 'primary',
  finalized: 'warning',
  paid: 'success',
  cancelled: 'neutral',
};

export function RunStatusBadge({ status, size }: { status: RunStatus; size?: 'sm' | 'md' }) {
  return <StatusBadge status={status} size={size} variant={RUN_VARIANTS[status]} label={RUN_STATUSES.find((s) => s.value === status)?.label ?? humanize(status)} />;
}

const FLOW: { status: RunStatus; label: string; at: (r: PayrollRun) => string | null; by?: (r: PayrollRun) => string | undefined }[] = [
  { status: 'draft', label: 'Draft', at: (r) => r.created_at, by: (r) => r.created_by?.name },
  { status: 'calculated', label: 'Calculated', at: (r) => r.calculated_at },
  { status: 'approved', label: 'Approved', at: (r) => r.approved_at, by: (r) => r.approved_by?.name },
  { status: 'finalized', label: 'Finalized', at: (r) => r.finalized_at, by: (r) => r.finalized_by?.name },
  { status: 'paid', label: 'Paid', at: (r) => r.paid_at },
];
const ORDER: RunStatus[] = ['draft', 'calculated', 'approved', 'finalized', 'paid'];

/** Draft → Calculated → Approved → Finalized → Paid (cancelled runs show where they stopped). */
export function RunStepper({ run }: { run: PayrollRun }) {
  const cancelled = run.status === 'cancelled';
  // For cancelled runs, infer the last reached step from timestamps.
  const reached = cancelled
    ? Math.max(0, ...FLOW.map((s, i) => (s.at(run) ? i : -1)))
    : ORDER.indexOf(run.status);
  return (
    <ol className={styles.stepper} aria-label="Payroll run progress">
      {FLOW.map((s, i) => {
        const state = i < reached || (i === reached && (run.status === 'paid' || cancelled)) ? 'done' : i === reached ? 'current' : 'pending';
        const at = s.at(run);
        const by = s.by?.(run);
        return (
          <li key={s.status} aria-current={state === 'current' ? 'step' : undefined}>
            <span className={styles.stepDot} data-state={state} aria-hidden>{state === 'done' ? <Check size={12} /> : i + 1}</span>
            <span className={styles.stepText}>
              <strong>{s.label}</strong>
              <span>{at && i <= reached ? formatDateTime(at) : state === 'current' ? 'Current' : 'Pending'}{by && i <= reached ? ` · ${by}` : ''}</span>
            </span>
          </li>
        );
      })}
      {cancelled && (
        <li>
          <span className={styles.stepDot} data-state="bad" aria-hidden><X size={12} /></span>
          <span className={styles.stepText}><strong>Cancelled</strong><span>{formatDateTime(run.cancelled_at)}</span></span>
        </li>
      )}
    </ol>
  );
}

// ---- Warnings & skipped ----------------------------------------------------------------

export function WarningBadges({ warnings }: { warnings: string[] | undefined }) {
  if (!warnings?.length) return <span className={styles.hint}>—</span>;
  return (
    <span className={styles.warnings}>
      {warnings.map((w) => (
        <Tooltip key={w} content={WARNINGS[w]?.help ?? humanize(w)}>
          <Badge size="sm" variant={w === 'negative_net' ? 'danger' : 'warning'} tabIndex={0}>{WARNINGS[w]?.label ?? humanize(w)}</Badge>
        </Tooltip>
      ))}
    </span>
  );
}

export function SkippedList({ skipped }: { skipped: SkippedEmployee[] }) {
  return (
    <ul className={styles.skipped}>
      {skipped.map((s) => (
        <li key={s.employee_id}>
          <strong>{s.name}</strong> <span className={styles.hint}>({s.employee_code})</span> — {SKIP_REASONS[s.reason] ?? humanize(s.reason)}
        </li>
      ))}
    </ul>
  );
}

// ---- Errors ------------------------------------------------------------------------------

/** Server message plus guidance for payroll business-rule refusals. */
export function explainPayrollError(e: ApiError): string {
  switch (e.code) {
    case 'payroll.self_approval':
      return `${e.message} Payroll needs two people: someone other than the run's creator, with the “Run & approve payroll” permission, must approve it.`;
    case 'payroll.empty_run':
      return `${e.message} Make sure employees in scope have a salary effective for this period, then recalculate.`;
    case 'payroll.negative_net':
      return e.message;
    case 'payroll.invalid_state':
      return `${e.message} The run may have been changed by someone else — the page has been refreshed.`;
    default:
      return e.message;
  }
}

// ---- Money helpers ------------------------------------------------------------------------

export const MONEY_RE = /^\d{1,14}(\.\d{1,4})?$/;
export const RATE_RE = /^\d{1,4}(\.\d{1,4})?$/;
export const money = (label: string) => z.string().trim().refine((v) => MONEY_RE.test(v), `${label}: a non-negative amount (max 4 decimals)`);
export const optionalMoney = z.string().trim().refine((v) => v === '' || MONEY_RE.test(v), 'A non-negative amount (max 4 decimals)');
export const optionalRate = z.string().trim().refine((v) => v === '' || (RATE_RE.test(v) && Number(v) <= 1000), '0–1000 %, max 4 decimals');

export const fmtRate = (v: string | null | undefined) => (v === null || v === undefined || v === '' ? '—' : `${trimDecimal(v) || '0'}%`);

/** Amount or rate of a component, as configured. */
export function componentValue(c: Pick<PayComponent, 'calculation' | 'default_amount' | 'default_rate'>, currency: string) {
  return c.calculation === 'fixed' ? formatMoney(c.default_amount, currency) : fmtRate(c.default_rate);
}

// ---- Component overrides editor ------------------------------------------------------------

export const overrideRowSchema = z.object({
  pay_component_id: z.string().min(1, 'Choose a component'),
  amount: optionalMoney,
  rate: optionalRate,
});
export type OverrideRow = z.infer<typeof overrideRowSchema>;

/** Array-level check: each component at most once. Adds the issue to the duplicate row. */
export function refineDistinctComponents(rows: OverrideRow[], ctx: z.RefinementCtx, path = 'components') {
  const seen = new Set<string>();
  rows.forEach((r, i) => {
    if (!r.pay_component_id) return;
    if (seen.has(r.pay_component_id)) ctx.addIssue({ code: 'custom', path: [path, i, 'pay_component_id'], message: 'Component already listed' });
    seen.add(r.pay_component_id);
  });
}

export const overrideFromApi = (c: { pay_component_id: number; amount: string | null; rate: string | null }): OverrideRow => ({
  pay_component_id: String(c.pay_component_id),
  amount: c.amount === null ? '' : trimDecimal(c.amount),
  rate: c.rate === null ? '' : trimDecimal(c.rate),
});
export const overrideToApi = (r: OverrideRow) => ({
  pay_component_id: Number(r.pay_component_id),
  amount: r.amount.trim() === '' ? null : r.amount.trim(),
  rate: r.rate.trim() === '' ? null : r.rate.trim(),
});

/**
 * Rows of pay components with optional amount / rate overrides. Fully
 * controlled: the parent form owns the values (react-hook-form field array).
 */
export function OverridesEditor({
  ids,
  rows,
  components,
  currency,
  error,
  onChange,
  onAdd,
  onRemove,
  emptyText = 'No components. Add earnings, deductions or employer contributions.',
  disabled,
}: {
  ids: string[];
  rows: OverrideRow[];
  components: PayComponent[];
  currency: string;
  error: (i: number, field: keyof OverrideRow) => string | undefined;
  onChange: (i: number, field: keyof OverrideRow, value: string) => void;
  onAdd: () => void;
  onRemove: (i: number) => void;
  emptyText?: ReactNode;
  disabled?: boolean;
}) {
  const byId = new Map(components.map((c) => [String(c.id), c]));
  return (
    <div className={styles.scroll}>
      <table className={styles.gridTable} style={{ minWidth: 640 }}>
        <thead>
          <tr>
            <th>Component</th>
            <th>Type</th>
            <th style={{ width: 170 }}>Amount</th>
            <th style={{ width: 130 }}>Rate %</th>
            <th style={{ width: 44 }} aria-label="Remove" />
          </tr>
        </thead>
        <tbody>
          {ids.length === 0 && (
            <tr><td colSpan={5} className={styles.hint}>{emptyText}</td></tr>
          )}
          {ids.map((id, i) => {
            const row = rows[i] ?? { pay_component_id: '', amount: '', rate: '' };
            const c = byId.get(row.pay_component_id);
            const fixed = c?.calculation === 'fixed';
            const options = components
              .filter((x) => x.is_active || String(x.id) === row.pay_component_id)
              .map((x) => ({ value: String(x.id), label: `${x.code} · ${x.name}${x.is_active ? '' : ' (inactive)'}` }));
            return (
              <tr key={id}>
                <td>
                  <Select aria-label={`Row ${i + 1} component`} placeholder="Select component" value={row.pay_component_id} options={options} disabled={disabled}
                    invalid={!!error(i, 'pay_component_id')} onChange={(e) => onChange(i, 'pay_component_id', e.target.value)} />
                  {error(i, 'pay_component_id') && <small className={styles.fieldError}>{error(i, 'pay_component_id')}</small>}
                </td>
                <td className={styles.hint} style={{ paddingTop: 14 }}>{c ? `${kindLabel(c.kind)} · ${calcLabel(c.calculation)}` : '—'}</td>
                <td>
                  <Input aria-label={`Row ${i + 1} amount`} inputMode="decimal" value={row.amount} disabled={disabled || (!!c && !fixed)}
                    placeholder={c && fixed ? `Default ${formatMoney(c.default_amount, currency)}` : c ? 'n/a' : ''}
                    invalid={!!error(i, 'amount')} onChange={(e) => onChange(i, 'amount', e.target.value)} />
                  {error(i, 'amount') && <small className={styles.fieldError}>{error(i, 'amount')}</small>}
                </td>
                <td>
                  <Input aria-label={`Row ${i + 1} rate`} inputMode="decimal" value={row.rate} disabled={disabled || (!!c && fixed)}
                    placeholder={c && !fixed ? `Default ${fmtRate(c.default_rate)}` : c ? 'n/a' : ''}
                    invalid={!!error(i, 'rate')} onChange={(e) => onChange(i, 'rate', e.target.value)} />
                  {error(i, 'rate') && <small className={styles.fieldError}>{error(i, 'rate')}</small>}
                </td>
                <td><IconButton icon={<Trash2 size={16} />} size="sm" aria-label={`Remove row ${i + 1}`} disabled={disabled} onClick={() => onRemove(i)} /></td>
              </tr>
            );
          })}
        </tbody>
      </table>
      <div style={{ marginTop: 8 }}>
        <Button size="sm" variant="ghost" leftIcon={<Plus size={14} />} onClick={onAdd} disabled={disabled}>Add component</Button>
        <span className={styles.hint} style={{ marginLeft: 8 }}>Leave amount / rate empty to use the component default.</span>
      </div>
    </div>
  );
}
