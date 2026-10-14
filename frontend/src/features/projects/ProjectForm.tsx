'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useQuery } from '@tanstack/react-query';
import {
  Alert, Button, CurrencyInput, DateInput, Drawer, FormField, FormGrid, FormSection, Input, Select, Switch, Textarea,
} from '@/components/ui';
import { get } from '@/lib/api/client';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { useCan } from '@/lib/auth/useCan';
import { useAppSelector } from '@/store';
import { selectBranches } from '@/store/sessionSlice';
import { PRIORITIES, PROJECT_STATUSES, projects, useCompanyId, type Project, type ProjectInput } from './api';
import { enumOptions, nullable, usePeople } from './shared';
import styles from './projects.module.scss';

const schema = z
  .object({
    name: z.string().trim().min(1, 'Name is required').max(150),
    code: z.string().trim().max(30).regex(/^[A-Za-z0-9_-]*$/, 'Letters, numbers, - and _ only'),
    description: z.string().trim().max(10000),
    customer_id: z.string(),
    department_id: z.string(),
    branch_id: z.string(),
    manager_id: z.string(),
    status: z.enum(PROJECT_STATUSES),
    priority: z.enum(PRIORITIES),
    start_date: z.string(),
    due_date: z.string(),
    budget: z.string().nullable(),
    currency: z.string().trim().regex(/^[A-Za-z]{3}$/, 'Use a 3-letter currency code'),
    color: z.string().regex(/^#[0-9A-Fa-f]{6}$/, 'Pick a colour'),
    use_color: z.boolean(),
    is_billable: z.boolean(),
  })
  .refine((v) => !v.start_date || !v.due_date || v.due_date >= v.start_date, { path: ['due_date'], message: 'Due date must be on or after the start date' });
type Values = z.infer<typeof schema>;

const toValues = (p: Project | null, currency: string): Values => ({
  name: p?.name ?? '',
  code: p?.code ?? '',
  description: p?.description ?? '',
  customer_id: p?.customer_id ? String(p.customer_id) : '',
  department_id: p?.department_id ? String(p.department_id) : '',
  branch_id: p?.branch_id ? String(p.branch_id) : '',
  manager_id: p?.manager_id ? String(p.manager_id) : '',
  status: p?.status ?? 'planning',
  priority: p?.priority ?? 'medium',
  start_date: p?.start_date ?? '',
  due_date: p?.due_date ?? '',
  budget: p?.budget ?? null,
  currency: p?.currency ?? currency,
  color: p?.color?.slice(0, 7) ?? '#4f46e5',
  use_color: !!p?.color,
  is_billable: p?.is_billable ?? false,
});

function useLookups(open: boolean) {
  const can = useCan();
  const companyId = useCompanyId();
  const canCustomers = can('customers.view');
  const canDepartments = can('departments.view');
  const customers = useQuery({
    queryKey: [companyId, 'customers', 'all'],
    queryFn: () => get<{ data: { id: number; name: string; code: string }[] }>('/customers/all').then((r) => r.data),
    enabled: open && canCustomers,
    staleTime: 60_000,
  });
  const departments = useQuery({
    queryKey: [companyId, 'departments', 'projects-picker'],
    queryFn: () => get<{ data: { id: number; name: string; code: string }[] }>('/departments', { per_page: 100, sort: 'name' }).then((r) => r.data),
    enabled: open && canDepartments,
    staleTime: 5 * 60_000,
  });
  return { customers, departments, canCustomers, canDepartments };
}

export function ProjectForm({ open, project, onClose, onSaved }: { open: boolean; project: Project | null; onClose: () => void; onSaved?: (p: Project) => void }) {
  const companyCurrency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const branches = useAppSelector(selectBranches);
  const lookups = useLookups(open);
  const people = usePeople(project?.manager ? [project.manager] : []);
  const [formError, setFormError] = useState<string | null>(null);
  const form = useForm<Values>({ resolver: zodResolver(schema), defaultValues: toValues(project, companyCurrency) });
  const { register, handleSubmit, control, setError, formState, watch } = form;
  const create = projects.useCreate();
  const update = projects.useUpdate();
  const currency = watch('currency');
  const useColor = watch('use_color');

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const body: ProjectInput = {
      name: v.name,
      code: nullable(v.code),
      description: nullable(v.description),
      department_id: v.department_id ? Number(v.department_id) : null,
      branch_id: v.branch_id ? Number(v.branch_id) : null,
      manager_id: v.manager_id ? Number(v.manager_id) : null,
      status: v.status,
      priority: v.priority,
      start_date: v.start_date || null,
      due_date: v.due_date || null,
      budget: v.budget || null,
      currency: v.currency.toUpperCase(),
      color: v.use_color ? v.color : null,
      is_billable: v.is_billable,
    };
    // Only send customer when the picker was usable, so we never clear a value we couldn't show.
    if (lookups.canCustomers) body.customer_id = v.customer_id ? Number(v.customer_id) : null;
    try {
      const saved = project ? await update.mutateAsync({ id: project.id, body }) : await create.mutateAsync(body);
      onSaved?.(saved);
      onClose();
    } catch (e) {
      const err = e as ApiError;
      setFormError(err.status === 409 ? err.message : applyServerErrors(err, setError as never, Object.keys(toValues(null, 'USD'))));
    }
  };

  const e = formState.errors;
  const customerOptions = (lookups.customers.data ?? []).map((c) => ({ value: String(c.id), label: `${c.name} · ${c.code}` }));
  if (project?.customer_id && !customerOptions.some((o) => o.value === String(project.customer_id))) {
    customerOptions.unshift({ value: String(project.customer_id), label: `Customer #${project.customer_id}` });
  }
  const departmentOptions = (lookups.departments.data ?? []).map((d) => ({ value: String(d.id), label: d.name }));
  if (project?.department && !departmentOptions.some((o) => o.value === String(project.department!.id))) {
    departmentOptions.unshift({ value: String(project.department.id), label: project.department.name });
  }

  return (
    <Drawer
      open={open}
      onClose={onClose}
      size="lg"
      title={project ? `Edit ${project.name}` : 'New project'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="project-form" loading={formState.isSubmitting}>{project ? 'Save changes' : 'Create project'}</Button>
        </>
      }
    >
      <form id="project-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormSection title="Details">
          <FormGrid columns={2}>
            <FormField label="Name" required error={e.name?.message} span="full">
              <Input maxLength={150} {...register('name')} autoFocus />
            </FormField>
            <FormField label="Code" error={e.code?.message} hint={project ? `Number ${project.number}` : 'Optional short code; a number is assigned automatically'}>
              <Input maxLength={30} {...register('code')} />
            </FormField>
            <FormField label="Manager" error={e.manager_id?.message} hint={people.limited ? 'Only you can be picked (no access to the user list)' : 'Defaults to you'}>
              <Select {...register('manager_id')} options={[{ value: '', label: 'Me (default)' }, ...people.options]} />
            </FormField>
            <FormField label="Description" span="full" error={e.description?.message}>
              <Textarea rows={4} maxLength={10000} {...register('description')} />
            </FormField>
            <FormField label="Status" error={e.status?.message}>
              <Select {...register('status')} options={enumOptions(PROJECT_STATUSES)} />
            </FormField>
            <FormField label="Priority" error={e.priority?.message}>
              <Select {...register('priority')} options={enumOptions(PRIORITIES)} />
            </FormField>
            <FormField label="Start date" error={e.start_date?.message}>
              {(p) => <Controller control={control} name="start_date" render={({ field }) => <DateInput {...p} value={field.value} onValueChange={(v) => field.onChange(v ?? '')} />} />}
            </FormField>
            <FormField label="Due date" error={e.due_date?.message}>
              {(p) => <Controller control={control} name="due_date" render={({ field }) => <DateInput {...p} value={field.value} onValueChange={(v) => field.onChange(v ?? '')} />} />}
            </FormField>
          </FormGrid>
        </FormSection>
        <FormSection title="Organisation">
          <FormGrid columns={2}>
            {lookups.canCustomers && (
              <FormField label="Customer" error={e.customer_id?.message}>
                <Select {...register('customer_id')} options={[{ value: '', label: lookups.customers.isLoading ? 'Loading…' : 'Internal project' }, ...customerOptions]} />
              </FormField>
            )}
            {lookups.canDepartments ? (
              <FormField label="Department" error={e.department_id?.message}>
                <Select {...register('department_id')} options={[{ value: '', label: 'None' }, ...departmentOptions]} />
              </FormField>
            ) : project?.department ? (
              <FormField label="Department"><Input value={project.department.name} readOnly /></FormField>
            ) : null}
            <FormField label="Branch" error={e.branch_id?.message}>
              <Select {...register('branch_id')} options={[{ value: '', label: 'All branches' }, ...branches.map((b) => ({ value: String(b.id), label: b.name }))]} />
            </FormField>
          </FormGrid>
        </FormSection>
        <FormSection title="Budget & billing">
          <FormGrid columns={2}>
            <FormField label="Budget" error={e.budget?.message}>
              {(p) => <Controller control={control} name="budget" render={({ field }) => <CurrencyInput {...p} currency={/^[A-Za-z]{3}$/.test(currency) ? currency.toUpperCase() : companyCurrency} value={field.value} onValueChange={field.onChange} />} />}
            </FormField>
            <FormField label="Currency" error={e.currency?.message}>
              <Input maxLength={3} {...register('currency')} style={{ textTransform: 'uppercase' }} />
            </FormField>
            <Controller control={control} name="is_billable" render={({ field }) => (
              <Switch checked={field.value} onCheckedChange={field.onChange} label="Billable to customer" description="Billable time feeds the cost shown against the budget." />
            )} />
            <div className={styles.row}>
              <Controller control={control} name="use_color" render={({ field }) => (
                <Switch checked={field.value} onCheckedChange={field.onChange} label="Colour tag" />
              )} />
              {useColor && <input type="color" className={styles.colorInput} aria-label="Project colour" {...register('color')} />}
              {e.color && <span className={styles.danger}>{e.color.message}</span>}
            </div>
          </FormGrid>
        </FormSection>
      </form>
    </Drawer>
  );
}
