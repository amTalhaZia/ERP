'use client';

import { useState } from 'react';
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import {
  Alert, Button, ConfirmDialog, DataTable, Drawer, ErrorState, FormField, FormGrid, Input, MenuItem, MenuSeparator, SearchInput, SkeletonCard, StatusBadge, Switch, Textarea,
  paginationFromMeta, type DataTableColumn,
} from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { applyServerErrors } from '@/lib/crud/resource';
import { salaryStructures, useCompanyCurrency, usePayComponentOptions, type SalaryStructure } from '../api';
import { OverridesEditor, overrideFromApi, overrideRowSchema, overrideToApi, refineDistinctComponents } from '../components';
import crud from '@/components/crud/crud.module.scss';
import styles from '../payroll.module.scss';

const schema = z
  .object({
    name: z.string().trim().min(1, 'Name is required').max(100),
    description: z.string().max(500),
    currency: z.string().trim().regex(/^[A-Za-z]{3}$/, 'Three-letter currency code'),
    is_active: z.boolean(),
    components: z.array(overrideRowSchema).max(100),
  })
  .superRefine((v, ctx) => refineDistinctComponents(v.components, ctx));
type Values = z.infer<typeof schema>;

function StructureForm({ row, onClose }: { row: SalaryStructure | null; onClose: () => void }) {
  const companyCurrency = useCompanyCurrency();
  const comps = usePayComponentOptions();
  const [formError, setFormError] = useState<string | null>(null);
  const create = salaryStructures.useCreate({ onSuccess: onClose });
  const update = salaryStructures.useUpdate({ onSuccess: onClose });
  const { register, handleSubmit, control, setError, setValue, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: row?.name ?? '',
      description: row?.description ?? '',
      currency: row?.currency ?? companyCurrency,
      is_active: row?.is_active ?? true,
      components: (row?.components ?? []).map(overrideFromApi),
    },
  });
  const { fields, append, remove } = useFieldArray({ control, name: 'components' });
  const rows = useWatch({ control, name: 'components' });
  const currency = useWatch({ control, name: 'currency' });
  const e = formState.errors;

  return (
    <Drawer open onClose={onClose} title={row ? `Edit ${row.name}` : 'New salary structure'} size="xl"
      description="A reusable package of pay components. Employees get a structure plus their own base salary."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="structure-form" loading={formState.isSubmitting}>{row ? 'Save changes' : 'Create structure'}</Button>
        </>
      }>
      <form id="structure-form" noValidate onSubmit={handleSubmit(async (v) => {
        setFormError(null);
        const body = {
          name: v.name.trim(),
          description: v.description.trim() || null,
          currency: v.currency.trim().toUpperCase(),
          is_active: v.is_active,
          components: v.components.map(overrideToApi),
        };
        try {
          if (row) await update.mutateAsync({ id: row.id, body });
          else await create.mutateAsync(body);
        } catch (err) {
          const x = err as ApiError;
          const left = applyServerErrors(x, setError as never, ['name', 'description', 'currency', 'is_active', 'components']);
          setFormError(x.status === 422 ? left : (left ?? x.message));
        }
      })}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={3}>
          <FormField label="Name" required error={e.name?.message} span={2}><Input maxLength={100} {...register('name')} autoFocus={!row} /></FormField>
          <FormField label="Currency" required error={e.currency?.message}><Input maxLength={3} {...register('currency')} style={{ textTransform: 'uppercase' }} /></FormField>
          <FormField label="Description" error={e.description?.message} span="full"><Textarea rows={2} maxLength={500} {...register('description')} /></FormField>
        </FormGrid>
        <div style={{ marginTop: 16 }}>
          <Controller control={control} name="is_active" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" description="Inactive structures can’t be assigned to new salaries." />} />
        </div>
        <h4 className={styles.sectionTitle}>Components</h4>
        <p className={styles.hint} style={{ marginBottom: 8 }}>Basic salary and income tax are always included automatically.</p>
        {comps.query.error ? (
          <ErrorState size="sm" error={comps.query.error} onRetry={() => void comps.query.refetch()} />
        ) : comps.query.isLoading ? (
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
          />
        )}
        {(e.components?.message || e.components?.root?.message) && <Alert variant="danger" style={{ marginTop: 12 }}>{e.components?.message ?? e.components?.root?.message}</Alert>}
      </form>
    </Drawer>
  );
}

/** Edit loads the full structure (the list has counts only). */
function EditStructure({ id, onClose }: { id: number; onClose: () => void }) {
  const q = salaryStructures.useOne(id);
  if (q.error) return <Drawer open onClose={onClose} title="Salary structure"><ErrorState error={q.error} onRetry={() => void q.refetch()} /></Drawer>;
  if (!q.data) return <Drawer open onClose={onClose} title="Salary structure"><SkeletonCard /></Drawer>;
  return <StructureForm row={q.data} onClose={onClose} />;
}

export function SalaryStructuresTab({ canManage }: { canManage: boolean }) {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const q = salaryStructures.useList({ page, per_page: 50, sort: 'name', search: search || undefined });
  const del = salaryStructures.useDelete();
  const [editing, setEditing] = useState<number | 'new' | null>(null);
  const [deleting, setDeleting] = useState<SalaryStructure | null>(null);
  const meta = q.data?.meta?.pagination;

  const columns: DataTableColumn<SalaryStructure>[] = [
    { id: 'name', header: 'Name', enableHiding: false, cell: ({ row }) => <div className={crud.cellMain}><strong>{row.original.name}</strong><span>{row.original.description ?? ''}</span></div> },
    { id: 'currency', header: 'Currency', cell: ({ row }) => <span className={crud.mono}>{row.original.currency}</span> },
    { id: 'components', header: 'Components', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.components_count ?? '—' },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <div className={styles.stack}>
      <div className={styles.between}>
        <SearchInput placeholder="Search structures" defaultValue={search} onDebouncedChange={(v) => { setSearch(v); setPage(1); }} aria-label="Search salary structures" />
        {canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New structure</Button>}
      </div>
      <DataTable<SalaryStructure>
        aria-label="Salary structures"
        columns={columns}
        data={q.data?.data ?? []}
        isLoading={q.isLoading}
        error={q.error}
        onRetry={() => void q.refetch()}
        onRowClick={canManage ? (row) => setEditing(row.id) : undefined}
        rowActions={canManage ? (row) => (
          <>
            <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(row.id)}>Edit</MenuItem>
            <MenuSeparator />
            <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(row)}>Delete</MenuItem>
          </>
        ) : undefined}
        pagination={meta && meta.last_page > 1 ? { ...paginationFromMeta(meta), onPageChange: setPage } : undefined}
        emptyState={<div style={{ padding: 24 }} className={styles.hint}>{search ? 'No structures match.' : 'No salary structures yet. Create one (e.g. “Staff”, “Management”) and add pay components to it.'}</div>}
      />
      {editing === 'new' && <StructureForm key="new" row={null} onClose={() => setEditing(null)} />}
      {typeof editing === 'number' && <EditStructure key={editing} id={editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name ?? 'structure'}?`}
        description="Structures assigned to employee salaries cannot be deleted — deactivate them instead."
        destructive
        confirmLabel="Delete"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </div>
  );
}
