'use client';

import { Suspense, useState } from 'react';
import { useRouter } from 'next/navigation';
import { ClipboardCheck, Eye, Plus, Trash2 } from 'lucide-react';
import { Button, ConfirmDialog, MenuItem, MenuSeparator, Select, StatusBadge, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate } from '@/lib/format';
import { REVIEW_STATUSES, reviews, useEmployeeOptions, type PerformanceReview } from '@/features/hr/api';
import { Stars } from '@/features/hr/components';
import { NewReviewDrawer } from '@/features/hr/Performance';
import crud from '@/components/crud/crud.module.scss';

function PerformanceInner() {
  const router = useRouter();
  const can = useCan();
  const list = useListState({ sort: '-period_end' });
  const query = reviews.useList(list.apiParams);
  const canManage = can('performance.manage');
  const emp = useEmployeeOptions(can('employees.view'));
  const del = reviews.useDelete();
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<PerformanceReview | null>(null);
  const f = list.state.filters;

  const columns: DataTableColumn<PerformanceReview>[] = [
    {
      id: 'employee', header: 'Employee', enableHiding: false,
      cell: ({ row }) => <div className={crud.cellMain}><strong>{row.original.employee?.name}</strong><span>{row.original.employee?.employee_code}</span></div>,
    },
    { id: 'cycle', accessorKey: 'cycle', header: 'Cycle', enableSorting: true, cell: ({ row }) => <span className={crud.mono}>{row.original.cycle}</span> },
    { id: 'period_end', accessorKey: 'period_end', header: 'Period', enableSorting: true, cell: ({ row }) => `${formatDate(row.original.period_start)} – ${formatDate(row.original.period_end)}` },
    { id: 'reviewer', header: 'Reviewer', cell: ({ row }) => row.original.reviewer?.name ?? '—' },
    { id: 'overall_rating', accessorKey: 'overall_rating', header: 'Rating', enableSorting: true, cell: ({ row }) => <Stars rating={row.original.overall_rating} /> },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <StatusBadge status={row.original.status} /> },
  ];

  return (
    <>
      <ListPage<PerformanceReview>
        title="Performance reviews"
        description={can(['performance.view', 'performance.manage'], 'any') ? 'Review cycles, ratings and acknowledgements.' : 'Reviews about you, and reviews you are writing.'}
        actions={canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setCreating(true)}>New review</Button>}
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search cycle, e.g. 2026-H1"
        filters={
          <>
            <Select aria-label="Status" placeholder="Any status" value={f.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={REVIEW_STATUSES} />
            {emp.options.length > 0 && (
              <Select aria-label="Employee" placeholder="Any employee" value={f.employee_id ?? ''} onChange={(e) => list.update({ filters: { employee_id: e.target.value || undefined } })} options={emp.options} />
            )}
          </>
        }
        emptyTitle="No reviews yet"
        emptyDescription={canManage ? 'Start a review cycle for your team.' : 'Reviews shared with you will appear here.'}
        emptyAction={canManage ? <Button leftIcon={<ClipboardCheck size={16} />} onClick={() => setCreating(true)}>Start a review</Button> : undefined}
        onRowClick={(r) => router.push(`/hr/performance/${r.id}`)}
        rowActions={(r) => (
          <>
            <MenuItem icon={<Eye size={16} />} onSelect={() => router.push(`/hr/performance/${r.id}`)}>Open</MenuItem>
            {canManage && r.status !== 'acknowledged' && (
              <>
                <MenuSeparator />
                <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(r)}>Delete</MenuItem>
              </>
            )}
          </>
        )}
      />
      <NewReviewDrawer key={creating ? 'open' : 'closed'} open={creating} onClose={() => setCreating(false)} employeeId={f.employee_id} />
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title="Delete this review?"
        description={deleting ? `${deleting.employee?.name} · ${deleting.cycle}` : undefined}
        destructive
        confirmLabel="Delete review"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </>
  );
}

export default function PerformancePage() {
  return (
    <Suspense>
      <PerformanceInner />
    </Suspense>
  );
}
