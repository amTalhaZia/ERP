'use client';

import { useState } from 'react';
import { Download } from 'lucide-react';
import { Alert, Button, Drawer, ErrorState, SkeletonCard, useToast } from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';
import { downloadPayslipPdf, usePayslip, WARNINGS, type PayslipLine } from './api';
import { RunStatusBadge, fmtRate, periodLabel } from './components';
import crud from '@/components/crud/crud.module.scss';
import styles from './payroll.module.scss';

function LinesTable({ title, lines, currency, total, totalLabel }: { title: string; lines: PayslipLine[]; currency: string; total?: string; totalLabel?: string }) {
  if (lines.length === 0 && !total) return null;
  return (
    <>
      <h4 className={styles.sectionTitle}>{title}</h4>
      <table className={crud.lines}>
        <tbody>
          {lines.map((l) => (
            <tr key={`${l.kind}-${l.code}`}>
              <td>
                {l.name} <span className={styles.hint}>({l.code})</span>
                {l.rate !== null && l.calculation !== 'fixed' && <div className={styles.hint}>{fmtRate(l.rate)} {l.calculation === 'percent_of_gross' ? 'of gross' : 'of base'}</div>}
              </td>
              <td className={crud.r}>{formatMoney(l.amount, currency)}</td>
            </tr>
          ))}
          {lines.length === 0 && <tr><td colSpan={2} className={styles.hint}>None</td></tr>}
        </tbody>
        {total !== undefined && (
          <tfoot><tr><td>{totalLabel}</td><td className={crud.r}>{formatMoney(total, currency)}</td></tr></tfoot>
        )}
      </table>
    </>
  );
}

const days = (v: string) => formatNumber(v, { maximumFractionDigits: 1 });

/** Payslip detail with earnings / deductions lines and PDF download. `mine` uses the self-service endpoints. */
export function PayslipDrawer({ id, mine = false, onClose }: { id: number | null; mine?: boolean; onClose: () => void }) {
  const q = usePayslip(id, mine);
  const toast = useToast();
  const [busy, setBusy] = useState(false);
  const p = q.data;

  const pdf = async () => {
    if (!p) return;
    setBusy(true);
    try {
      await downloadPayslipPdf(p, mine);
    } catch (e) {
      toast.error('Could not download the payslip', (e as ApiError).message);
    } finally {
      setBusy(false);
    }
  };

  const lines = p?.lines ?? [];
  const earnings = lines.filter((l) => l.kind === 'earning');
  const deductions = lines.filter((l) => l.kind === 'deduction');
  const employer = lines.filter((l) => l.kind === 'employer_contribution');

  return (
    <Drawer open={id !== null} onClose={onClose} size="lg"
      title={p ? `Payslip ${p.number}` : 'Payslip'}
      description={p?.run ? `${p.run.name} · ${periodLabel(p.run.period_start, p.run.period_end)}` : undefined}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Close</Button>
          <Button leftIcon={<Download size={16} />} onClick={pdf} loading={busy} disabled={!p}>Download PDF</Button>
        </>
      }>
      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : !p ? (
        <SkeletonCard />
      ) : (
        <>
          {p.warnings.length > 0 && (
            <Alert variant={p.warnings.includes('negative_net') ? 'danger' : 'warning'} style={{ marginBottom: 16 }}>
              {p.warnings.map((w) => WARNINGS[w]?.help ?? w).join(' ')}
            </Alert>
          )}
          <div className={styles.slipHead}>
            <dl className={crud.dl}>
              <dt>Employee</dt><dd><strong>{p.employee.name}</strong> <span className={styles.hint}>{p.employee.code}</span></dd>
              <dt>Department</dt><dd>{p.employee.department ?? '—'}</dd>
              <dt>Designation</dt><dd>{p.employee.designation ?? '—'}</dd>
              <dt>Bank</dt><dd>{p.employee.bank_name ? `${p.employee.bank_name} ${p.employee.bank_account ?? ''}` : '—'}</dd>
            </dl>
            <dl className={crud.dl}>
              <dt>Status</dt><dd><RunStatusBadge status={p.status} size="sm" /></dd>
              <dt>Payment date</dt><dd>{formatDate(p.run?.payment_date)}</dd>
              <dt>Paid / working days</dt><dd>{days(p.paid_days)} / {days(p.working_days)}{Number(p.unpaid_days) > 0 ? ` (${days(p.unpaid_days)} unpaid)` : ''}</dd>
              <dt>Base salary</dt><dd>{formatMoney(p.base_salary, p.currency)}</dd>
            </dl>
          </div>

          <LinesTable title="Earnings" lines={earnings} currency={p.currency} total={p.gross} totalLabel="Gross pay" />
          <LinesTable title="Deductions" lines={deductions} currency={p.currency} total={p.total_deductions} totalLabel="Total deductions (incl. tax)" />
          <p className={styles.hint} style={{ marginTop: 8 }}>Taxable income {formatMoney(p.taxable, p.currency)} · income tax {formatMoney(p.tax, p.currency)}</p>

          <div className={styles.netBox}>
            <span>Net pay</span>
            <span className={Number(p.net) < 0 ? styles.danger : undefined}>{formatMoney(p.net, p.currency)}</span>
          </div>

          {employer.length > 0 && (
            <LinesTable title="Employer contributions (not deducted from pay)" lines={employer} currency={p.currency} total={p.employer_contributions} totalLabel="Total employer cost on top of gross" />
          )}
        </>
      )}
    </Drawer>
  );
}
