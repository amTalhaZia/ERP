'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Plus, Star, Trash2 } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, DateInput, Drawer, FormActions, FormField, FormGrid, IconButton, Input, Select, Textarea,
} from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { useCan } from '@/lib/auth/useCan';
import { useAppSelector } from '@/store';
import { selectUser } from '@/store/sessionSlice';
import { companyUsers } from '@/features/access/api';
import { reviews, useEmployeeOptions, type PerformanceReview } from './api';
import { nul, useUnsavedChangesWarning } from './components';
import styles from './hr.module.scss';

/** 1–5 star picker (radio group semantics). */
export function StarInput({ value, onChange, label, invalid }: { value: number | null; onChange: (v: number | null) => void; label: string; invalid?: boolean }) {
  return (
    <div role="radiogroup" aria-label={label} aria-invalid={invalid || undefined} className={styles.stars} style={{ alignItems: 'center' }}>
      {[1, 2, 3, 4, 5].map((n) => (
        <button
          key={n}
          type="button"
          role="radio"
          aria-checked={value === n}
          aria-label={`${n} star${n === 1 ? '' : 's'}`}
          className={styles.starBtn}
          data-on={value !== null && n <= value}
          onClick={() => onChange(value === n ? null : n)}
        >
          <Star size={18} aria-hidden fill={value !== null && n <= value ? 'currentColor' : 'none'} />
        </button>
      ))}
    </div>
  );
}

/** Reviewer choices: company users when visible, otherwise just the signed-in user. */
function useReviewerOptions(enabled: boolean) {
  const can = useCan();
  const me = useAppSelector(selectUser);
  const users = companyUsers.useList({ per_page: 100, sort: 'name' }, { enabled: enabled && can('users.view') });
  const options = (users.data?.data ?? []).map((u) => ({ value: String(u.id), label: u.name }));
  if (me && !options.some((o) => o.value === String(me.id))) options.unshift({ value: String(me.id), label: `${me.name} (you)` });
  return options;
}

// ---- New review drawer ------------------------------------------------------------------

const newSchema = z
  .object({
    employee_id: z.string().min(1, 'Choose an employee'),
    reviewer_id: z.string().min(1, 'Choose a reviewer'),
    cycle: z.string().trim().min(1, 'Cycle is required').max(20),
    period_start: z.string().min(1, 'Required'),
    period_end: z.string().min(1, 'Required'),
  })
  .refine((v) => v.period_end >= v.period_start, { path: ['period_end'], message: 'Must be on or after the start' });

export function NewReviewDrawer({ open, onClose, employeeId }: { open: boolean; onClose: () => void; employeeId?: string }) {
  const router = useRouter();
  const me = useAppSelector(selectUser);
  const emp = useEmployeeOptions(open);
  const reviewers = useReviewerOptions(open);
  const [formError, setFormError] = useState<string | null>(null);
  const y = new Date().getFullYear();
  const { register, handleSubmit, control, setError, formState } = useForm<z.infer<typeof newSchema>>({
    resolver: zodResolver(newSchema),
    defaultValues: { employee_id: employeeId ?? '', reviewer_id: me ? String(me.id) : '', cycle: `${y}`, period_start: `${y}-01-01`, period_end: `${y}-12-31` },
  });
  const create = reviews.useCreate({ onSuccess: (r) => { onClose(); router.push(`/hr/performance/${r.id}`); } });
  const e = formState.errors;

  return (
    <Drawer open={open} onClose={onClose} size="sm" title="New performance review" description="Creates a draft you can fill in and submit."
      footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button type="submit" form="new-review-form" loading={formState.isSubmitting}>Create draft</Button></>}>
      <form id="new-review-form" noValidate onSubmit={handleSubmit(async (v) => {
        setFormError(null);
        try {
          await create.mutateAsync({ ...v, employee_id: Number(v.employee_id), reviewer_id: Number(v.reviewer_id), ratings: [] });
        } catch (err) {
          setFormError(applyServerErrors(err as ApiError, setError as never, Object.keys(newSchema.shape)));
        }
      })}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          <FormField label="Employee" required span="full" error={e.employee_id?.message}>
            <Select {...register('employee_id')} placeholder={emp.query.isLoading ? 'Loading…' : 'Choose an employee'} options={emp.options} />
          </FormField>
          <FormField label="Reviewer" required span="full" error={e.reviewer_id?.message}><Select {...register('reviewer_id')} options={reviewers} /></FormField>
          <FormField label="Cycle" required span="full" hint="e.g. 2026, 2026-H1, 2026-Q3" error={e.cycle?.message}><Input maxLength={20} {...register('cycle')} /></FormField>
          <FormField label="Period start" required error={e.period_start?.message}>
            <Controller control={control} name="period_start" render={({ field }) => <DateInput value={field.value || null} onValueChange={(d) => field.onChange(d ?? '')} />} />
          </FormField>
          <FormField label="Period end" required error={e.period_end?.message}>
            <Controller control={control} name="period_end" render={({ field }) => <DateInput value={field.value || null} onValueChange={(d) => field.onChange(d ?? '')} />} />
          </FormField>
        </FormGrid>
      </form>
    </Drawer>
  );
}

