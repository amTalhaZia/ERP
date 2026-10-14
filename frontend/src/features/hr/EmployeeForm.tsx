'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
  Alert, Button, Card, CardBody, CurrencyInput, DateInput, FormActions, FormField, FormGrid, FormSection, Input, Select, Textarea,
} from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { useCan } from '@/lib/auth/useCan';
import { useAppSelector } from '@/store';
import { selectBranches } from '@/store/sessionSlice';
import { departments } from '@/features/company/api';
import { companyUsers } from '@/features/access/api';
import {
  EDITABLE_STATUSES, EMPLOYMENT_TYPES, GENDERS, MARITAL_STATUSES, employees, useCompanyCurrency, useDesignationOptions, useDirectory,
  type Employee,
} from './api';
import { nul, useUnsavedChangesWarning } from './components';
import styles from './hr.module.scss';

const opt = (max: number) => z.string().trim().max(max);

const schema = z
  .object({
    first_name: z.string().trim().min(1, 'First name is required').max(100),
    last_name: z.string().trim().min(1, 'Last name is required').max(100),
    email: z.email('Enter a valid email').max(150),
    phone: opt(40),
    date_of_birth: z.string(),
    gender: z.string(),
    marital_status: z.string(),
    national_id: opt(60),
    address_line1: opt(200),
    address_line2: opt(200),
    city: opt(100),
    state: opt(100),
    postal_code: opt(20),
    country: z.string().trim().refine((v) => v === '' || /^[A-Za-z]{2}$/.test(v), 'Use a 2-letter country code'),
    emergency_contact_name: opt(150),
    emergency_contact_phone: opt(40),
    emergency_contact_relationship: opt(60),
    branch_id: z.string(),
    department_id: z.string(),
    designation_id: z.string(),
    manager_id: z.string(),
    employment_type: z.enum(['full_time', 'part_time', 'contract', 'intern']),
    status: z.enum(['probation', 'active', 'on_leave', 'suspended']),
    hired_at: z.string().min(1, 'Hire date is required'),
    probation_ends_at: z.string(),
    base_salary: z.string().nullable(),
    currency: z.string().trim().refine((v) => v === '' || /^[A-Za-z]{3}$/.test(v), 'Use a 3-letter currency code'),
    bank_name: opt(150),
    bank_account_number: opt(60),
    bank_iban: opt(60),
    tax_identifier: opt(60),
    user_id: z.string(),
    notes: opt(5000),
  })
  .refine((v) => !v.probation_ends_at || !v.hired_at || v.probation_ends_at >= v.hired_at, {
    path: ['probation_ends_at'],
    message: 'Probation must end on or after the hire date',
  })
  .refine((v) => !v.date_of_birth || v.date_of_birth < new Date().toISOString().slice(0, 10), {
    path: ['date_of_birth'],
    message: 'Date of birth must be in the past',
  });
type Values = z.infer<typeof schema>;
const FIELDS = [
  'first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'gender', 'marital_status', 'national_id', 'address_line1', 'address_line2', 'city', 'state',
  'postal_code', 'country', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship', 'branch_id', 'department_id',
  'designation_id', 'manager_id', 'employment_type', 'status', 'hired_at', 'probation_ends_at', 'base_salary', 'currency', 'bank_name',
  'bank_account_number', 'bank_iban', 'tax_identifier', 'user_id', 'notes',
];

const s = (v: number | null | undefined) => (v === null || v === undefined ? '' : String(v));

function toValues(e: Employee | null, currency: string): Values {
  return {
    first_name: e?.first_name ?? '',
    last_name: e?.last_name ?? '',
    email: e?.email ?? '',
    phone: e?.phone ?? '',
    date_of_birth: e?.date_of_birth ?? '',
    gender: e?.gender ?? '',
    marital_status: e?.marital_status ?? '',
    national_id: '',
    address_line1: e?.address.line1 ?? '',
    address_line2: e?.address.line2 ?? '',
    city: e?.address.city ?? '',
    state: e?.address.state ?? '',
    postal_code: e?.address.postal_code ?? '',
    country: e?.address.country ?? '',
    emergency_contact_name: e?.emergency_contact.name ?? '',
    emergency_contact_phone: e?.emergency_contact.phone ?? '',
    emergency_contact_relationship: e?.emergency_contact.relationship ?? '',
    branch_id: s(e?.branch_id),
    department_id: s(e?.department_id),
    designation_id: s(e?.designation_id),
    manager_id: s(e?.manager_id),
    employment_type: e?.employment_type ?? 'full_time',
    status: e && e.status !== 'terminated' ? e.status : 'probation',
    hired_at: e?.hired_at ?? new Date().toISOString().slice(0, 10),
    probation_ends_at: e?.probation_ends_at ?? '',
    base_salary: e?.base_salary ?? null,
    currency: e?.currency ?? currency,
    bank_name: e?.bank_name ?? '',
    bank_account_number: '',
    bank_iban: '',
    tax_identifier: '',
    user_id: s(e?.user_id),
    notes: e?.notes ?? '',
  };
}

