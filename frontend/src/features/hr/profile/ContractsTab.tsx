'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { FileText, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMutation } from '@tanstack/react-query';
import {
  Alert, Button, Card, CardBody, CardHeader, ConfirmDialog, CurrencyInput, DateInput, Drawer, EmptyState, ErrorState, FormField, FormGrid, IconButton, Input,
  Select, SkeletonTable, StatusBadge, Textarea, useToast,
} from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { formatDate, formatMoney } from '@/lib/format';
import { CONTRACT_STATUSES, EMPLOYMENT_TYPES, UPLOAD_ACCEPT, UPLOAD_MAX_BYTES, contracts, hrApi, typeLabel, useInvalidate, type Contract, type Employee } from '../api';
import { nul } from '../components';
import crud from '@/components/crud/crud.module.scss';

const schema = z
  .object({
    contract_type: z.enum(['full_time', 'part_time', 'contract', 'intern']),
    start_date: z.string().min(1, 'Start date is required'),
    end_date: z.string(),
    salary: z.string().nullable().refine((v) => !!v, 'Salary is required'),
    currency: z.string().trim().regex(/^[A-Za-z]{3}$/, 'Use a 3-letter currency code'),
    working_hours_per_week: z.string().refine((v) => v === '' || (/^\d+(\.\d{1,2})?$/.test(v) && Number(v) <= 168), '0–168'),
    status: z.enum(['active', 'expired', 'terminated']),
    notes: z.string().max(5000),
  })
  .refine((v) => !v.end_date || v.end_date >= v.start_date, { path: ['end_date'], message: 'End date must be on or after the start date' });
type Values = z.infer<typeof schema>;

function ContractForm({ open, employee, row, onClose }: { open: boolean; employee: Employee; row: Contract | null; onClose: () => void }) {
  const invalidate = useInvalidate();
  const toast = useToast();
  const [file, setFile] = useState<File | null>(null);
  const [fileError, setFileError] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, control, setError, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      contract_type: row?.contract_type ?? employee.employment_type,
      start_date: row?.start_date ?? employee.hired_at ?? '',
      end_date: row?.end_date ?? '',
      salary: row?.salary ?? employee.base_salary ?? null,
      currency: row?.currency ?? employee.currency ?? 'USD',
      working_hours_per_week: row?.working_hours_per_week != null ? String(Number(row.working_hours_per_week)) : '40',
      status: row?.status ?? 'active',
      notes: row?.notes ?? '',
    },
  });
  const e = formState.errors;

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const body: Record<string, unknown> = {
      contract_type: v.contract_type,
      start_date: v.start_date,
      end_date: v.end_date || null,
      salary: v.salary,
      currency: v.currency.toUpperCase(),
      status: v.status,
      notes: nul(v.notes),
    };
    if (v.working_hours_per_week) body.working_hours_per_week = v.working_hours_per_week;
    if (!row) body.employee_id = employee.id;
    try {
      await hrApi.saveContract(row?.id ?? null, body, file);
      void invalidate('employment-contracts');
      toast.success(row ? 'Contract saved' : 'Contract added');
      onClose();
    } catch (err) {
      const apiErr = err as ApiError;
      if (apiErr.errors?.document) setFileError(apiErr.errors.document[0] ?? null);
      const left = applyServerErrors(apiErr, setError as never, Object.keys(schema.shape));
      setFormError(left);
    }
  };

  return (
    <Drawer open={open} onClose={onClose} title={row ? 'Edit contract' : 'New contract'} description={employee.name} size="md"
      footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button type="submit" form="contract-form" loading={formState.isSubmitting}>{row ? 'Save changes' : 'Add contract'}</Button></>}>
      <form id="contract-form" noValidate onSubmit={handleSubmit(onSubmit)}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          <FormField label="Type" required error={e.contract_type?.message}><Select {...register('contract_type')} options={EMPLOYMENT_TYPES} /></FormField>
          <FormField label="Status" error={e.status?.message} hint="Only one active contract per employee"><Select {...register('status')} options={CONTRACT_STATUSES} /></FormField>
          <FormField label="Start date" required error={e.start_date?.message}>
            <Controller control={control} name="start_date" render={({ field }) => <DateInput value={field.value || null} onValueChange={(d) => field.onChange(d ?? '')} />} />
          </FormField>
          <FormField label="End date" error={e.end_date?.message} hint="Empty = open-ended">
            <Controller control={control} name="end_date" render={({ field }) => <DateInput value={field.value || null} onValueChange={(d) => field.onChange(d ?? '')} />} />
          </FormField>
          <FormField label="Salary" required error={e.salary?.message}>
            <Controller control={control} name="salary" render={({ field }) => <CurrencyInput currency={employee.currency ?? 'USD'} value={field.value} onValueChange={field.onChange} />} />
          </FormField>
          <FormField label="Currency" required error={e.currency?.message}><Input maxLength={3} {...register('currency')} style={{ textTransform: 'uppercase' }} /></FormField>
          <FormField label="Hours per week" error={e.working_hours_per_week?.message}><Input inputMode="decimal" {...register('working_hours_per_week')} /></FormField>
          <FormField label="Signed document" error={fileError ?? undefined} hint={row?.has_document ? 'A document is on file; choosing a file replaces it.' : 'PDF, Word or image, up to 10 MB'}>
            <Input type="file" accept={UPLOAD_ACCEPT} onChange={(ev) => {
              const f = ev.target.files?.[0] ?? null;
              setFileError(f && f.size > UPLOAD_MAX_BYTES ? 'The file is larger than 10 MB.' : null);
              setFile(f && f.size <= UPLOAD_MAX_BYTES ? f : null);
            }} />
          </FormField>
          <FormField label="Notes" span="full" error={e.notes?.message}><Textarea rows={3} {...register('notes')} /></FormField>
        </FormGrid>
      </form>
    </Drawer>
  );
}