// ---- Editor ---------------------------------------------------------------------------------

const DEFAULT_CRITERIA = ['Quality of work', 'Productivity', 'Communication', 'Teamwork', 'Initiative'];

const editSchema = z
  .object({
    reviewer_id: z.string().min(1, 'Choose a reviewer'),
    cycle: z.string().trim().min(1, 'Cycle is required').max(20),
    period_start: z.string().min(1, 'Required'),
    period_end: z.string().min(1, 'Required'),
    overall_rating: z.number().nullable(),
    ratings: z.array(z.object({
      criterion: z.string().trim().min(1, 'Name the criterion').max(150),
      rating: z.number().nullable().refine((v) => v != null && v >= 1, 'Rate 1–5'),
      comment: z.string().max(1000),
    })).max(50),
    strengths: z.string().max(5000),
    improvements: z.string().max(5000),
    goals: z.string().max(5000),
  })
  .refine((v) => v.period_end >= v.period_start, { path: ['period_end'], message: 'Must be on or after the start' });
type EditValues = z.infer<typeof editSchema>;

export function ReviewEditor({ review, onSubmitted }: { review: PerformanceReview; onSubmitted: () => void }) {
  const reviewers = useReviewerOptions(true);
  const [formError, setFormError] = useState<string | null>(null);
  const update = reviews.useUpdate({ silent: true });
  const submit = reviews.useAction('submit', { success: 'Review submitted — the employee can now acknowledge it' });
  const { register, handleSubmit, control, setError, reset, formState } = useForm<EditValues>({
    resolver: zodResolver(editSchema),
    defaultValues: {
      reviewer_id: String(review.reviewer_id),
      cycle: review.cycle,
      period_start: review.period_start,
      period_end: review.period_end,
      overall_rating: review.overall_rating != null ? Math.round(Number(review.overall_rating)) : null,
      ratings: review.ratings.length
        ? review.ratings.map((r) => ({ criterion: r.criterion, rating: r.rating != null ? Math.round(Number(r.rating)) : null, comment: r.comment ?? '' }))
        : DEFAULT_CRITERIA.map((c) => ({ criterion: c, rating: null, comment: '' })),
      strengths: review.strengths ?? '',
      improvements: review.improvements ?? '',
      goals: review.goals ?? '',
    },
  });
  const { fields, append, remove } = useFieldArray({ control, name: 'ratings' });
  const ratings = useWatch({ control, name: 'ratings' });
  const rated = (ratings ?? []).map((r) => r.rating).filter((v): v is number => v !== null);
  const average = rated.length ? rated.reduce((a, b) => a + b, 0) / rated.length : null;
  useUnsavedChangesWarning(formState.isDirty);
  const e = formState.errors;
  if (!reviewers.some((o) => o.value === String(review.reviewer_id)) && review.reviewer) {
    reviewers.unshift({ value: String(review.reviewer_id), label: review.reviewer.name });
  }

  const save = async (v: EditValues, thenSubmit: boolean) => {
    setFormError(null);
    const body = {
      reviewer_id: Number(v.reviewer_id),
      cycle: v.cycle,
      period_start: v.period_start,
      period_end: v.period_end,
      overall_rating: v.overall_rating,
      ratings: v.ratings.map((r) => ({ criterion: r.criterion, rating: r.rating, comment: nul(r.comment) })),
      strengths: nul(v.strengths),
      improvements: nul(v.improvements),
      goals: nul(v.goals),
    };
    try {
      await update.mutateAsync({ id: review.id, body });
      reset(v);
      if (thenSubmit) {
        await submit.mutateAsync({ id: review.id });
        onSubmitted();
      }
    } catch (err) {
      const apiErr = err as ApiError;
      setFormError(applyServerErrors(apiErr, setError as never, [...Object.keys(editSchema.shape), 'ratings']) ?? (apiErr.status === 422 ? 'Please correct the highlighted fields.' : null));
    }
  };

  return (
    <form noValidate onSubmit={handleSubmit((v) => save(v, false))} className={styles.stack}>
      {formError && <Alert variant="danger">{formError}</Alert>}
      <Card>
        <CardHeader title="Review details" />
        <CardBody>
          <FormGrid columns={4}>
            <FormField label="Reviewer" required error={e.reviewer_id?.message}><Select {...register('reviewer_id')} options={reviewers} /></FormField>
            <FormField label="Cycle" required error={e.cycle?.message}><Input maxLength={20} {...register('cycle')} /></FormField>
            <FormField label="Period start" required error={e.period_start?.message}>
              <Controller control={control} name="period_start" render={({ field }) => <DateInput value={field.value || null} onValueChange={(d) => field.onChange(d ?? '')} />} />
            </FormField>
            <FormField label="Period end" required error={e.period_end?.message}>
              <Controller control={control} name="period_end" render={({ field }) => <DateInput value={field.value || null} onValueChange={(d) => field.onChange(d ?? '')} />} />
            </FormField>
          </FormGrid>
        </CardBody>
      </Card>

      <Card>
        <CardHeader
          title="Criteria"
          description={average !== null ? `Average ${average.toFixed(1)} / 5 across ${rated.length} rated` : 'Rate each criterion from 1 to 5'}
          actions={<Button size="sm" variant="secondary" leftIcon={<Plus size={14} />} onClick={() => append({ criterion: '', rating: null, comment: '' })} disabled={fields.length >= 50}>Add criterion</Button>}
        />
        <CardBody>
          {fields.length === 0 && <p className={styles.small}>No criteria — add at least one, or rely on the overall rating.</p>}
          {fields.map((f, i) => (
            <div key={f.id} className={styles.ratingRow}>
              <FormField label="Criterion" hideLabel error={e.ratings?.[i]?.criterion?.message}><Input aria-label={`Criterion ${i + 1}`} placeholder="Criterion" {...register(`ratings.${i}.criterion`)} /></FormField>
              <FormField label="Rating" hideLabel error={e.ratings?.[i]?.rating?.message}>
                <Controller control={control} name={`ratings.${i}.rating`} render={({ field }) => (
                  <StarInput label={`Rating for criterion ${i + 1}`} value={field.value} onChange={field.onChange} invalid={!!e.ratings?.[i]?.rating} />
                )} />
              </FormField>
              <FormField label="Comment" hideLabel error={e.ratings?.[i]?.comment?.message}><Input aria-label={`Comment for criterion ${i + 1}`} placeholder="Comment (optional)" {...register(`ratings.${i}.comment`)} /></FormField>
              <IconButton icon={<Trash2 size={14} />} aria-label={`Remove criterion ${i + 1}`} onClick={() => remove(i)} />
            </div>
          ))}
        </CardBody>
      </Card>

      <Card>
        <CardHeader title="Summary" />
        <CardBody>
          <FormGrid columns={1}>
            <FormField label="Overall rating" error={e.overall_rating?.message} hint={average !== null ? `Criteria average: ${average.toFixed(1)}` : undefined}>
              <Controller control={control} name="overall_rating" render={({ field }) => <StarInput label="Overall rating" value={field.value} onChange={field.onChange} />} />
            </FormField>
            <FormField label="Strengths" error={e.strengths?.message}><Textarea rows={3} {...register('strengths')} /></FormField>
            <FormField label="Areas to improve" error={e.improvements?.message}><Textarea rows={3} {...register('improvements')} /></FormField>
            <FormField label="Goals for next period" error={e.goals?.message}><Textarea rows={3} {...register('goals')} /></FormField>
          </FormGrid>
        </CardBody>
      </Card>

      <FormActions>
        <Button type="submit" variant="secondary" loading={formState.isSubmitting && !submit.isPending}>Save draft</Button>
        <Button
          loading={submit.isPending}
          onClick={handleSubmit((v) => {
            if (!window.confirm('Submit this review? It can no longer be edited, and the employee will be asked to acknowledge it.')) return;
            return save(v, true);
          })}
        >
          Save & submit
        </Button>
      </FormActions>
    </form>
  );
}
