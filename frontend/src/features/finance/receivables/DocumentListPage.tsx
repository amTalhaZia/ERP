'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { BarChart3, Plus, Wallet } from 'lucide-react';
import { Button, DateInput, Select, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney } from '@/lib/format';
import { DOCUMENT_STATUSES, partnerLabel, useCurrency, usePartners } from './api';
import { DOC_CONFIG, docDate, docPartner, docResource, type AnyDoc, type DocKind } from './documents';
import { RecordPaymentDrawer } from './RecordPaymentDrawer';
import { DocumentStatusBadge, ReceivablesSummaryStrip } from './shared';
import crud from '@/components/crud/crud.module.scss';
import styles from './receivables.module.scss';

export function DocumentListPage({ kind }: { kind: DocKind }) {
  const cfg = DOC_CONFIG[kind];
  const router = useRouter();
  const can = useCan();
  const currency = useCurrency();
  const list = useListState({ sort: kind === 'invoice' ? '-invoice_date' : '-bill_date' });
  const query = docResource(kind).useList(list.apiParams);
  const partners = usePartners(cfg.partnerType);
  const [paying, setPaying] = useState(0);
  const partnerField = kind === 'invoice' ? 'customer_id' : 'supplier_id';
  const dateField = kind === 'invoice' ? 'invoice_date' : 'bill_date';

  const columns: DataTableColumn<AnyDoc>[] = [
    { id: 'number', accessorKey: 'number', header: 'Number', enableSorting: true, cell: ({ row }) => <span className={crud.mono}>{row.original.number}</span> },
    {
      id: 'partner',
      header: cfg.partnerLabel,
      cell: ({ row }) => {
        const d = row.original;
        return (
          <div className={crud.cellMain}>
            <strong>{docPartner(d)?.name ?? '—'}</strong>
            {'supplier_reference' in d && <span>Ref. {d.supplier_reference}</span>}
          </div>
        );
      },
    },
    { id: dateField, header: 'Date', enableSorting: true, cell: ({ row }) => formatDate(docDate(row.original)) },
    { id: 'due_date', accessorKey: 'due_date', header: 'Due', enableSorting: true, cell: ({ row }) => <span className={row.original.is_overdue ? styles.danger : undefined}>{formatDate(row.original.due_date)}</span> },
    { id: 'total', accessorKey: 'total', header: 'Total', enableSorting: true, meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.total, row.original.currency) },
    { id: 'balance_due', accessorKey: 'balance_due', header: 'Balance due', enableSorting: true, meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.balance_due, row.original.currency) },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <DocumentStatusBadge status={row.original.status} overdue={row.original.is_overdue} /> },
  ];

  return (
    <>
      <ListPage<AnyDoc>
        title={cfg.plural}
        description={kind === 'invoice' ? 'Customer invoices — approving posts to receivables and revenue.' : 'Supplier bills — approving posts to payables and expenses.'}
        actions={
          <>
            {can('reports.financial.view') && <Button variant="ghost" leftIcon={<BarChart3 size={16} />} href={`/finance/aging?tab=${kind === 'invoice' ? 'ar' : 'ap'}`}>Aging</Button>}
            {can('payments.create') && <Button variant="secondary" leftIcon={<Wallet size={16} />} onClick={() => setPaying((n) => n + 1)}>Record {cfg.paymentType}</Button>}
            {can(cfg.perm.create) && <Button leftIcon={<Plus size={16} />} href={`${cfg.path}/new`}>New {cfg.label.toLowerCase()}</Button>}
          </>
        }
        above={<ReceivablesSummaryStrip side={kind === 'invoice' ? 'receivables' : 'payables'} currency={currency} />}
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder={kind === 'invoice' ? 'Search number' : 'Search number or supplier ref.'}
        filters={
          <>
            <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={DOCUMENT_STATUSES} />
            <Select
              aria-label={cfg.partnerLabel}
              placeholder={`Any ${cfg.partnerType}`}
              value={list.state.filters[partnerField] ?? ''}
              onChange={(e) => list.update({ filters: { [partnerField]: e.target.value || undefined } })}
              options={(partners.data ?? []).map((p) => ({ value: String(p.id), label: partnerLabel(p) }))}
            />
            <DateInput aria-label="From" value={list.state.from ?? ''} onValueChange={(v) => list.update({ from: v ?? undefined })} />
            <DateInput aria-label="To" value={list.state.to ?? ''} onValueChange={(v) => list.update({ to: v ?? undefined })} />
          </>
        }
        emptyTitle={`No ${cfg.plural.toLowerCase()} yet`}
        emptyAction={can(cfg.perm.create) ? <Button size="sm" href={`${cfg.path}/new`}>New {cfg.label.toLowerCase()}</Button> : undefined}
        onRowClick={(d) => router.push(`${cfg.path}/${d.id}`)}
      />
      {paying > 0 && <RecordPaymentDrawer key={paying} open onClose={() => setPaying(0)} type={cfg.paymentType} />}
    </>
  );
}
