'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Alert, Button, Checkbox, DateInput, Drawer, FormField, FormGrid, Input, Select, Switch } from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { selectBranches } from '@/store/sessionSlice';
import { designations, holidays, leaveTypes, shifts, type Designation, type Holiday, type LeaveType, type Shift } from './api';

/** Shared drawer footer + error banner handling for small CRUD forms. */
function useServerError() {
  const [formError, setFormError] = useState<string | null>(null);
  const handle = (e: unknown, setError: unknown, fields: string[]) => {
    const err = e as ApiError;
    const left = applyServerErrors(err, setError as never, fields);
    setFormError(err.status === 422 ? left : (left ?? err.message));
  };
  return { formError, setFormError, handle };
}

function Footer({ formId, onClose, submitting, editing, noun }: { formId: string; onClose: () => void; submitting: boolean; editing: boolean; noun: string }) {
  return (
    <>
      <Button variant="secondary" onClick={onClose}>Cancel</Button>
      <Button type="submit" form={formId} loading={submitting}>{editing ? 'Save changes' : `Create ${noun}`}</Button>
    </>
  );
}

// ---- Designation -------------------------------------------------------------------

const designationSchema = z.object({
  title: z.string().trim().min(1, 'Title is required').max(150),
  code: z.string().trim().min(1, 'Code is required').max(20).regex(/^[A-Za-z0-9_-]+$/, 'Letters, numbers, - and _ only'),
  level: z.string().refine((v) => /^\d+$/.test(v) && Number(v) >= 1 && Number(v) <= 99, 'Whole number 1–99'),
  is_active: z.boolean(),
});

export function DesignationForm({ open, row, onClose }: { open: boolean; row: Designation | null; onClose: () => void }) {
  const { formError, setFormError, handle } = useServerError();
  const { register, handleSubmit, setError, control, formState } = useForm<z.infer<typeof designationSchema>>({
    resolver: zodResolver(designationSchema),
    defaultValues: { title: row?.title ?? '', code: row?.code ?? '', level: String(row?.level ?? 1), is_active: row?.is_active ?? true },
  });
  const create = designations.useCreate({ onSuccess: onClose });
  const update = designations.useUpdate({ onSuccess: onClose });
  const e = formState.errors;

  return (
    <Drawer open={open} onClose={onClose} title={row ? `Edit ${row.title}` : 'New designation'} description="Job titles used on employee profiles and the org chart." size="sm"
      footer={<Footer formId="designation-form" onClose={onClose} submitting={formState.isSubmitting} editing={!!row} noun="designation" />}>
      <form id="designation-form" noValidate onSubmit={handleSubmit(async (v) => {
        setFormError(null);
        const body = { ...v, level: Number(v.level) };
        try {
          if (row) await update.mutateAsync({ id: row.id, body });
          else await create.mutateAsync(body);
        } catch (err) {
          handle(err, setError, Object.keys(designationSchema.shape));
        }
      })}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={1}>
          <FormField label="Title" required error={e.title?.message}><Input {...register('title')} autoFocus /></FormField>
          <FormField label="Code" required error={e.code?.message}><Input {...register('code')} placeholder="e.g. SR-ENG" /></FormField>
          <FormField label="Level" hint="1 = most senior; used for ordering" error={e.level?.message}><Input inputMode="numeric" {...register('level')} /></FormField>
          <Controller control={control} name="is_active" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" description="Inactive designations are hidden from the employee form." />} />
        </FormGrid>
      </form>
    </Drawer>
  );
}

// ---- Leave type --------------------------------------------------------------------

const dec = (label: string) => z.string().trim().refine((v) => /^\d{1,3}(\.\d)?$/.test(v), `${label}: up to 999.9 with one decimal`);
const leaveTypeSchema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(100),
  code: z.string().trim().min(1, 'Code is required').max(20).regex(/^[A-Za-z0-9_-]+$/, 'Letters, numbers, - and _ only'),
  days_per_year: dec('Days'),
  is_paid: z.boolean(),
  requires_approval: z.boolean(),
  carry_forward: z.boolean(),
  max_carry_forward_days: z.string().trim().refine((v) => v === '' || /^\d{1,3}(\.\d)?$/.test(v), 'Up to 999.9'),
  color: z.string().regex(/^#[0-9a-fA-F]{6}$/, 'Pick a colour'),
  is_active: z.boolean(),
});

