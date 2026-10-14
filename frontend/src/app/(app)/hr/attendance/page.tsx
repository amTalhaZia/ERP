'use client';

import { Suspense, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { CalendarCheck, Pencil, Plus, Trash2 } from 'lucide-react';
import {
  Button, ConfirmDialog, DataTable, DateInput, EmptyState, FilterBar, MenuItem, MenuSeparator, PageHeader, Select, Tab, TabList, TabPanel, Tabs,
  paginationFromMeta, type DataTableColumn,
} from '@/components/ui';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate } from '@/lib/format';
import { departments } from '@/features/company/api';
import { ATTENDANCE_STATUSES, attendance, useEmployeeOptions, type AttendanceRecord } from '@/features/hr/api';
import { AttendanceForm, AttendanceSummary, BulkEntry, SelfServiceCard } from '@/features/hr/Attendance';
import { AttendanceBadge } from '@/features/hr/components';
import { formatTimeTz, minutesLabel, useCompanyTz } from '@/features/hr/time';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/hr/hr.module.scss';

function Records({ canManage }: { canManage: boolean }) {
  const tz = useCompanyTz();
  const list = useListState({ sort: '-date' });
  const { state, update, clearFilters, activeFilterCount } = list;
  // `department_id` is a top-level query param on this endpoint, not filter[].
  const { ['filter[department_id]']: dept, ...rest } = list.apiParams;
  const query = attendance.useList(dept ? { ...rest, department_id: dept } : rest);
  const emp = useEmployeeOptions();
  const deps = departments.useList({ per_page: 100, sort: 'name' });
  const del = attendance.useDelete();
  const [editing, setEditing] = useState<AttendanceRecord | 'new' | null>(null);
  const [deleting, setDeleting] = useState<AttendanceRecord | null>(null);
  const meta = query.data?.meta?.pagination;
  const f = state.filters;

  const columns: DataTableColumn<AttendanceRecord>[] = [
    { id: 'date', accessorKey: 'date', header: 'Date', enableSorting: true, enableHiding: false, cell: ({ row }) => formatDate(row.original.date, 'EEE dd MMM yyyy') },
    {
      id: 'employee', header: 'Employee', enableHiding: false,
      cell: ({ row }) => <div className={crud.cellMain}><strong>{row.original.employee?.name ?? `#${row.original.employee_id}`}</strong><span>{row.original.employee?.employee_code}</span></div>,
    },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <AttendanceBadge status={row.original.status} /> },
    { id: 'in', header: 'In', cell: ({ row }) => formatTimeTz(row.original.check_in, tz) },
    { id: 'out', header: 'Out', cell: ({ row }) => formatTimeTz(row.original.check_out, tz) },
    { id: 'worked_minutes', accessorKey: 'worked_minutes', header: 'Worked', enableSorting: true, meta: { align: 'right', numeric: true }, cell: ({ row }) => minutesLabel(row.original.worked_minutes) },
    { id: 'late_minutes', accessorKey: 'late_minutes', header: 'Late', enableSorting: true, meta: { align: 'right', numeric: true }, cell: ({ row }) => (row.original.late_minutes ? minutesLabel(row.original.late_minutes) : '—') },
    { id: 'source', header: 'Source', cell: ({ row }) => (row.original.source === 'self' ? 'Self-service' : row.original.source) },
  ];

  return (
    <div className={styles.stack}>
      <div className={styles.between}>
        <FilterBar activeCount={activeFilterCount} onClear={clearFilters}>
          <DateInput aria-label="From" value={state.from ?? null} max={state.to} onValueChange={(v) => update({ from: v ?? undefined })} />
          <DateInput aria-label="To" value={state.to ?? null} min={state.from} onValueChange={(v) => update({ to: v ?? undefined })} />
          <Select aria-label="Employee" placeholder="Any employee" value={f.employee_id ?? ''} onChange={(e) => update({ filters: { employee_id: e.target.value || undefined } })} options={emp.options} />
          <Select aria-label="Department" placeholder="Any department" value={f.department_id ?? ''} onChange={(e) => update({ filters: { department_id: e.target.value || undefined } })}
            options={(deps.data?.data ?? []).map((d) => ({ value: String(d.id), label: d.name }))} />
          <Select aria-label="Status" placeholder="Any status" value={f.status ?? ''} onChange={(e) => update({ filters: { status: e.target.value || undefined } })} options={ATTENDANCE_STATUSES} />
        </FilterBar>
        {canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>Record attendance</Button>}
      </div>
      <DataTable<AttendanceRecord>
        aria-label="Attendance records"
        columns={columns}
        data={query.data?.data ?? []}
        isLoading={query.isLoading || (query.isFetching && query.isPlaceholderData)}
        error={query.error}
        onRetry={() => void query.refetch()}
        sort={state.sort}
        onSortChange={(sort) => update({ sort })}
        pagination={meta ? { ...paginationFromMeta(meta), onPageChange: (page: number) => update({ page }, false), onPerPageChange: (perPage: number) => update({ perPage }) } : undefined}
        onRowClick={canManage ? (r) => setEditing(r) : undefined}
        rowActions={canManage ? (r) => (
          <>
            <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(r)}>Edit</MenuItem>
            <MenuSeparator />
            <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(r)}>Delete</MenuItem>
          </>
        ) : undefined}
        enableColumnVisibility
        emptyState={<EmptyState icon={<CalendarCheck size={22} />} title={activeFilterCount ? 'No matching records' : 'No attendance recorded yet'} description={activeFilterCount ? 'Try different filters.' : 'Record attendance, use bulk entry, or let employees check in themselves.'} />}
      />
      <AttendanceForm
        key={editing === null ? 'closed' : editing === 'new' ? 'new' : `${editing.id}-${editing.updated_at ?? ''}`}
        open={editing !== null}
        row={editing === 'new' ? null : editing}
        onClose={() => setEditing(null)}
      />
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title="Delete this attendance record?"
        description={deleting ? `${deleting.employee?.name ?? 'Employee'} · ${formatDate(deleting.date)}` : undefined}
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

