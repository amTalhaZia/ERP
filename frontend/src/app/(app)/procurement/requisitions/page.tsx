'use client';

import { Suspense, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import { Plus } from 'lucide-react';
import { Button, DateInput, Select, Switch, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney } from '@/lib/format';
import { useAppSelector } from '@/store';
import { REQUISITION_PRIORITIES, REQUISITION_STATUSES, requisitions, type Requisition } from '@/features/procurement/api';
import { DocStatus, PriorityBadge } from '@/features/procurement/components';
import styles from '@/components/crud/crud.module.scss';

function RequisitionsInner() {
  const router = useRouter();
  const can = useCan();
  const currency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const list = useListState({ sort: '-created_at' });
  const canReview = can(['requisitions.view', 'requisitions.approve'], 'any');

  // `mine` is a top-level flag on this endpoint, not a filter[…] column.
  const params = useMemo(() => {
    const p: Record<string, unknown> = { ...list.apiParams };
    delete p['filter[mine]'];
    if (list.state.filters.mine) p.mine = 1;
    return p;
  }, [list.apiParams, list.state.filters.mine]);
  const query = requisitions.useList(params);

  const columns: DataTableColumn<Requisition>[] = [
    { id: 'number', accessorKey: 'number', header: 'Number', enableSorting: true, cell: ({ row }) => <span className={styles.mono}>{row.original.number}</span> },
    {
      id: 'requester',
      header: 'Requested by',
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong>{row.original.requester?.name ?? '—'}</strong>
          <span>{[row.original.department?.name, row.original.branch?.name].filter(Boolean).join(' · ') || '—'}</span>
        </div>
      ),
    },
    { id: 'priority', accessorKey: 'priority', header: 'Priority', enableSorting: true, cell: ({ row }) => <PriorityBadge priority={row.original.priority} /> },
    { id: 'needed_by', accessorKey: 'needed_by', header: 'Needed by', enableSorting: true, cell: ({ row }) => formatDate(row.original.needed_by) },
    { id: 'lines', header: 'Lines', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.lines_count ?? 0 },
    { id: 'estimated_total', accessorKey: 'estimated_total', header: 'Estimated', enableSorting: true, meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.estimated_total, currency) },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <DocStatus status={row.original.status} /> },
    { id: 'created_at', accessorKey: 'created_at', header: 'Created', enableSorting: true, cell: ({ row }) => formatDate(row.original.created_at) },
  ];

  return (
    <ListPage<Requisition>
      title="Purchase requisitions"
      description="Internal requests to buy. Approved requisitions are converted into purchase orders."
      actions={can('requisitions.create') && <Button leftIcon={<Plus size={16} />} href="/procurement/requisitions/new">New requisition</Button>}
      query={query}
      columns={columns}
      list={list}
      searchPlaceholder="Search number or justification"
      filters={
        <>
          <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={REQUISITION_STATUSES} />
          <Select aria-label="Priority" placeholder="Any priority" value={list.state.filters.priority ?? ''} onChange={(e) => list.update({ filters: { priority: e.target.value || undefined } })} options={REQUISITION_PRIORITIES} />
          <DateInput aria-label="Created from" value={list.state.from ?? ''} onValueChange={(v) => list.update({ from: v ?? undefined })} />
          <DateInput aria-label="Created to" value={list.state.to ?? ''} onValueChange={(v) => list.update({ to: v ?? undefined })} />
          {canReview && (
            <Switch label="My requisitions" checked={list.state.filters.mine === '1'} onCheckedChange={(c) => list.update({ filters: { mine: c ? '1' : undefined } })} />
          )}
        </>
      }
      emptyTitle="No requisitions yet"
      emptyDescription="Raise a requisition when your team needs something purchased."
      emptyAction={can('requisitions.create') ? <Button leftIcon={<Plus size={16} />} href="/procurement/requisitions/new">New requisition</Button> : undefined}
      onRowClick={(r) => router.push(`/procurement/requisitions/${r.id}`)}
    />
  );
}

export default function RequisitionsPage() {
  return (
    <Suspense>
      <RequisitionsInner />
    </Suspense>
  );
}