export function LeaveTypeForm({ open, row, onClose }: { open: boolean; row: LeaveType | null; onClose: () => void }) {
  const { formError, setFormError, handle } = useServerError();
  const { register, handleSubmit, setError, control, formState } = useForm<z.infer<typeof leaveTypeSchema>>({
    resolver: zodResolver(leaveTypeSchema),
    defaultValues: {
      name: row?.name ?? '',
      code: row?.code ?? '',
      days_per_year: row?.days_per_year ? String(Number(row.days_per_year)) : '0',
      is_paid: row?.is_paid ?? true,
      requires_approval: row?.requires_approval ?? true,
      carry_forward: row?.carry_forward ?? false,
      max_carry_forward_days: row?.max_carry_forward_days ? String(Number(row.max_carry_forward_days)) : '',
      color: (row?.color ?? '#6366f1').slice(0, 7),
      is_active: row?.is_active ?? true,
    },
  });
  const create = leaveTypes.useCreate({ onSuccess: onClose });
  const update = leaveTypes.useUpdate({ onSuccess: onClose });
  const e = formState.errors;

  return (
    <Drawer open={open} onClose={onClose} title={row ? `Edit ${row.name}` : 'New leave type'} size="md"
      footer={<Footer formId="leave-type-form" onClose={onClose} submitting={formState.isSubmitting} editing={!!row} noun="leave type" />}>
      <form id="leave-type-form" noValidate onSubmit={handleSubmit(async (v) => {
        setFormError(null);
        const body = { ...v, max_carry_forward_days: v.carry_forward && v.max_carry_forward_days ? v.max_carry_forward_days : null };
        try {
          if (row) await update.mutateAsync({ id: row.id, body });
          else await create.mutateAsync(body);
        } catch (err) {
          handle(err, setError, Object.keys(leaveTypeSchema.shape));
        }
      })}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          <FormField label="Name" required error={e.name?.message}><Input {...register('name')} autoFocus /></FormField>
          <FormField label="Code" required error={e.code?.message}><Input {...register('code')} placeholder="e.g. AL" /></FormField>
          <FormField label="Days per year" required error={e.days_per_year?.message}><Input inputMode="decimal" {...register('days_per_year')} /></FormField>
          <FormField label="Colour" error={e.color?.message}><Input type="color" {...register('color')} style={{ padding: 2, height: 36 }} /></FormField>
          <FormField label="Max carry-forward days" hint="Only when carry-forward is on" error={e.max_carry_forward_days?.message}>
            <Input inputMode="decimal" {...register('max_carry_forward_days')} />
          </FormField>
        </FormGrid>
        <div style={{ display: 'grid', gap: 12, marginTop: 16 }}>
          <Controller control={control} name="is_paid" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Paid leave" />} />
          <Controller control={control} name="requires_approval" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Requires approval" description="When off, requests are approved automatically." />} />
          <Controller control={control} name="carry_forward" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Carry forward unused days" />} />
          <Controller control={control} name="is_active" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" description="Inactive types cannot be requested." />} />
        </div>
      </form>
    </Drawer>
  );
}

// ---- Shift -------------------------------------------------------------------------

export const WEEKDAYS = [
  { value: 1, short: 'M', label: 'Mon' },
  { value: 2, short: 'T', label: 'Tue' },
  { value: 3, short: 'W', label: 'Wed' },
  { value: 4, short: 'T', label: 'Thu' },
  { value: 5, short: 'F', label: 'Fri' },
  { value: 6, short: 'S', label: 'Sat' },
  { value: 7, short: 'S', label: 'Sun' },
];

const time = z.string().regex(/^\d{2}:\d{2}$/, 'Use HH:MM');
const shiftSchema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(100),
  start_time: time,
  end_time: time,
  break_minutes: z.string().refine((v) => /^\d+$/.test(v) && Number(v) <= 480, '0–480 minutes'),
  days_of_week: z.array(z.number()).min(1, 'Choose at least one working day'),
  is_default: z.boolean(),
});