function AttendanceInner() {
  const can = useCan();
  const router = useRouter();
  const pathname = usePathname();
  const search = useSearchParams();
  const canView = can('attendance.view');
  const canManage = can('attendance.manage');
  const tabs = [canView && 'records', canManage && 'bulk', canView && 'summary'].filter(Boolean) as string[];
  const requested = search.get('tab') ?? '';
  const tab = tabs.includes(requested) ? requested : tabs[0];
  const setTab = (t: string) => {
    const next = new URLSearchParams();
    if (t !== tabs[0]) next.set('tab', t);
    router.replace(next.toString() ? `${pathname}?${next}` : pathname, { scroll: false });
  };

  return (
    <div className={styles.stack}>
      <PageHeader title="Attendance" description="Daily check-ins, manual records and period summaries." />
      <SelfServiceCard />
      {tab && (
        <Tabs value={tab} onValueChange={setTab}>
          <TabList>
            {canView && <Tab value="records">Records</Tab>}
            {canManage && <Tab value="bulk">Bulk entry</Tab>}
            {canView && <Tab value="summary">Summary</Tab>}
          </TabList>
          {canView && <TabPanel value="records"><div className={styles.tabBody}><Records canManage={canManage} /></div></TabPanel>}
          {canManage && <TabPanel value="bulk"><div className={styles.tabBody}><BulkEntry onDone={() => setTab('records')} /></div></TabPanel>}
          {canView && <TabPanel value="summary"><div className={styles.tabBody}><AttendanceSummary /></div></TabPanel>}
        </Tabs>
      )}
    </div>
  );
}

export default function AttendancePage() {
  return (
    <Suspense>
      <AttendanceInner />
    </Suspense>
  );
}
