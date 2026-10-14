'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { ArrowRightLeft, Pencil, Plus, Trash2, UserCheck, UserPlus } from 'lucide-react';
import { Button, ConfirmDialog, MenuItem, MenuSeparator, Select, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney, humanize } from '@/lib/format';
import { useAppSelector } from '@/store';
import { leads, LEAD_SOURCES, LEAD_STATUSES, type Lead } from '@/features/sales/api';
import { AssignLeadModal, ConvertLeadModal, LeadForm } from '@/features/sales/LeadForm';
import { enumOptions, SalesStatus, useUserOptions } from '@/features/sales/shared';
import styles from '@/components/crud/crud.module.scss';

function LeadsInner() {
  const can = useCan();
  const currency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const list = useListState({ sort: '-created_at' });
  const query = leads.useList(list.apiParams);
  const del = leads.useDelete();
  const users = useUserOptions();
  const [editing, setEditing] = useState<Lead | 'new' | null>(null);
  const [assigning, setAssigning] = useState<Lead | null>(null);
  const [converting, setConverting] = useState<Lead | null>(null);
  const [deleting, setDeleting] = useState<Lead | null>(null);
  const canManage = can('leads.manage');

  const columns: DataTableColumn<Lead>[] = [
    {
      id: 'name',
      accessorKey: 'name',
      header: 'Lead',
      enableSorting: true,
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong>{row.original.name}</strong>
          <span>{[row.original.company_name, row.original.email ?? row.original.phone].filter(Boolean).join(' · ') || '—'}</span>
        </div>
      ),
    },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <SalesStatus status={row.original.status} /> },
    { id: 'source', header: 'Source', cell: ({ row }) => humanize(row.original.source) },
    {
      id: 'estimated_value',
      accessorKey: 'estimated_value',
      header: 'Est. value',
      enableSorting: true,
      meta: { numeric: true },
      cell: ({ row }) => (row.original.estimated_value === null ? <span className={styles.muted}>—</span> : formatMoney(row.original.estimated_value, currency)),
    },
    { id: 'assignee', header: 'Assigned to', cell: ({ row }) => row.original.assignee?.name ?? <span className={styles.muted}>Unassigned</span> },
    {
      id: 'converted',
      header: 'Customer',
      cell: ({ row }) =>
        row.original.converted_customer_id ? (
          <Link href={`/sales/customers/${row.original.converted_customer_id}`} onClick={(e) => e.stopPropagation()}>
            {row.original.converted_customer?.name ?? 'View customer'}
          </Link>
        ) : (
          <span className={styles.muted}>—</span>
        ),
    },
    { id: 'created_at', accessorKey: 'created_at', header: 'Created', enableSorting: true, cell: ({ row }) => formatDate(row.original.created_at) },
  ];

  return (
    <>
      <ListPage<Lead>
        title="Leads"
        description="Prospects to qualify, assign and convert into customers."
        actions={canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New lead</Button>}
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search name, company, email or phone"
        filters={
          <>
            <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={enumOptions(LEAD_STATUSES)} />
            <Select aria-label="Source" placeholder="Any source" value={list.state.filters.source ?? ''} onChange={(e) => list.update({ filters: { source: e.target.value || undefined } })} options={enumOptions(LEAD_SOURCES)} />
            <Select aria-label="Assignee" placeholder="Anyone" value={list.state.filters.assigned_to ?? ''} onChange={(e) => list.update({ filters: { assigned_to: e.target.value || undefined } })} options={users.options} />
          </>
        }
        emptyTitle="No leads yet"
        emptyDescription="Capture prospects from your website, events and referrals."
        emptyAction={canManage ? <Button leftIcon={<UserPlus size={16} />} onClick={() => setEditing('new')}>Add a lead</Button> : undefined}
        onRowClick={canManage ? (l) => setEditing(l) : undefined}
        rowActions={
          canManage
            ? (l) => (
                <>
                  <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(l)}>Edit</MenuItem>
                  <MenuItem icon={<UserCheck size={16} />} onSelect={() => setAssigning(l)}>Assign…</MenuItem>
                  <MenuItem icon={<ArrowRightLeft size={16} />} disabled={l.status === 'converted'} onSelect={() => setConverting(l)}>Convert to customer…</MenuItem>
                  <MenuSeparator />
                  <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(l)}>Delete</MenuItem>
                </>
              )
            : undefined
        }
      />

      <LeadForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} open={editing !== null} lead={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      {assigning && <AssignLeadModal key={assigning.id} lead={assigning} onClose={() => setAssigning(null)} />}
      {converting && <ConvertLeadModal key={converting.id} lead={converting} onClose={() => setConverting(null)} />}

      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        destructive
        confirmLabel="Delete lead"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id);
          setDeleting(null);
        }}
      />
    </>
  );
}

export default function LeadsPage() {
  return (
    <Suspense>
      <LeadsInner />
    </Suspense>
  );
}
