'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { BookOpen, Calculator, CheckCircle2, ChevronDown, Download, Lock, Trash2, Wallet, XCircle } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, ConfirmDialog, DataTable, ErrorState, FormField, MenuItem, Menu, MetricCard, PageHeader,
  SearchInput, SkeletonCard, Textarea, paginationFromMeta, useToast, type DataTableColumn,
} from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime, formatMoney, formatNumber } from '@/lib/format';
import { useAppSelector } from '@/store';
import { selectUser } from '@/store/sessionSlice';
import { downloadRunExport, payrollApi, payrollRuns, useInvalidatePayroll, useRunMutation, useRunPayslips, type Payslip, type PayrollRun, type SkippedEmployee } from './api';
import { RunStatusBadge, RunStepper, SkippedList, WarningBadges, explainPayrollError, periodLabel } from './components';
import { MarkPaidDrawer } from './MarkPaidDrawer';
import { PayslipDrawer } from './PayslipDrawer';
import crud from '@/components/crud/crud.module.scss';
import styles from './payroll.module.scss';

type Confirm = 'calculate' | 'approve' | 'finalize' | 'cancel' | 'delete' | null;

function PayslipsCard({ run, onOpen }: { run: PayrollRun; onOpen: (id: number) => void }) {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [search, setSearch] = useState('');
  const [sort, setSort] = useState<string | undefined>('employee_code');
  const q = useRunPayslips(run.id, { page, per_page: perPage, search: search || undefined, sort });
  const meta = q.data?.meta?.pagination;
  const days = (v: string) => formatNumber(v, { maximumFractionDigits: 1 });

  const columns: DataTableColumn<Payslip>[] = [
    {
      id: 'employee_name', header: 'Employee', enableSorting: true, enableHiding: false,
      cell: ({ row }) => (
        <div className={crud.cellMain}>
          <strong>{row.original.employee.name}</strong>
          <span><span className={crud.mono}>{row.original.employee.code}</span>{row.original.employee.department ? ` · ${row.original.employee.department}` : ''}</span>
        </div>
      ),
    },
    { id: 'days', header: 'Paid / working days', meta: { numeric: true, align: 'right' }, cell: ({ row }) => `${days(row.original.paid_days)} / ${days(row.original.working_days)}` },
    { id: 'gross', header: 'Gross', enableSorting: true, meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.gross, row.original.currency) },
    { id: 'tax', header: 'Tax', meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.tax, row.original.currency) },
    { id: 'total_deductions', header: 'Deductions', meta: { numeric: true, align: 'right', label: 'Deductions (incl. tax)' }, cell: ({ row }) => formatMoney(row.original.total_deductions, row.original.currency) },
    { id: 'net', header: 'Net', enableSorting: true, meta: { numeric: true, align: 'right' }, cell: ({ row }) => <strong className={Number(row.original.net) < 0 ? styles.danger : undefined}>{formatMoney(row.original.net, row.original.currency)}</strong> },
    { id: 'warnings', header: 'Warnings', cell: ({ row }) => <WarningBadges warnings={row.original.warnings} /> },
  ];

  return (
    <Card>
      <CardHeader title="Payslips" description={meta ? `${meta.total} payslip${meta.total === 1 ? '' : 's'}` : undefined}
        actions={<SearchInput placeholder="Search employee" defaultValue={search} onDebouncedChange={(v) => { setSearch(v); setPage(1); }} aria-label="Search payslips" />} />
      <CardBody padding="none">
        <DataTable<Payslip>
          aria-label="Payslips"
          columns={columns}
          data={q.data?.data ?? []}
          isLoading={q.isLoading}
          error={q.error}
          onRetry={() => void q.refetch()}
          sort={sort}
          onSortChange={(s) => { setSort(s); setPage(1); }}
          onRowClick={(row) => onOpen(row.id)}
          pagination={meta ? { ...paginationFromMeta(meta), onPageChange: setPage, onPerPageChange: (n) => { setPerPage(n); setPage(1); } } : undefined}
          emptyState={
            <div style={{ padding: 24 }} className={styles.hint}>
              {search ? 'No payslips match your search.' : run.status === 'draft' ? 'No payslips yet. Calculate the run to generate them.' : 'This run has no payslips.'}
            </div>
          }
        />
      </CardBody>
    </Card>
  );
}

