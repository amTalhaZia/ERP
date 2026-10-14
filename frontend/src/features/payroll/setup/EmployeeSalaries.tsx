'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Plus, UserSearch } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, DateInput, Drawer, EmptyState, ErrorState, FormField, FormGrid, Input, SearchInput, Select, SkeletonCard, StatusBadge, Textarea, useToast,
} from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { applyServerErrors } from '@/lib/crud/resource';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney } from '@/lib/format';
import {
  payrollApi, salaryStructures, useCompanyCurrency, useCompanyId, useEmployeeSearch, useInvalidatePayroll, usePayComponentOptions,
  type EmployeeLite, type EmployeeSalary, type SalaryStructure,
} from '../api';
import { MONEY_RE, OverridesEditor, fmtRate, overrideFromApi, overrideRowSchema, overrideToApi, refineDistinctComponents, todayIso } from '../components';
import crud from '@/components/crud/crud.module.scss';
import styles from '../payroll.module.scss';

const schema = z
  .object({
    salary_structure_id: z.string(),
    base_salary: z.string().trim().refine((v) => MONEY_RE.test(v), 'A non-negative monthly amount (max 4 decimals)'),
    currency: z.string().trim().regex(/^[A-Za-z]{3}$/, 'Three-letter currency code'),
    effective_from: z.string().min(1, 'Effective date is required'),
    effective_to: z.string(),
    notes: z.string().max(1000),
    components: z.array(overrideRowSchema).max(100),
  })
  .superRefine((v, ctx) => {
    refineDistinctComponents(v.components, ctx);
    if (v.effective_to && v.effective_to < v.effective_from) ctx.addIssue({ code: 'custom', path: ['effective_to'], message: 'Must be on or after the effective date' });
  });
type Values = z.infer<typeof schema>;

function RecordSalaryDrawer({ employee, current, onClose }: { employee: EmployeeLite; current: EmployeeSalary | null; onClose: () => void }) {
  const companyCurrency = useCompanyCurrency();
  const toast = useToast();
  const invalidate = useInvalidatePayroll();
  const comps = usePayComponentOptions();
  const structures = salaryStructures.useList({ per_page: 100, sort: 'name' });
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, control, setError, setValue, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      salary_structure_id: current?.salary_structure_id ? String(current.salary_structure_id) : '',
      base_salary: '',
      currency: current?.currency ?? companyCurrency,
      effective_from: todayIso(),
      effective_to: '',
      notes: '',
      components: (current?.components ?? []).map(overrideFromApi),
    },
  });
  const { fields, append, remove } = useFieldArray({ control, name: 'components' });
  const rows = useWatch({ control, name: 'components' });
  const currency = useWatch({ control, name: 'currency' });
  const e = formState.errors;
  const structureRows: SalaryStructure[] = structures.data?.data ?? [];
  const structureOptions = structureRows
    .filter((s) => s.is_active || String(s.id) === String(current?.salary_structure_id ?? ''))
    .map((s) => ({ value: String(s.id), label: `${s.name} (${s.currency})` }));

  return (
    <Drawer open onClose={onClose} size="xl" title={`Record new salary — ${employee.name}`}
      description="Creates a new salary record from the effective date; the previous one ends the day before. History is never overwritten."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="salary-form" loading={formState.isSubmitting}>Record salary</Button>
        </>
      }>
      <form id="salary-form" noValidate onSubmit={handleSubmit(async (v) => {
        setFormError(null);
        try {
          await payrollApi.recordSalary(employee.id, {
            salary_structure_id: v.salary_structure_id ? Number(v.salary_structure_id) : null,
            base_salary: v.base_salary,
            currency: v.currency.trim().toUpperCase(),
            effective_from: v.effective_from,
            effective_to: v.effective_to || null,
            pay_frequency: 'monthly',
            notes: v.notes.trim() || null,
            components: v.components.map(overrideToApi),
          });
          void invalidate();
          toast.success('Salary recorded');
          onClose();
        } catch (err) {
          const x = err as ApiError;
          const left = applyServerErrors(x, setError as never, ['salary_structure_id', 'base_salary', 'currency', 'effective_from', 'effective_to', 'notes', 'components']);
          setFormError(x.status === 422 ? left : (left ?? x.message));
        }
      })}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={3}>
          <FormField label="Salary structure" error={e.salary_structure_id?.message} hint="Components of the structure apply unless overridden below.">
            <Select placeholder="No structure" {...register('salary_structure_id')} options={structureOptions}
              onChange={(ev) => {
                setValue('salary_structure_id', ev.target.value);
                const s = structureRows.find((x) => String(x.id) === ev.target.value);
                if (s) setValue('currency', s.currency);
              }} />
          </FormField>
          <FormField label="Base salary (monthly)" required error={e.base_salary?.message}><Input inputMode="decimal" {...register('base_salary')} autoFocus /></FormField>
          <FormField label="Currency" required error={e.currency?.message} hint="Must match the payroll run currency to be included."><Input maxLength={3} {...register('currency')} style={{ textTransform: 'uppercase' }} /></FormField>
          <Controller control={control} name="effective_from" render={({ field }) => (
            <FormField label="Effective from" required error={e.effective_from?.message}><DateInput value={field.value} onValueChange={(v) => field.onChange(v ?? '')} /></FormField>
          )} />
          <Controller control={control} name="effective_to" render={({ field }) => (
            <FormField label="Effective to" error={e.effective_to?.message} hint="Optional"><DateInput value={field.value || null} onValueChange={(v) => field.onChange(v ?? '')} /></FormField>
          )} />
          <FormField label="Notes" error={e.notes?.message}><Textarea rows={1} maxLength={1000} {...register('notes')} /></FormField>
        </FormGrid>
        <h4 className={styles.sectionTitle}>Employee-specific component overrides</h4>
        {comps.query.isLoading ? (
          <SkeletonCard />
        ) : (
          <OverridesEditor
            ids={fields.map((f) => f.id)}
            rows={rows ?? []}
            components={comps.rows}
            currency={currency || companyCurrency}
            error={(i, f) => e.components?.[i]?.[f]?.message}
            onChange={(i, f, value) => setValue(`components.${i}.${f}`, value, { shouldDirty: true, shouldValidate: formState.isSubmitted })}
            onAdd={() => append({ pay_component_id: '', amount: '', rate: '' })}
            onRemove={remove}
            emptyText="No overrides — the structure’s components and defaults apply. Add a component to override its amount/rate or to give this employee an extra component."
          />
        )}
        {(e.components?.message || e.components?.root?.message) && <Alert variant="danger" style={{ marginTop: 12 }}>{e.components?.message ?? e.components?.root?.message}</Alert>}
      </form>
    </Drawer>
  );
}