export function ShiftForm({ open, row, onClose }: { open: boolean; row: Shift | null; onClose: () => void }) {
  const { formError, setFormError, handle } = useServerError();
  const { register, handleSubmit, setError, control, formState } = useForm<z.infer<typeof shiftSchema>>({
    resolver: zodResolver(shiftSchema),
    defaultValues: {
      name: row?.name ?? '',
      start_time: row?.start_time ?? '09:00',
      end_time: row?.end_time ?? '17:00',
      break_minutes: String(row?.break_minutes ?? 60),
      days_of_week: row?.days_of_week ?? [1, 2, 3, 4, 5],
      is_default: row?.is_default ?? false,
    },
  });
  const create = shifts.useCreate({ onSuccess: onClose });
  const update = shifts.useUpdate({ onSuccess: onClose });
  const e = formState.errors;

  return (
    <Drawer open={open} onClose={onClose} title={row ? `Edit ${row.name}` : 'New shift'} description="Working hours drive late-minute and worked-time calculations, and which days count as leave." size="md"
      footer={<Footer formId="shift-form" onClose={onClose} submitting={formState.isSubmitting} editing={!!row} noun="shift" />}>
      <form id="shift-form" noValidate onSubmit={handleSubmit(async (v) => {
        setFormError(null);
        const body = { ...v, break_minutes: Number(v.break_minutes), days_of_week: [...v.days_of_week].sort() };
        try {
          if (row) await update.mutateAsync({ id: row.id, body });
          else await create.mutateAsync(body);
        } catch (err) {
          handle(err, setError, Object.keys(shiftSchema.shape));
        }
      })}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={3}>
          <FormField label="Name" required span="full" error={e.name?.message}><Input {...register('name')} autoFocus placeholder="e.g. General" /></FormField>
          <FormField label="Starts" required error={e.start_time?.message}><Input type="time" {...register('start_time')} /></FormField>
          <FormField label="Ends" required error={e.end_time?.message} hint="Earlier than start = overnight"><Input type="time" {...register('end_time')} /></FormField>
          <FormField label="Break (min)" error={e.break_minutes?.message}><Input inputMode="numeric" {...register('break_minutes')} /></FormField>
        </FormGrid>
        <fieldset style={{ border: 0, padding: 0, margin: '16px 0 0' }}>
          <legend style={{ fontWeight: 600, fontSize: 13, marginBottom: 8 }}>Working days</legend>
          <Controller control={control} name="days_of_week" render={({ field }) => (
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12 }}>
              {WEEKDAYS.map((d) => (
                <Checkbox
                  key={d.value}
                  label={d.label}
                  checked={field.value.includes(d.value)}
                  onChange={(ev) => field.onChange(ev.target.checked ? [...field.value, d.value] : field.value.filter((x) => x !== d.value))}
                />
              ))}
            </div>
          )} />
          {e.days_of_week?.message && <p role="alert" style={{ color: 'var(--color-danger-text)', fontSize: 12, marginTop: 4 }}>{e.days_of_week.message}</p>}
        </fieldset>
        <div style={{ marginTop: 16 }}>
          <Controller control={control} name="is_default" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Default shift" description="Used for employees without an assignment. Only one shift can be the default." />} />
        </div>
      </form>
    </Drawer>
  );
}

// ---- Holiday -----------------------------------------------------------------------

const holidaySchema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(150),
  date: z.string().min(1, 'Date is required'),
  is_recurring: z.boolean(),
  branch_id: z.string(),
});

export function HolidayForm({ open, row, onClose }: { open: boolean; row: Holiday | null; onClose: () => void }) {
  const branches = useAppSelector(selectBranches);
  const { formError, setFormError, handle } = useServerError();
  const { register, handleSubmit, setError, control, formState } = useForm<z.infer<typeof holidaySchema>>({
    resolver: zodResolver(holidaySchema),
    defaultValues: { name: row?.name ?? '', date: row?.date ?? '', is_recurring: row?.is_recurring ?? false, branch_id: row?.branch_id ? String(row.branch_id) : '' },
  });
  const create = holidays.useCreate({ onSuccess: onClose });
  const update = holidays.useUpdate({ onSuccess: onClose });
  const e = formState.errors;

  return (
    <Drawer open={open} onClose={onClose} title={row ? `Edit ${row.name}` : 'New holiday'} size="sm"
      footer={<Footer formId="holiday-form" onClose={onClose} submitting={formState.isSubmitting} editing={!!row} noun="holiday" />}>
      <form id="holiday-form" noValidate onSubmit={handleSubmit(async (v) => {
        setFormError(null);
        const body = { ...v, branch_id: v.branch_id ? Number(v.branch_id) : null };
        try {
          if (row) await update.mutateAsync({ id: row.id, body });
          else await create.mutateAsync(body);
        } catch (err) {
          handle(err, setError, Object.keys(holidaySchema.shape));
        }
      })}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={1}>
          <FormField label="Name" required error={e.name?.message}><Input {...register('name')} autoFocus /></FormField>
          <FormField label="Date" required error={e.date?.message}>
            <Controller control={control} name="date" render={({ field }) => <DateInput value={field.value || null} onValueChange={(d) => field.onChange(d ?? '')} />} />
          </FormField>
          <FormField label="Branch" hint="Leave empty for a company-wide holiday" error={e.branch_id?.message}>
            <Select {...register('branch_id')} placeholder="All branches" options={branches.map((b) => ({ value: String(b.id), label: b.name }))} />
          </FormField>
          <Controller control={control} name="is_recurring" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Recurs every year" description="Same day and month each year." />} />
        </FormGrid>
      </form>
    </Drawer>
  );
}