export function RunDetail({ id }: { id: string }) {
  const router = useRouter();
  const can = useCan();
  const toast = useToast();
  const me = useAppSelector(selectUser);
  const invalidate = useInvalidatePayroll();
  const q = payrollRuns.useOne(id);
  const [confirm, setConfirm] = useState<Confirm>(null);
  const [paying, setPaying] = useState(0);
  const [slipId, setSlipId] = useState<number | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [skipped, setSkipped] = useState<SkippedEmployee[] | null>(null);
  const [cancelReason, setCancelReason] = useState('');
  const [exporting, setExporting] = useState(false);

  const calculate = useRunMutation((runId: number) => payrollApi.calculate(runId));
  const approve = useRunMutation((runId: number) => payrollApi.approve(runId));
  const finalize = useRunMutation((runId: number) => payrollApi.finalize(runId));
  const cancel = useRunMutation((v: { id: number; reason: string | null }) => payrollApi.cancel(v.id, v.reason));
  const remove = useRunMutation((runId: number) => payrollApi.remove(runId));

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const r = q.data;
  const canProcess = can('payroll.process');
  const ownRun = !!me && r.created_by?.id === me.id;

  const act = async (fn: () => Promise<unknown>, success: string) => {
    setActionError(null);
    try {
      await fn();
      toast.success(success);
    } catch (e) {
      const err = e as ApiError;
      if (err.code === 'payroll.invalid_state') void invalidate();
      setActionError(err.status === 422 ? (Object.values(err.errors ?? {})[0]?.[0] ?? err.message) : explainPayrollError(err));
    } finally {
      setConfirm(null);
    }
  };

  const exportAs = async (format: 'csv' | 'xlsx') => {
    setExporting(true);
    try {
      await downloadRunExport(r, format);
    } catch (e) {
      toast.error('Export failed', (e as ApiError).message);
    } finally {
      setExporting(false);
    }
  };

  const t = r.totals;
  const hasPayslips = t.employee_count > 0;

  return (
    <div className={styles.stack}>
      <PageHeader
        title={r.name}
        description={`${r.number} · ${periodLabel(r.period_start, r.period_end)} · pay date ${formatDate(r.payment_date)}`}
        backHref="/payroll/runs"
        backLabel="Payroll runs"
        meta={<RunStatusBadge status={r.status} />}
        actions={
          <>
            {canProcess && (r.status === 'draft' || r.status === 'calculated') && (
              <Button variant={r.status === 'draft' ? 'primary' : 'secondary'} leftIcon={<Calculator size={16} />} onClick={() => setConfirm('calculate')}>
                {r.status === 'draft' ? 'Calculate' : 'Recalculate'}
              </Button>
            )}
            {canProcess && r.status === 'calculated' && <Button leftIcon={<CheckCircle2 size={16} />} onClick={() => setConfirm('approve')}>Approve</Button>}
            {canProcess && r.status === 'approved' && <Button leftIcon={<Lock size={16} />} onClick={() => setConfirm('finalize')}>Finalize</Button>}
            {canProcess && r.status === 'finalized' && <Button leftIcon={<Wallet size={16} />} onClick={() => setPaying((n) => n + 1)}>Mark paid</Button>}
            {hasPayslips && (
              <Menu align="end" trigger={<Button variant="secondary" leftIcon={<Download size={16} />} rightIcon={<ChevronDown size={14} />} loading={exporting}>Export</Button>}>
                <MenuItem onSelect={() => void exportAs('csv')}>CSV</MenuItem>
                <MenuItem onSelect={() => void exportAs('xlsx')}>Excel (XLSX)</MenuItem>
              </Menu>
            )}
            {canProcess && ['draft', 'calculated', 'approved'].includes(r.status) && (
              <Button variant="ghost" leftIcon={<XCircle size={16} />} onClick={() => { setCancelReason(''); setConfirm('cancel'); }}>Cancel run</Button>
            )}
            {canProcess && r.status === 'draft' && <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirm('delete')}>Delete</Button>}
          </>
        }
      />

      {actionError && <Alert variant="danger" dismissible onDismiss={() => setActionError(null)}>{actionError}</Alert>}
      {skipped && skipped.length > 0 && (
        <Alert variant="warning" dismissible onDismiss={() => setSkipped(null)} title={`${skipped.length} employee${skipped.length === 1 ? ' was' : 's were'} skipped`}>
          <SkippedList skipped={skipped} />
        </Alert>
      )}
      {r.status === 'calculated' && ownRun && canProcess && (
        <Alert variant="info">You created this run, so you can’t approve it — another user with the “Run &amp; approve payroll” permission must approve it.</Alert>
      )}
      {r.status === 'cancelled' && <Alert variant="warning" title="Cancelled">This run was cancelled {r.cancelled_at ? `on ${formatDateTime(r.cancelled_at)}` : ''}. Its employees can be included in a new run for the same period.</Alert>}
      {(r.status === 'finalized' || r.status === 'paid') && (
        <Alert variant="info">This run is finalized and posted to the general ledger; it can no longer be changed. Payslips are visible to employees in self-service.</Alert>
      )}

      <Card>
        <CardBody><RunStepper run={r} /></CardBody>
      </Card>

      <div className={styles.metrics}>
        <MetricCard label="Gross pay" value={formatMoney(t.gross, r.currency)} />
        <MetricCard label="Deductions" value={formatMoney(t.deductions, r.currency)} footer="Including tax" />
        <MetricCard label="Tax withheld" value={formatMoney(t.tax, r.currency)} />
        <MetricCard label="Employer contributions" value={formatMoney(t.employer_contributions, r.currency)} footer="On top of gross" />
        <MetricCard label="Net pay" value={formatMoney(t.net, r.currency)} />
        <MetricCard label="Employees" value={t.employee_count} footer={r.calculated_at ? `Calculated ${formatDateTime(r.calculated_at)}` : 'Not calculated yet'} />
      </div>

      <div className={crud.detailGrid}>
        <PayslipsCard run={r} onOpen={setSlipId} />
        <Card>
          <CardHeader title="Details" />
          <CardBody>
            <dl className={crud.dl}>
              <dt>Number</dt><dd className={crud.mono}>{r.number}</dd>
              <dt>Period</dt><dd>{formatDate(r.period_start)} – {formatDate(r.period_end)}</dd>
              <dt>Payment date</dt><dd>{formatDate(r.payment_date)}</dd>
              <dt>Currency</dt><dd>{r.currency}</dd>
              <dt>Branch</dt><dd>{r.branch?.name ?? 'All branches'}</dd>
              <dt>Department</dt><dd>{r.department?.name ?? 'All departments'}</dd>
              <dt>Created by</dt><dd>{r.created_by?.name ?? '—'}</dd>
              <dt>Approved</dt><dd>{r.approved_at ? `${formatDateTime(r.approved_at)}${r.approved_by ? ` by ${r.approved_by.name}` : ''}` : '—'}</dd>
              <dt>Finalized</dt><dd>{r.finalized_at ? `${formatDateTime(r.finalized_at)}${r.finalized_by ? ` by ${r.finalized_by.name}` : ''}` : '—'}</dd>
              <dt>Paid</dt><dd>{r.paid_at ? formatDateTime(r.paid_at) : '—'}</dd>
              <dt>Payroll journal</dt>
              <dd>{r.journal_entry_id ? <a className={styles.linkBtn} href={`/finance/journals/${r.journal_entry_id}`}><BookOpen size={12} style={{ display: 'inline', verticalAlign: '-1px' }} /> View journal entry</a> : 'Posted on finalize'}</dd>
              <dt>Payment journal</dt>
              <dd>{r.payment_journal_entry_id ? <a className={styles.linkBtn} href={`/finance/journals/${r.payment_journal_entry_id}`}><BookOpen size={12} style={{ display: 'inline', verticalAlign: '-1px' }} /> View journal entry</a> : 'Posted when marked paid'}</dd>
              {r.notes && <><dt>Notes</dt><dd style={{ whiteSpace: 'pre-wrap' }}>{r.notes}</dd></>}
            </dl>
          </CardBody>
        </Card>
      </div>

      <ConfirmDialog
        open={confirm === 'calculate'}
        onClose={() => setConfirm(null)}
        title={r.status === 'draft' ? `Calculate ${r.number}?` : `Recalculate ${r.number}?`}
        description="Payslips are (re)built from current salaries, attendance and unpaid leave, pay components and the active tax table. Employees who newly qualify are added; those who no longer do are removed."
        confirmLabel={r.status === 'draft' ? 'Calculate' : 'Recalculate'}
        onConfirm={() => act(async () => {
          const res = await calculate.mutateAsync(r.id);
          setSkipped(res ? res.skipped : null);
        }, 'Payroll calculated')}
      />
      <ConfirmDialog
        open={confirm === 'approve'}
        onClose={() => setConfirm(null)}
        title={`Approve ${r.number}?`}
        description={`Approves net pay of ${formatMoney(t.net, r.currency)} for ${t.employee_count} employee${t.employee_count === 1 ? '' : 's'}. The approver must be someone other than the person who created the run.`}
        confirmLabel="Approve"
        onConfirm={() => act(() => approve.mutateAsync(r.id), 'Payroll run approved')}
      />
      <ConfirmDialog
        open={confirm === 'finalize'}
        onClose={() => setConfirm(null)}
        title={`Finalize ${r.number}? This cannot be undone.`}
        description={`Finalizing posts one payroll journal entry to the general ledger (dated ${formatDate(r.period_end)}): salaries and employer contributions as expense; tax withheld, deductions and net pay (${formatMoney(t.net, r.currency)}) as liabilities. Payslips are numbered and released to employees. The run can no longer be recalculated or cancelled.`}
        confirmLabel="Finalize & post"
        destructive
        typeToConfirm={r.number}
        onConfirm={() => act(() => finalize.mutateAsync(r.id), 'Payroll finalized and posted')}
      />
      <ConfirmDialog
        open={confirm === 'cancel'}
        onClose={() => setConfirm(null)}
        title={`Cancel ${r.number}?`}
        description="The run is kept for audit but nothing is posted. Its employees can be included in a new run for the same period."
        confirmLabel="Cancel run"
        cancelLabel="Keep run"
        destructive
        onConfirm={() => act(() => cancel.mutateAsync({ id: r.id, reason: cancelReason.trim() || null }), 'Payroll run cancelled')}
      >
        <FormField label="Reason" hint="Optional, recorded in the audit log.">
          <Textarea rows={3} maxLength={500} value={cancelReason} onChange={(e) => setCancelReason(e.target.value)} />
        </FormField>
      </ConfirmDialog>
      <ConfirmDialog
        open={confirm === 'delete'}
        onClose={() => setConfirm(null)}
        title={`Delete ${r.number}?`}
        description="The draft run and its payslips are deleted permanently."
        confirmLabel="Delete"
        destructive
        onConfirm={() => act(async () => {
          await remove.mutateAsync(r.id);
          router.replace('/payroll/runs');
        }, 'Payroll run deleted')}
      />
      {paying > 0 && <MarkPaidDrawer key={paying} run={r} open onClose={() => setPaying(0)} />}
      <PayslipDrawer key={slipId ?? 'none'} id={slipId} onClose={() => setSlipId(null)} />
    </div>
  );
}
