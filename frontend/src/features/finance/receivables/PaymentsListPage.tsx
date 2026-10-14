'use client';

import { useState } from 'react';
import { BookOpen, Plus, XCircle } from 'lucide-react';
import { Alert, Button, DateInput, Drawer, ErrorState, MenuItem, Select, SkeletonCard, StatusBadge, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime, formatMoney, humanize } from '@/lib/format';
import { ALLOCATION_HREF, PAYMENT_METHODS, payments, useBankAccountOptions, type Payment, type PaymentType } from './api';
import { RecordPaymentDrawer } from './RecordPaymentDrawer';
import { ReasonDialog, useInvalidateReceivables } from './shared';
import crud from '@/components/crud/crud.module.scss';
import styles from './receivables.module.scss';

const ALLOC_LABEL = { invoice: 'Invoice', bill: 'Bill', expense_claim: 'Expense claim' } as const;

function PaymentDetail({ id, onClose, onVoid }: { id: number; onClose: () => void; onVoid: (p: Payment) => void }) {
  const can = useCan();
  const q = payments.useOne(id);
  const p = q.data;
  return (
    <Drawer
      open
      onClose={onClose}
      size="lg"
      title={p ? `${humanize(p.type)} ${p.number}` : 'Payment'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Close</Button>
          {p && p.status === 'posted' && can('payments.create') && <Button variant="danger" leftIcon={<XCircle size={16} />} onClick={() => onVoid(p)}>Void</Button>}
        </>
      }
    >
      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : !p ? (
        <SkeletonCard />
      ) : (
        <div className={styles.stack}>
          {p.status === 'void' && <Alert variant="warning" title="Void">Voided {p.voided_at ? formatDateTime(p.voided_at) : ''}{p.void_reason ? ` — ${p.void_reason}` : ''}. The posting was reversed and document balances restored.</Alert>}
          <dl className={crud.dl}>
            <dt>Status</dt><dd><StatusBadge status={p.status} /></dd>
            <dt>{humanize(p.partner_type)}</dt><dd>{p.partner?.name ?? `#${p.partner_id}`}</dd>
            <dt>Date</dt><dd>{formatDate(p.payment_date)}</dd>
            <dt>Amount</dt><dd><strong>{formatMoney(p.amount, p.currency)}</strong></dd>
            <dt>Method</dt><dd>{humanize(p.method)}</dd>
            <dt>Bank account</dt><dd>{p.bank_account ? `${p.bank_account.name}${p.bank_account.account_number ? ` (${p.bank_account.account_number})` : ''}` : '—'}</dd>
            <dt>Reference</dt><dd>{p.reference ?? '—'}</dd>
            <dt>Notes</dt><dd>{p.notes ?? '—'}</dd>
            <dt>Journal</dt><dd>{p.journal_entry_id ? <a className={styles.linkBtn} href={`/finance/journals/${p.journal_entry_id}`}><BookOpen size={12} style={{ display: 'inline', verticalAlign: '-1px' }} /> View journal entry</a> : '—'}</dd>
          </dl>
          <div>
            <strong>Allocations</strong>
            <table className={styles.allocTable} style={{ marginTop: 8 }}>
              <thead><tr><th>Document</th><th className={styles.num}>Amount</th></tr></thead>
              <tbody>
                {(p.allocations ?? []).map((a) => (
                  <tr key={a.id}>
                    <td><a className={styles.linkBtn} href={ALLOCATION_HREF[a.type](a.document_id)}>{ALLOC_LABEL[a.type]} #{a.document_id}</a></td>
                    <td className={styles.num}>{formatMoney(a.amount, p.currency)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </Drawer>
  );
}

export function PaymentsListPage({ type }: { type: PaymentType }) {
  const can = useCan();
  const list = useListState({ sort: '-payment_date' });
  const query = payments.useList({ ...list.apiParams, 'filter[type]': type });
  const banks = useBankAccountOptions();
  const invalidate = useInvalidateReceivables();
  const voidPayment = payments.useAction<{ reason: string }>('void', { success: `${type === 'receipt' ? 'Receipt' : 'Payment'} voided`, onSuccess: () => void invalidate() });
  const [recording, setRecording] = useState(0);
  const [viewing, setViewing] = useState<number | null>(null);
  const [voiding, setVoiding] = useState<Payment | null>(null);
  const noun = type === 'receipt' ? 'receipt' : 'payment';

  const columns: DataTableColumn<Payment>[] = [
    { id: 'number', accessorKey: 'number', header: 'Number', enableSorting: true, cell: ({ row }) => <span className={crud.mono}>{row.original.number}</span> },
    { id: 'payment_date', accessorKey: 'payment_date', header: 'Date', enableSorting: true, cell: ({ row }) => formatDate(row.original.payment_date) },
    {
      id: 'partner',
      header: type === 'receipt' ? 'Customer' : 'Paid to',
      cell: ({ row }) => (
        <div className={crud.cellMain}>
          <strong>{row.original.partner?.name ?? `#${row.original.partner_id}`}</strong>
          {type === 'payment' && <span>{humanize(row.original.partner_type)}</span>}
        </div>
      ),
    },
    { id: 'method', header: 'Method', cell: ({ row }) => humanize(row.original.method) },
    { id: 'bank', header: 'Bank account', cell: ({ row }) => row.original.bank_account?.name ?? '—' },
    { id: 'reference', header: 'Reference', cell: ({ row }) => row.original.reference ?? <span className={crud.muted}>—</span> },
    { id: 'amount', accessorKey: 'amount', header: 'Amount', enableSorting: true, meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.amount, row.original.currency) },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.status} /> },
  ];

  return (
    <>
      <ListPage<Payment>
        title={type === 'receipt' ? 'Receipts' : 'Payments'}
        description={type === 'receipt' ? 'Money received from customers. Recorded receipts are posted; correct them by voiding.' : 'Money paid to suppliers and employees. Recorded payments are posted; correct them by voiding.'}
        actions={can('payments.create') && <Button leftIcon={<Plus size={16} />} onClick={() => setRecording((n) => n + 1)}>Record {noun}</Button>}
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search number or reference"
        filters={
          <>
            {type === 'payment' && (
              <Select aria-label="Paid to" placeholder="Suppliers & employees" value={list.state.filters.partner_type ?? ''} onChange={(e) => list.update({ filters: { partner_type: e.target.value || undefined } })} options={[{ value: 'supplier', label: 'Suppliers' }, { value: 'employee', label: 'Employees' }]} />
            )}
            <Select aria-label="Method" placeholder="Any method" value={list.state.filters.method ?? ''} onChange={(e) => list.update({ filters: { method: e.target.value || undefined } })} options={PAYMENT_METHODS} />
            <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={[{ value: 'posted', label: 'Posted' }, { value: 'void', label: 'Void' }]} />
            <Select aria-label="Bank account" placeholder="Any bank account" value={list.state.filters.bank_account_id ?? ''} onChange={(e) => list.update({ filters: { bank_account_id: e.target.value || undefined } })} options={banks.options} />
            <DateInput aria-label="From" value={list.state.from ?? ''} onValueChange={(v) => list.update({ from: v ?? undefined })} />
            <DateInput aria-label="To" value={list.state.to ?? ''} onValueChange={(v) => list.update({ to: v ?? undefined })} />
          </>
        }
        emptyTitle={`No ${noun}s yet`}
        onRowClick={(p) => setViewing(p.id)}
        rowActions={(p) => (
          <>
            <MenuItem onSelect={() => setViewing(p.id)}>View allocations</MenuItem>
            {p.journal_entry_id && <MenuItem icon={<BookOpen size={16} />} href={`/finance/journals/${p.journal_entry_id}`}>View journal</MenuItem>}
            {p.status === 'posted' && can('payments.create') && <MenuItem icon={<XCircle size={16} />} destructive onSelect={() => setVoiding(p)}>Void</MenuItem>}
          </>
        )}
      />
      {recording > 0 && <RecordPaymentDrawer key={recording} open onClose={() => setRecording(0)} type={type} />}
      {viewing !== null && <PaymentDetail key={viewing} id={viewing} onClose={() => setViewing(null)} onVoid={(p) => setVoiding(p)} />}
      <ReasonDialog
        key={voiding ? `void-${voiding.id}` : 'void-closed'}
        open={voiding !== null}
        onClose={() => setVoiding(null)}
        title={`Void ${voiding?.number ?? ''}?`}
        description="A reversing journal entry dated today is posted and the allocated documents' balances are restored. This cannot be undone."
        confirmLabel={`Void ${noun}`}
        onSubmit={(reason) => voidPayment.mutateAsync({ id: voiding!.id, body: { reason } })}
      />
    </>
  );
}
