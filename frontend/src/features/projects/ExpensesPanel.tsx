'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Paperclip, Pencil, Plus, Receipt, Trash2 } from 'lucide-react';
import {
  Alert, Button, ConfirmDialog, CurrencyInput, DataTable, DateInput, Drawer, EmptyState, FormField, FormGrid, Input, MenuItem, MenuSeparator, Select,
  Textarea, paginationFromMeta, type DataTableColumn,
} from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney } from '@/lib/format';
import crud from '@/components/crud/crud.module.scss';
import { documentsApi, useDocumentList } from '@/features/documents/api';
import { projectsApi, usePmMutation, usePmQuery, type Project, type ProjectExpense, type ProjectExpenseInput } from './api';
import { nullable, todayIso } from './shared';
import styles from './projects.module.scss';

export function ExpensesPanel({ project, canManage }: { project: Project; canManage: boolean }) {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [editing, setEditing] = useState<ProjectExpense | 'new' | null>(null);
  const [deleting, setDeleting] = useState<ProjectExpense | null>(null);
  const [downloadError, setDownloadError] = useState<string | null>(null);
  const params = { page, per_page: perPage, sort: '-date' };
  const q = usePmQuery(['expenses', project.id, params], () => projectsApi.expenses(project.id, params), { keepPrevious: true });
  const del = usePmMutation((id: number) => projectsApi.deleteExpense(project.id, id), { success: 'Expense deleted', errorTitle: 'Could not delete expense' });
  const meta = q.data?.meta.pagination;

  const openReceipt = async (id: number) => {
    setDownloadError(null);
    try {
      const doc = await documentsApi.show(id);
      await documentsApi.download(doc);
    } catch (e) {
      setDownloadError((e as ApiError).message);
    }
  };

  const columns: DataTableColumn<ProjectExpense>[] = [
    { id: 'date', header: 'Date', cell: ({ row }) => formatDate(row.original.date) },
    {
      id: 'category',
      header: 'Expense',
      cell: ({ row }) => (
        <div className={crud.cellMain}>
          <strong>{row.original.category}</strong>
          <span>{row.original.description ?? ' '}</span>
        </div>
      ),
    },
    {
      id: 'receipt',
      header: 'Receipt',
      cell: ({ row }) =>
        row.original.receipt_document_id ? (
          <Button size="sm" variant="link" leftIcon={<Paperclip size={13} />} onClick={(e) => { e.stopPropagation(); void openReceipt(row.original.receipt_document_id!); }}>
            Download
          </Button>
        ) : (
          <span className={crud.muted}>—</span>
        ),
    },
    { id: 'amount', header: 'Amount', meta: { numeric: true }, cell: ({ row }) => formatMoney(row.original.amount, row.original.currency) },
  ];

  return (
    <div className={styles.stack}>
      <div className={styles.row}>
        <p className={styles.note} style={{ flex: 1 }}>Project expenses count against the budget. They are tracked here only and are not posted to the general ledger.</p>
        {canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>Add expense</Button>}
      </div>
      {downloadError && <Alert variant="danger" dismissible onDismiss={() => setDownloadError(null)}>{downloadError}</Alert>}
      <DataTable<ProjectExpense>
        aria-label="Project expenses"
        columns={columns}
        data={q.data?.data ?? []}
        isLoading={q.isLoading || (q.isFetching && q.isPlaceholderData)}
        error={q.error}
        onRetry={() => void q.refetch()}
        pagination={meta ? { ...paginationFromMeta(meta), onPageChange: setPage, onPerPageChange: (n: number) => { setPerPage(n); setPage(1); } } : undefined}
        onRowClick={canManage ? (e) => setEditing(e) : undefined}
        rowActions={
          canManage
            ? (e) => (
                <>
                  <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(e)}>Edit</MenuItem>
                  <MenuSeparator />
                  <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(e)}>Delete</MenuItem>
                </>
              )
            : undefined
        }
        emptyState={<EmptyState icon={<Receipt size={22} />} title="No expenses recorded" action={canManage ? <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>Add expense</Button> : undefined} />}
      />
      {editing && <ExpenseDrawer key={editing === 'new' ? 'new' : editing.id} project={project} expense={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title="Delete this expense?"
        description={deleting ? `${deleting.category} · ${formatMoney(deleting.amount, deleting.currency)}` : undefined}
        destructive
        confirmLabel="Delete expense"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </div>
  );
}