export function ContractsTab({ employee, canManage }: { employee: Employee; canManage: boolean }) {
  const q = contracts.useList({ 'filter[employee_id]': employee.id, per_page: 50, sort: '-start_date' });
  const del = contracts.useDelete();
  const [editing, setEditing] = useState<Contract | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Contract | null>(null);
  const toast = useToast();
  const rows = q.data?.data ?? [];
  const active = rows.find((c) => c.status === 'active');
  const expire = useMutation<Contract, ApiError, Contract>({
    mutationFn: (c) => hrApi.saveContract(c.id, { status: 'expired' }, null),
    onSuccess: () => { void q.refetch(); toast.success('Contract marked expired'); },
    onError: (err) => toast.error('Could not update contract', err.message),
  });

  return (
    <Card>
      <CardHeader
        title="Contracts"
        description={active ? `Active: ${typeLabel(active.contract_type)} since ${formatDate(active.start_date)}` : 'No active contract'}
        actions={canManage && <Button size="sm" variant="secondary" leftIcon={<Plus size={14} />} onClick={() => setEditing('new')}>New contract</Button>}
      />
      <CardBody padding="none">
        {canManage && active && (
          <div style={{ padding: '12px 16px 0' }}>
            <Alert variant="info">An employee can have only one active contract. To add a new active contract, expire or terminate the current one first.</Alert>
          </div>
        )}
        {q.error ? (
          <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} />
        ) : q.isLoading ? (
          <div style={{ padding: 16 }}><SkeletonTable rows={3} cols={5} /></div>
        ) : rows.length === 0 ? (
          <EmptyState size="sm" icon={<FileText size={20} />} title="No contracts on file" description="Record the employment contract with salary and dates." />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines}>
              <thead><tr><th>Type</th><th>Period</th><th className={crud.r}>Salary</th><th>Hours/wk</th><th>Status</th><th>Doc</th>{canManage && <th aria-label="Actions" />}</tr></thead>
              <tbody>
                {rows.map((c) => (
                  <tr key={c.id}>
                    <td>{typeLabel(c.contract_type)}</td>
                    <td>{formatDate(c.start_date)} – {c.end_date ? formatDate(c.end_date) : 'open-ended'}</td>
                    <td className={crud.r}>{formatMoney(c.salary, c.currency)}</td>
                    <td>{c.working_hours_per_week != null ? Number(c.working_hours_per_week) : '—'}</td>
                    <td><StatusBadge status={c.status} /></td>
                    <td>{c.has_document ? 'Yes' : '—'}</td>
                    {canManage && (
                      <td className={crud.r} style={{ whiteSpace: 'nowrap' }}>
                        {c.status === 'active' && <Button size="sm" variant="ghost" loading={expire.isPending && expire.variables?.id === c.id} onClick={() => expire.mutate(c)}>Expire</Button>}
                        <IconButton size="sm" icon={<Pencil size={14} />} aria-label="Edit contract" onClick={() => setEditing(c)} />
                        <IconButton size="sm" icon={<Trash2 size={14} />} aria-label="Delete contract" onClick={() => setDeleting(c)} />
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>
      <ContractForm
        key={editing === null ? 'closed' : editing === 'new' ? 'new' : `${editing.id}-${editing.updated_at ?? ''}`}
        open={editing !== null}
        employee={employee}
        row={editing === 'new' ? null : editing}
        onClose={() => setEditing(null)}
      />
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title="Delete this contract?"
        destructive
        confirmLabel="Delete contract"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </Card>
  );
}
