'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Alert, Button, DateInput, Drawer, FormField, FormGrid, Input, Select, Textarea } from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { applyServerErrors } from '@/lib/crud/resource';
import { useCan } from '@/lib/auth/useCan';
import { useAppSelector } from '@/store';
import { selectBranches } from '@/store/sessionSlice';
import { payrollApi, useDepartmentOptions, useInvalidatePayroll, type RunResult } from './api';
import { SkippedList, currentMonth, monthBounds, monthLabel } from './components';
import styles from './payroll.module.scss';

const schema = z
  .object({
    month: z.string().regex(/^\d{4}-\d{2}$/, 'Pick a month'),
    name: z.string().trim().min(1, 'Name is required').max(150),
    payment_date: z.string().min(1, 'Payment date is required'),
    branch_id: z.string(),
    department_id: z.string(),
    notes: z.string().max(1000),
  })
  .superRefine((v, ctx) => {
    const b = monthBounds(v.month);
    if (b && v.payment_date && v.payment_date < b.start) {
      ctx.addIssue({ code: 'custom', path: ['payment_date'], message: 'Payment date cannot be before the period starts' });
    }
  });
type Values = z.infer<typeof schema>;

const defaultName = (month: string) => {
  const b = monthBounds(month);
  return b ? `Payroll ${monthLabel(b.start)}` : '';
};

export function NewRunDrawer({ open, onClose }: { open: boolean; onClose: () => void }) {
  const router = useRouter();
  const can = useCan();
  const branches = useAppSelector(selectBranches);
  const departments = useDepartmentOptions(open && can('departments.view'));
  const invalidate = useInvalidatePayroll();
  const [formError, setFormError] = useState<string | null>(null);
  const [result, setResult] = useState<RunResult | null>(null);
  const month0 = currentMonth();
  const { register, handleSubmit, control, setError, setValue, getValues, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      month: month0,
      name: defaultName(month0),
      payment_date: monthBounds(month0)?.end ?? '',
      branch_id: '',
      department_id: '',
      notes: '',
    },
  });
  const e = formState.errors;

  const onMonth = (month: string) => {
    const prev = getValues('month');
    setValue('month', month, { shouldValidate: true });
    const b = monthBounds(month);
    if (!b) return;
    // Keep user-typed names; only replace the generated default.
    if (getValues('name') === defaultName(prev) || !getValues('name')) setValue('name', defaultName(month));
    const pb = monthBounds(prev);
    if (!getValues('payment_date') || (pb && getValues('payment_date') === pb.end)) setValue('payment_date', b.end);
  };

  const submit = handleSubmit(async (v) => {
    setFormError(null);
    const b = monthBounds(v.month)!;
    try {
      const r = await payrollApi.createRun({
        name: v.name.trim(),
        period_start: b.start,
        period_end: b.end,
        payment_date: v.payment_date,
        branch_id: v.branch_id ? Number(v.branch_id) : null,
        department_id: v.department_id ? Number(v.department_id) : null,
        notes: v.notes.trim() || null,
      });
      void invalidate();
      if (r.skipped.length === 0) {
        onClose();
        router.push(`/payroll/runs/${r.run.id}`);
      } else {
        setResult(r);
      }
    } catch (err) {
      const x = err as ApiError;
      if (x.status === 422 && x.errors) {
        // period_* errors belong to the month picker.
        const errors = { ...x.errors };
        for (const k of ['period_start', 'period_end'] as const) {
          if (errors[k]) {
            setError('month', { type: 'server', message: errors[k][0] ?? 'Invalid period' });
            delete errors[k];
          }
        }
        const left = applyServerErrors({ ...x, errors }, setError as never, ['name', 'payment_date', 'branch_id', 'department_id', 'notes']);
        setFormError(Object.keys(errors).length ? left : null);
      } else {
        setFormError(x.message);
      }
    }
  });

  if (result) {
    return (
      <Drawer open={open} onClose={onClose} title={`${result.run.number} created`} size="md"
        footer={
          <>
            <Button variant="secondary" onClick={onClose}>Close</Button>
            <Button onClick={() => { onClose(); router.push(`/payroll/runs/${result.run.id}`); }}>Open run</Button>
          </>
        }>
        <Alert variant="success" style={{ marginBottom: 16 }}>
          The run was created with {result.run.totals.employee_count} employee{result.run.totals.employee_count === 1 ? '' : 's'}. Open it to review and calculate payslips.
        </Alert>
        <Alert variant="warning" title={`${result.skipped.length} employee${result.skipped.length === 1 ? ' was' : 's were'} skipped`}>
          <SkippedList skipped={result.skipped} />
          <p className={styles.hint} style={{ marginTop: 8 }}>Record a salary for these employees (Payroll → Salary structures → Employee salaries), then recalculate the run to include them.</p>
        </Alert>
      </Drawer>
    );
  }

  return (
    <Drawer open={open} onClose={onClose} title="New payroll run" description="Monthly payroll: the period is always one full calendar month." size="md"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="new-run-form" loading={formState.isSubmitting}>Create run</Button>
        </>
      }>
      <form id="new-run-form" noValidate onSubmit={submit}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          <Controller control={control} name="month" render={({ field }) => {
            const b = monthBounds(field.value);
            return (
              <FormField label="Pay period (month)" required error={e.month?.message} hint={b ? `${b.start} → ${b.end}` : undefined} keepHint>
                <Input type="month" value={field.value} onChange={(ev) => onMonth(ev.target.value)} />
              </FormField>
            );
          }} />
          <Controller control={control} name="payment_date" render={({ field }) => (
            <FormField label="Payment date" required error={e.payment_date?.message}>
              <DateInput value={field.value} onValueChange={(v) => field.onChange(v ?? '')} />
            </FormField>
          )} />
          <FormField label="Name" required error={e.name?.message} span="full"><Input maxLength={150} {...register('name')} /></FormField>
          {branches.length > 0 && (
            <FormField label="Branch" error={e.branch_id?.message} hint="Leave empty to include all branches you can access.">
              <Select placeholder="All branches" {...register('branch_id')} options={branches.map((b) => ({ value: String(b.id), label: b.name }))} />
            </FormField>
          )}
          {can('departments.view') && (
            <FormField label="Department" error={e.department_id?.message} hint="Optional — limit the run to one department.">
              <Select placeholder="All departments" {...register('department_id')} options={departments.options} />
            </FormField>
          )}
          <FormField label="Notes" error={e.notes?.message} span="full"><Textarea rows={3} maxLength={1000} {...register('notes')} /></FormField>
        </FormGrid>
        <p className={styles.hint} style={{ marginTop: 16 }}>
          Employees in scope with a salary effective for the period are added automatically; anyone without one is listed as skipped.
        </p>
      </form>
    </Drawer>
  );
}