const schema = z.object({
  date: z.string().min(1, 'Date is required'),
  category: z.string().trim().min(1, 'Category is required').max(60),
  description: z.string().trim().max(500),
  amount: z.string().nullable().refine((v) => !!v && Number(v) > 0, 'Enter an amount greater than zero'),
  currency: z.string().trim().regex(/^[A-Za-z]{3}$/, 'Use a 3-letter currency code'),
  receipt_document_id: z.string(),
});
type Values = z.infer<typeof schema>;

function ExpenseDrawer({ project, expense, onClose }: { project: Project; expense: ProjectExpense | null; onClose: () => void }) {
  const can = useCan();
  const [formError, setFormError] = useState<string | null>(null);
  const docs = useDocumentList({ documentable_type: 'project', documentable_id: project.id, per_page: 100 }, { enabled: can('documents.view') });
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      date: expense?.date ?? todayIso(),
      category: expense?.category ?? '',
      description: expense?.description ?? '',
      amount: expense?.amount ?? null,
      currency: expense?.currency ?? project.currency,
      receipt_document_id: expense?.receipt_document_id ? String(expense.receipt_document_id) : '',
    },
  });
  const { register, handleSubmit, control, setError, formState, watch } = form;
  const save = usePmMutation(
    (body: ProjectExpenseInput) => (expense ? projectsApi.updateExpense(project.id, expense.id, body) : projectsApi.createExpense(project.id, body)),
    { success: expense ? 'Expense saved' : 'Expense added', errorTitle: 'Could not save expense' },
  );
  const currency = watch('currency');

  const onSubmit = async (v: Values) => {
    setFormError(null);
    try {
      await save.mutateAsync({
        date: v.date,
        category: v.category,
        description: nullable(v.description),
        amount: v.amount ?? '0',
        currency: v.currency.toUpperCase(),
        receipt_document_id: v.receipt_document_id ? Number(v.receipt_document_id) : null,
      });
      onClose();
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  const e = formState.errors;
  const docOptions = (docs.data?.data ?? []).map((d) => ({ value: String(d.id), label: d.title }));
  if (expense?.receipt_document_id && !docOptions.some((o) => o.value === String(expense.receipt_document_id))) {
    docOptions.push({ value: String(expense.receipt_document_id), label: `Document #${expense.receipt_document_id}` });
  }

  return (
    <Drawer
      open
      onClose={onClose}
      size="md"
      title={expense ? 'Edit expense' : 'Add expense'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="expense-form" loading={formState.isSubmitting}>{expense ? 'Save' : 'Add expense'}</Button>
        </>
      }
    >
      <form id="expense-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          <FormField label="Date" required error={e.date?.message}>
            {(p) => <Controller control={control} name="date" render={({ field }) => <DateInput {...p} value={field.value} onValueChange={(v) => field.onChange(v ?? '')} />} />}
          </FormField>
          <FormField label="Category" required error={e.category?.message} hint="e.g. Travel, Software, Materials">
            <Input maxLength={60} {...register('category')} list="project-expense-categories" />
          </FormField>
          <datalist id="project-expense-categories">
            {['Travel', 'Software', 'Materials', 'Subcontractor', 'Equipment', 'Meals', 'Other'].map((c) => <option key={c} value={c} />)}
          </datalist>
          <FormField label="Amount" required error={e.amount?.message}>
            {(p) => <Controller control={control} name="amount" render={({ field }) => <CurrencyInput {...p} currency={/^[A-Za-z]{3}$/.test(currency) ? currency.toUpperCase() : project.currency} value={field.value} onValueChange={field.onChange} />} />}
          </FormField>
          <FormField label="Currency" error={e.currency?.message}>
            <Input maxLength={3} {...register('currency')} style={{ textTransform: 'uppercase' }} />
          </FormField>
          <FormField label="Description" span="full" error={e.description?.message}>
            <Textarea rows={3} maxLength={500} {...register('description')} />
          </FormField>
          <FormField label="Receipt" span="full" error={e.receipt_document_id?.message} hint="Pick a file uploaded to this project’s Files tab">
            <Select {...register('receipt_document_id')} options={[{ value: '', label: docs.isLoading ? 'Loading…' : 'No receipt' }, ...docOptions]} />
          </FormField>
        </FormGrid>
      </form>
    </Drawer>
  );
}