const idOrNull = (v: string) => (v ? Number(v) : null);

/** Full-page create/edit form. Remount with `key` to reset between records. */
export function EmployeeForm({ employee }: { employee: Employee | null }) {
  const router = useRouter();
  const can = useCan();
  const currency = useCompanyCurrency();
  const branches = useAppSelector(selectBranches);
  const [formError, setFormError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const deps = departments.useList({ per_page: 100, sort: 'name' });
  const desig = useDesignationOptions();
  const directory = useDirectory();
  const users = companyUsers.useList({ per_page: 100, sort: 'name' }, { enabled: can('users.view') });
  const create = employees.useCreate();
  const update = employees.useUpdate();

  const { register, handleSubmit, setError, control, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: toValues(employee, currency),
  });
  useUnsavedChangesWarning(formState.isDirty && !saved);
  const salaryCurrency = useWatch({ control, name: 'currency' });

  const sensitive = employee?.sensitive;
  const onFile = (v: string | null | undefined) => (v ? `On file: ${v}. Leave empty to keep.` : undefined);

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const body: Record<string, unknown> = {
      first_name: v.first_name,
      last_name: v.last_name,
      email: v.email,
      phone: nul(v.phone),
      date_of_birth: v.date_of_birth || null,
      gender: v.gender || null,
      marital_status: v.marital_status || null,
      address_line1: nul(v.address_line1),
      address_line2: nul(v.address_line2),
      city: nul(v.city),
      state: nul(v.state),
      postal_code: nul(v.postal_code),
      country: v.country ? v.country.toUpperCase() : null,
      emergency_contact_name: nul(v.emergency_contact_name),
      emergency_contact_phone: nul(v.emergency_contact_phone),
      emergency_contact_relationship: nul(v.emergency_contact_relationship),
      branch_id: idOrNull(v.branch_id),
      department_id: idOrNull(v.department_id),
      designation_id: idOrNull(v.designation_id),
      manager_id: idOrNull(v.manager_id),
      employment_type: v.employment_type,
      hired_at: v.hired_at,
      probation_ends_at: v.probation_ends_at || null,
      base_salary: v.base_salary || null,
      currency: v.currency ? v.currency.toUpperCase() : null,
      bank_name: nul(v.bank_name),
      user_id: idOrNull(v.user_id),
      notes: nul(v.notes),
    };
    // Terminated employees keep their status; reactivate is a separate action.
    if (!employee || employee.status !== 'terminated') body.status = v.status;
    // Sensitive values are write-only (encrypted at rest): send only when re-entered.
    for (const k of ['national_id', 'bank_account_number', 'bank_iban', 'tax_identifier'] as const) {
      if (v[k]) body[k] = v[k].trim();
    }
    try {
      const row = employee ? await update.mutateAsync({ id: employee.id, body }) : await create.mutateAsync(body);
      setSaved(true);
      router.push(`/hr/employees/${row.id}`);
    } catch (e) {
      const err = e as ApiError;
      const left = applyServerErrors(err, setError as never, FIELDS);
      setFormError(err.status === 422 ? (left ?? 'Please correct the highlighted fields.') : left);
    }
  };

  const cancel = () => {
    if (formState.isDirty && !window.confirm('Discard your unsaved changes?')) return;
    router.push(employee ? `/hr/employees/${employee.id}` : '/hr/employees');
  };

  const e = formState.errors;
  const managerOptions = (directory.data ?? []).filter((d) => d.id !== employee?.id).map((d) => ({ value: String(d.id), label: `${d.name} · ${d.employee_code}` }));
  // Keep the current manager selectable even if they left (no longer in the directory).
  if (employee?.manager && !managerOptions.some((o) => o.value === String(employee.manager_id))) {
    managerOptions.unshift({ value: String(employee.manager.id), label: `${employee.manager.name} · ${employee.manager.employee_code}` });
  }
  const designationOptions = [...desig.options];
  if (employee?.designation && !designationOptions.some((o) => o.value === String(employee.designation_id))) {
    designationOptions.unshift({ value: String(employee.designation.id), label: employee.designation.title });
  }
  const userOptions = (users.data?.data ?? []).map((u) => ({ value: String(u.id), label: `${u.name} · ${u.email}` }));
  if (employee?.user && !userOptions.some((o) => o.value === String(employee.user_id))) {
    userOptions.unshift({ value: String(employee.user.id), label: employee.user.email });
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className={styles.stack}>
      {formError && <Alert variant="danger">{formError}</Alert>}
      <Card>
        <CardBody>
          <FormSection title="Personal">
            <FormGrid columns={3}>
              <FormField label="First name" required error={e.first_name?.message}><Input {...register('first_name')} autoFocus={!employee} autoComplete="off" /></FormField>
              <FormField label="Last name" required error={e.last_name?.message}><Input {...register('last_name')} autoComplete="off" /></FormField>
              <FormField label="Date of birth" error={e.date_of_birth?.message}>
                <Controller control={control} name="date_of_birth" render={({ field }) => <DateInput value={field.value || null} onValueChange={(d) => field.onChange(d ?? '')} />} />
              </FormField>
              <FormField label="Gender" error={e.gender?.message}><Select {...register('gender')} placeholder="Not specified" options={GENDERS} /></FormField>
              <FormField label="Marital status" error={e.marital_status?.message}><Select {...register('marital_status')} placeholder="Not specified" options={MARITAL_STATUSES} /></FormField>
            </FormGrid>
          </FormSection>

          <FormSection title="Contact & address">
            <FormGrid columns={2}>
              <FormField label="Work email" required error={e.email?.message}><Input type="email" {...register('email')} autoComplete="off" /></FormField>
              <FormField label="Phone" error={e.phone?.message}><Input type="tel" {...register('phone')} /></FormField>
              <FormField label="Address line 1" span="full" error={e.address_line1?.message}><Input {...register('address_line1')} /></FormField>
              <FormField label="Address line 2" span="full" error={e.address_line2?.message}><Input {...register('address_line2')} /></FormField>
              <FormField label="City" error={e.city?.message}><Input {...register('city')} /></FormField>
              <FormField label="State / region" error={e.state?.message}><Input {...register('state')} /></FormField>
              <FormField label="Postal code" error={e.postal_code?.message}><Input {...register('postal_code')} /></FormField>
              <FormField label="Country" hint="ISO code, e.g. PK, US" error={e.country?.message}><Input maxLength={2} {...register('country')} style={{ textTransform: 'uppercase' }} /></FormField>
            </FormGrid>
          </FormSection>

          <FormSection title="Emergency contact">
            <FormGrid columns={3}>
              <FormField label="Name" error={e.emergency_contact_name?.message}><Input {...register('emergency_contact_name')} /></FormField>
              <FormField label="Phone" error={e.emergency_contact_phone?.message}><Input type="tel" {...register('emergency_contact_phone')} /></FormField>
              <FormField label="Relationship" error={e.emergency_contact_relationship?.message}><Input {...register('emergency_contact_relationship')} placeholder="e.g. Spouse" /></FormField>
            </FormGrid>
          </FormSection>

          <FormSection title="Employment">
            <FormGrid columns={3}>
              <FormField label="Branch" error={e.branch_id?.message}>
                <Select {...register('branch_id')} placeholder="No branch" options={branches.map((b) => ({ value: String(b.id), label: b.name }))} />
              </FormField>
              <FormField label="Department" error={e.department_id?.message} hint={deps.error ? 'Departments could not be loaded.' : undefined}>
                <Select {...register('department_id')} placeholder="No department" options={(deps.data?.data ?? []).map((d) => ({ value: String(d.id), label: d.name }))} />
              </FormField>
              <FormField label="Designation" error={e.designation_id?.message} hint={<Link href="/hr/designations" target="_blank">Manage designations</Link>}>
                <Select {...register('designation_id')} placeholder="No designation" options={designationOptions} />
              </FormField>
              <FormField label="Reports to" error={e.manager_id?.message}>
                <Select {...register('manager_id')} placeholder={directory.isLoading ? 'Loading…' : 'No manager'} options={managerOptions} />
              </FormField>
              <FormField label="Employment type" required error={e.employment_type?.message}>
                <Select {...register('employment_type')} options={EMPLOYMENT_TYPES} />
              </FormField>
              <FormField
                label="Status"
                error={e.status?.message}
                hint={employee?.status === 'terminated' ? 'Terminated — use Reactivate on the profile.' : !employee ? 'New hires start on probation or active.' : undefined}
              >
                <Select
                  {...register('status')}
                  disabled={employee?.status === 'terminated'}
                  options={employee ? EDITABLE_STATUSES : EDITABLE_STATUSES.filter((o) => o.value === 'probation' || o.value === 'active')}
                />
              </FormField>
              <FormField label="Hire date" required error={e.hired_at?.message}>
                <Controller control={control} name="hired_at" render={({ field }) => <DateInput value={field.value || null} onValueChange={(d) => field.onChange(d ?? '')} />} />
              </FormField>
              <FormField label="Probation ends" error={e.probation_ends_at?.message}>
                <Controller control={control} name="probation_ends_at" render={({ field }) => <DateInput value={field.value || null} onValueChange={(d) => field.onChange(d ?? '')} />} />
              </FormField>
            </FormGrid>
          </FormSection>

          <FormSection title="Payroll basics">
            <FormGrid columns={3}>
              <FormField label="Base salary" error={e.base_salary?.message}>
                <Controller control={control} name="base_salary" render={({ field }) => (
                  <CurrencyInput currency={/^[A-Za-z]{3}$/.test(salaryCurrency ?? '') ? salaryCurrency.toUpperCase() : currency} value={field.value} onValueChange={field.onChange} />
                )} />
              </FormField>
              <FormField label="Currency" error={e.currency?.message}><Input maxLength={3} {...register('currency')} style={{ textTransform: 'uppercase' }} /></FormField>
            </FormGrid>
          </FormSection>

          <FormSection title="Identity, bank & tax" description="Sensitive numbers are encrypted and never shown in full again — only the last 4 characters are displayed. Leave a field empty to keep the value on file.">
            <FormGrid columns={2}>
              <FormField label="National ID" error={e.national_id?.message} hint={onFile(sensitive?.national_id)}><Input autoComplete="off" {...register('national_id')} /></FormField>
              <FormField label="Tax identifier" error={e.tax_identifier?.message} hint={onFile(sensitive?.tax_identifier)}><Input autoComplete="off" {...register('tax_identifier')} /></FormField>
              <FormField label="Bank name" error={e.bank_name?.message}><Input {...register('bank_name')} /></FormField>
              <FormField label="Account number" error={e.bank_account_number?.message} hint={onFile(sensitive?.bank_account_number)}><Input autoComplete="off" {...register('bank_account_number')} /></FormField>
              <FormField label="IBAN" error={e.bank_iban?.message} hint={onFile(sensitive?.bank_iban)}><Input autoComplete="off" {...register('bank_iban')} /></FormField>
            </FormGrid>
          </FormSection>

          <FormSection title="Linked user" description="Link the employee's login to enable self-service attendance, leave requests and review acknowledgement.">
            <FormGrid columns={2}>
              <FormField
                label="Login"
                error={e.user_id?.message}
                hint={!can('users.view') ? 'You need the “View users” permission to pick a login.' : 'Only members of this company are listed.'}
              >
                <Select {...register('user_id')} placeholder="Not linked" disabled={!can('users.view')} options={userOptions} />
              </FormField>
            </FormGrid>
          </FormSection>

          <FormSection title="Notes">
            <FormField label="Internal notes" error={e.notes?.message}><Textarea rows={3} {...register('notes')} /></FormField>
          </FormSection>
        </CardBody>
      </Card>
      <FormActions>
        <Button variant="secondary" onClick={cancel}>Cancel</Button>
        <Button type="submit" loading={formState.isSubmitting}>{employee ? 'Save changes' : 'Create employee'}</Button>
      </FormActions>
    </form>
  );
}
