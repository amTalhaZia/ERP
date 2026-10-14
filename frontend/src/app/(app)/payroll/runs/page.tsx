'use client';

import { Suspense, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { Plus } from 'lucide-react';
import { Alert, Button, Input, PageHeader, Select, Tab, TabList, Tabs, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney } from '@/lib/format';
import { RUN_STATUSES, payrollRuns, type PayrollRun } from '@/features/payroll/api';
import { RunStatusBadge, monthBounds, periodLabel } from '@/features/payroll/components';
import { NewRunDrawer } from '@/features/payroll/NewRunDrawer';
import { PayrollReports } from '@/features/payroll/Reports';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/payroll/payroll.module.scss';

function SectionTabs({ value }: { value: 'runs' | 'reports' }) {
  const router = useRouter();
  const pathname = usePathname();
  return (
    <Tabs value={value} onValueChange={(v) => router.replace(v === 'reports' ? `${pathname}?tab=reports` : pathname, { scroll: false })}>
      <TabList>
        <Tab value="runs">Runs</Tab>
        <Tab value="reports">Reports</Tab>
      </TabList>
    </Tabs>
  );
}

function RunsList() {
  const router = useRouter();
  const can = useCan();
  const list = useListState({ sort: '-period_end' });
  const query = payrollRuns.useList(list.apiParams);
  const [creating, setCreating] = useState(0);
  const canProcess = can('payroll.process');

  // Month filter maps to a from/to range on period_end.
  const { from, to } = list.state;
  const month = from && to && monthBounds(from.slice(0, 7))?.start === from && monthBounds(from.slice(0, 7))?.end === to ? from.slice(0, 7) : '';

  const columns: DataTableColumn<PayrollRun>[] = [
    { id: 'number', accessorKey: 'number', header: 'Number', enableSorting: true, cell: ({ row }) => <span className={crud.mono}>{row.original.number}</span> },
    {
      id: 'period_end', header: 'Period', enableSorting: true, enableHiding: false,
      cell: ({ row }) => (
        <div className={crud.cellMain}>
          <strong>{periodLabel(row.original.period_start, row.original.period_end)}</strong>
          <span>{row.original.name}</span>
        </div>
      ),
    },
    {
      id: 'scope', header: 'Scope',
      cell: ({ row }) => [row.original.branch?.name, row.original.department?.name].filter(Boolean).join(' · ') || <span className={crud.muted}>Company-wide</span>,
    },
    { id: 'payment_date', accessorKey: 'payment_date', header: 'Pay date', enableSorting: true, cell: ({ row }) => formatDate(row.original.payment_date) },
    { id: 'employees', header: 'Employees', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.totals.employee_count },
    { id: 'gross', header: 'Gross', meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.totals.gross, row.original.currency) },
    { id: 'net', header: 'Net', meta: { numeric: true, align: 'right' }, cell: ({ row }) => <strong>{formatMoney(row.original.totals.net, row.original.currency)}</strong> },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <RunStatusBadge status={row.original.status} /> },
  ];

  return (
    <>
      <ListPage<PayrollRun>
        title="Payroll"
        description="Monthly payroll runs: calculate, approve (by a second person), finalize to the general ledger and record payment."
        actions={canProcess && <Button leftIcon={<Plus size={16} />} onClick={() => setCreating((n) => n + 1)}>New run</Button>}
        above={<SectionTabs value="runs" />}
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search number or name"
        filters={
          <>
            <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''}
              onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={RUN_STATUSES} />
            <Input type="month" aria-label="Period" value={month}
              onChange={(e) => {
                const b = monthBounds(e.target.value);
                list.update({ from: b?.start, to: b?.end });
              }} />
          </>
        }
        emptyTitle="No payroll runs yet"
        emptyDescription="Create a run for a month to calculate payslips for every employee with a salary."
        emptyAction={canProcess ? <Button size="sm" onClick={() => setCreating((n) => n + 1)}>New run</Button> : undefined}
        onRowClick={(r) => router.push(`/payroll/runs/${r.id}`)}
      />
      {creating > 0 && <NewRunDrawer key={creating} open onClose={() => setCreating(0)} />}
    </>
  );
}

function RunsInner() {
  const params = useSearchParams();
  const can = useCan();
  if (!can('payroll.view')) return <Alert variant="warning">You don’t have permission to view payroll runs.</Alert>;
  if (params.get('tab') === 'reports') {
    return (
      <div className={styles.stack}>
        <PageHeader title="Payroll" description="Payroll cost reports from finalized and paid runs." />
        <SectionTabs value="reports" />
        <PayrollReports />
      </div>
    );
  }
  return <RunsList />;
}

export default function PayrollRunsPage() {
  return (
    <Suspense>
      <RunsInner />
    </Suspense>
  );
}