function SalaryHistory({ employee, canManage }: { employee: EmployeeLite; canManage: boolean }) {
  const companyId = useCompanyId();
  const [recording, setRecording] = useState(0);
  const q = useQuery({
    queryKey: [companyId, 'employee-salaries', employee.id],
    queryFn: () => payrollApi.salaries(employee.id).then((r) => r.data),
  });
  const rows = q.data ?? [];
  const today = todayIso();
  const currentRow = rows.find((s) => s.effective_from <= today && (!s.effective_to || s.effective_to >= today)) ?? rows[0] ?? null;

  return (
    <Card>
      <CardHeader title={employee.name} description={`${employee.employee_code}${employee.department ? ` · ${employee.department.name}` : ''}`}
        actions={canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setRecording((n) => n + 1)}>Record new salary</Button>} />
      <CardBody padding={rows.length ? 'none' : 'md'}>
        {q.error ? (
          <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} />
        ) : q.isLoading ? (
          <SkeletonCard />
        ) : rows.length === 0 ? (
          <EmptyState size="sm" title="No salary recorded" description="This employee is skipped by payroll runs until a salary is recorded." />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines} style={{ minWidth: 720 }}>
              <thead><tr><th>Effective</th><th>Structure</th><th className={crud.r}>Base salary</th><th>Overrides</th><th>Recorded</th><th /></tr></thead>
              <tbody>
                {rows.map((s) => (
                  <tr key={s.id}>
                    <td>{formatDate(s.effective_from)} – {s.effective_to ? formatDate(s.effective_to) : 'open'}</td>
                    <td>{s.salary_structure?.name ?? <span className={crud.muted}>None</span>}</td>
                    <td className={crud.r}>{formatMoney(s.base_salary, s.currency)}</td>
                    <td>
                      {s.components?.length
                        ? s.components.map((c) => `${c.code ?? c.pay_component_id}: ${c.amount !== null ? formatMoney(c.amount, s.currency) : c.rate !== null ? fmtRate(c.rate) : 'default'}`).join(', ')
                        : <span className={crud.muted}>—</span>}
                    </td>
                    <td><span className={styles.hint}>{formatDate(s.created_at)}{s.created_by ? ` · ${s.created_by.name}` : ''}</span>{s.notes && <div className={styles.hint}>{s.notes}</div>}</td>
                    <td>{s.id === currentRow?.id && <StatusBadge status="active" label="Current" size="sm" />}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>
      {recording > 0 && <RecordSalaryDrawer key={recording} employee={employee} current={currentRow} onClose={() => setRecording(0)} />}
    </Card>
  );
}

export function EmployeeSalariesTab({ canManage }: { canManage: boolean }) {
  const can = useCan();
  const canSearch = can('employees.view');
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState<EmployeeLite | null>(null);
  const q = useEmployeeSearch(search, canSearch);
  const results = q.data?.data ?? [];

  if (!canSearch) {
    return <Alert variant="info">Looking up employees needs the “View employees” permission. Ask an administrator to grant it to manage salaries.</Alert>;
  }

  return (
    <div className={styles.picker}>
      <Card>
        <CardHeader title="Employees" description="Pick an employee to see their salary history." />
        <CardBody>
          <SearchInput placeholder="Search name, code or email" defaultValue={search} onDebouncedChange={setSearch} aria-label="Search employees" />
          <div style={{ marginTop: 12 }}>
            {q.error ? (
              <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} />
            ) : q.isLoading ? (
              <SkeletonCard />
            ) : results.length === 0 ? (
              <p className={styles.hint}>No employees found.</p>
            ) : (
              <Select aria-label="Employee" size="md" value={selected ? String(selected.id) : ''} placeholder={`Select from ${results.length}${results.length >= 50 ? '+' : ''} result${results.length === 1 ? '' : 's'}`}
                onChange={(ev) => setSelected(results.find((r) => String(r.id) === ev.target.value) ?? null)}
                options={results.map((r) => ({ value: String(r.id), label: `${r.name} · ${r.employee_code}${r.status === 'terminated' ? ' (terminated)' : ''}` }))} />
            )}
          </div>
        </CardBody>
      </Card>
      {selected ? (
        <SalaryHistory key={selected.id} employee={selected} canManage={canManage} />
      ) : (
        <Card><CardBody><EmptyState icon={<UserSearch size={22} />} title="No employee selected" description="Search and pick an employee to view or record their salary." /></CardBody></Card>
      )}
    </div>
  );
}
