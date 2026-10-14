'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Download, FileText } from 'lucide-react';
import { Alert, Card, CardBody, DataTable, EmptyState, IconButton, PageHeader, paginationFromMeta, useToast, type DataTableColumn } from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney } from '@/lib/format';
import { downloadPayslipPdf, payrollApi, useCompanyId, type Payslip } from '@/features/payroll/api';
import { RunStatusBadge, periodLabel } from '@/features/payroll/components';
import { PayslipDrawer } from '@/features/payroll/PayslipDrawer';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/payroll/payroll.module.scss';

function PdfButton({ slip }: { slip: Payslip }) {
  const toast = useToast();
  const [busy, setBusy] = useState(false);
  return (
    <IconButton
      icon={<Download size={16} />}
      size="sm"
      aria-label={`Download payslip ${slip.number} as PDF`}
      loading={busy}
      onClick={async (e) => {
        e.stopPropagation();
        setBusy(true);
        try {
          await downloadPayslipPdf(slip, true);
        } catch (err) {
          toast.error('Could not download the payslip', (err as ApiError).message);
        } finally {
          setBusy(false);
        }
      }}
    />
  );
}

export default function MyPayslipsPage() {
  const can = useCan();
  const companyId = useCompanyId();
  const allowed = can('payslips.view.own');
  const [page, setPage] = useState(1);
  const [slipId, setSlipId] = useState<number | null>(null);
  const q = useQuery({
    queryKey: [companyId, 'my-payslips', 'list', page],
    queryFn: () => payrollApi.myPayslips({ page, per_page: 24, sort: '-id' }),
    placeholderData: (prev) => prev,
    enabled: allowed,
  });

  if (!allowed) return <Alert variant="warning">You don’t have permission to view payslips.</Alert>;

  const meta = q.data?.meta?.pagination;
  const columns: DataTableColumn<Payslip>[] = [
    {
      id: 'period', header: 'Period', enableHiding: false,
      cell: ({ row }) => (
        <div className={crud.cellMain}>
          <strong>{row.original.run ? periodLabel(row.original.run.period_start, row.original.run.period_end) : '—'}</strong>
          <span className={crud.mono}>{row.original.number}</span>
        </div>
      ),
    },
    { id: 'payment_date', header: 'Pay date', cell: ({ row }) => formatDate(row.original.run?.payment_date) },
    { id: 'gross', header: 'Gross', meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.gross, row.original.currency) },
    { id: 'deductions', header: 'Deductions', meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.total_deductions, row.original.currency) },
    { id: 'net', header: 'Net pay', meta: { numeric: true, align: 'right' }, cell: ({ row }) => <strong>{formatMoney(row.original.net, row.original.currency)}</strong> },
    { id: 'status', header: 'Status', cell: ({ row }) => <RunStatusBadge status={row.original.status} size="sm" /> },
    { id: 'pdf', header: '', enableHiding: false, meta: { align: 'right', width: 56 }, cell: ({ row }) => <PdfButton slip={row.original} /> },
  ];

  return (
    <div className={styles.stack}>
      <PageHeader title="My payslips" description="Your payslips appear here once payroll for the month is finalized." />
      <Card>
        <CardBody padding="none">
          <DataTable<Payslip>
            aria-label="My payslips"
            columns={columns}
            data={q.data?.data ?? []}
            isLoading={q.isLoading}
            error={q.error}
            onRetry={() => void q.refetch()}
            onRowClick={(row) => setSlipId(row.id)}
            pagination={meta && meta.last_page > 1 ? { ...paginationFromMeta(meta), onPageChange: setPage } : undefined}
            emptyState={
              <EmptyState
                icon={<FileText size={22} />}
                title="No payslips yet"
                description={
                  <>
                    Payslips show up after your company finalizes a payroll run that includes you. If you expected one, ask HR to check that your
                    login is linked to your employee record (Employees → Edit → Linked user).
                  </>
                }
              />
            }
          />
        </CardBody>
      </Card>
      <PayslipDrawer key={slipId ?? 'none'} id={slipId} mine onClose={() => setSlipId(null)} />
    </div>
  );
}
