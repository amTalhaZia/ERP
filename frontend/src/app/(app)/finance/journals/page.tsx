'use client';

import { Suspense } from 'react';
import { useRouter } from 'next/navigation';
import { Plus } from 'lucide-react';
import { Button, DateInput, Select, StatusBadge, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney, humanize } from '@/lib/format';
import { useAppSelector } from '@/store';
import { journals, type JournalEntry } from '@/features/finance/api';
import styles from '@/components/crud/crud.module.scss';

function JournalsInner() {
  const router = useRouter();
  const can = useCan();
  const currency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const list = useListState({ sort: '-entry_date' });
  const query = journals.useList(list.apiParams);

  const columns: DataTableColumn<JournalEntry>[] = [
    { id: 'number', accessorKey: 'number', header: 'Number', enableSorting: true, cell: ({ row }) => <span className={styles.mono}>{row.original.number}</span> },
    { id: 'entry_date', accessorKey: 'entry_date', header: 'Date', enableSorting: true, cell: ({ row }) => formatDate(row.original.entry_date) },
    { id: 'memo', header: 'Memo / reference', cell: ({ row }) => <div className={styles.cellMain}><strong>{row.original.memo ?? '—'}</strong><span>{row.original.reference ?? ''}</span></div> },
    { id: 'source', header: 'Source', cell: ({ row }) => humanize(row.original.source_type ?? 'manual') },
    { id: 'total_debit', header: 'Amount', meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.total_debit, currency) },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <StatusBadge status={row.original.status} /> },
  ];

  return (
    <ListPage<JournalEntry>
      title="Journal entries"
      description="Double-entry postings. Posted entries are immutable — corrections are made with reversing entries."
      actions={can('journals.create') && <Button leftIcon={<Plus size={16} />} href="/finance/journals/new">New entry</Button>}
      query={query}
      columns={columns}
      list={list}
      searchPlaceholder="Search number, reference or memo"
      filters={
        <>
          <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={[{ value: 'draft', label: 'Draft' }, { value: 'posted', label: 'Posted' }, { value: 'reversed', label: 'Reversed' }]} />
          <Select aria-label="Source" placeholder="Any source" value={list.state.filters.source_type ?? ''} onChange={(e) => list.update({ filters: { source_type: e.target.value || undefined } })} options={['manual', 'invoice', 'bill', 'payment', 'expense_claim', 'payroll_run', 'stock_adjustment', 'opening_balance'].map((v) => ({ value: v, label: humanize(v) }))} />
          <DateInput aria-label="From" value={list.state.from ?? ''} onValueChange={(v) => list.update({ from: v ?? undefined })} />
          <DateInput aria-label="To" value={list.state.to ?? ''} onValueChange={(v) => list.update({ to: v ?? undefined })} />
        </>
      }
      emptyTitle="No journal entries yet"
      onRowClick={(j) => router.push(`/finance/journals/${j.id}`)}
    />
  );
}

export default function JournalsPage() {
  return (
    <Suspense>
      <JournalsInner />
    </Suspense>
  );
}
