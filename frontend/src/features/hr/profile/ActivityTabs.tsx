'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useMutation, useQuery } from '@tanstack/react-query';
import { CalendarClock, CalendarDays, ClipboardCheck, Clock } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, DateInput, Drawer, EmptyState, ErrorState, FormField, FormGrid, Select, SkeletonTable, StatusBadge, useToast,
} from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { formatDate, formatNumber } from '@/lib/format';
import { attendance, hrApi, leaveBalances, leaveRequests, reviews, useCompanyId, useInvalidate, useShiftOptions, type Employee } from '../api';
import { AttendanceBadge, Stars, Swatch } from '../components';
import { formatTimeTz, minutesLabel, todayInTz, useCompanyTz } from '../time';
import crud from '@/components/crud/crud.module.scss';
import styles from '../hr.module.scss';

function Loading() {
  return <div style={{ padding: 16 }}><SkeletonTable rows={4} cols={5} /></div>;
}

// ---- Attendance -------------------------------------------------------------------

export function AttendanceTab({ employee }: { employee: Employee }) {
  const tz = useCompanyTz();
  const q = attendance.useList({ 'filter[employee_id]': employee.id, per_page: 20, sort: '-date' });
  const rows = q.data?.data ?? [];
  return (
    <Card>
      <CardHeader title="Recent attendance" description={`Times in ${tz}`} actions={<Button size="sm" variant="ghost" href={`/hr/attendance?f.employee_id=${employee.id}`}>View all</Button>} />
      <CardBody padding="none">
        {q.error ? <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} /> : q.isLoading ? <Loading /> : rows.length === 0 ? (
          <EmptyState size="sm" icon={<Clock size={20} />} title="No attendance recorded" />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines}>
              <thead><tr><th>Date</th><th>Status</th><th>In</th><th>Out</th><th className={crud.r}>Worked</th><th className={crud.r}>Late</th><th>Source</th></tr></thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.id}>
                    <td>{formatDate(r.date, 'EEE dd MMM yyyy')}</td>
                    <td><AttendanceBadge status={r.status} /></td>
                    <td>{formatTimeTz(r.check_in, tz)}</td>
                    <td>{formatTimeTz(r.check_out, tz)}</td>
                    <td className={crud.r}>{minutesLabel(r.worked_minutes)}</td>
                    <td className={crud.r}>{r.late_minutes ? minutesLabel(r.late_minutes) : '—'}</td>
                    <td>{r.source === 'self' ? 'Self-service' : r.source}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>
    </Card>
  );
}

// ---- Leave ------------------------------------------------------------------------

export function LeaveTab({ employee }: { employee: Employee }) {
  const tz = useCompanyTz();
  const year = Number(todayInTz(tz).slice(0, 4));
  const balances = leaveBalances.useList({ 'filter[employee_id]': employee.id, 'filter[year]': year, per_page: 50 });
  const requests = leaveRequests.useList({ 'filter[employee_id]': employee.id, per_page: 15, sort: '-start_date' });
  const b = balances.data?.data ?? [];
  const r = requests.data?.data ?? [];

  return (
    <div className={styles.stack}>
      <Card>
        <CardHeader title={`Balances ${year}`} />
        <CardBody>
          {balances.error ? <ErrorState size="sm" error={balances.error} onRetry={() => void balances.refetch()} /> : balances.isLoading ? <Loading /> : b.length === 0 ? (
            <EmptyState size="sm" icon={<CalendarDays size={20} />} title="No balances for this year" description="Generate balances from Leave → Balances." />
          ) : (
            <div className={styles.balances}>
              {b.map((x) => (
                <div key={x.id} className={styles.balance} style={{ borderLeftColor: x.leave_type?.color ?? undefined }}>
                  <span className={styles.balanceName}>{x.leave_type?.name ?? 'Leave'}</span>
                  <strong>{formatNumber(x.remaining, { maximumFractionDigits: 1 })}</strong>
                  <span>remaining · {formatNumber(x.used, { maximumFractionDigits: 1 })} used of {formatNumber(Number(x.entitled) + Number(x.carried_forward) + Number(x.adjusted), { maximumFractionDigits: 1 })}</span>
                </div>
              ))}
            </div>
          )}
        </CardBody>
      </Card>
      <Card>
        <CardHeader title="Leave requests" actions={<Button size="sm" variant="ghost" href="/hr/leave?tab=all">All requests</Button>} />
        <CardBody padding="none">
          {requests.error ? <ErrorState size="sm" error={requests.error} onRetry={() => void requests.refetch()} /> : requests.isLoading ? <Loading /> : r.length === 0 ? (
            <EmptyState size="sm" title="No leave requests" />
          ) : (
            <div className={crud.tableScroll}>
              <table className={crud.lines}>
                <thead><tr><th>Type</th><th>Dates</th><th className={crud.r}>Days</th><th>Status</th><th>Reason</th></tr></thead>
                <tbody>
                  {r.map((x) => (
                    <tr key={x.id}>
                      <td><span className={styles.row}><Swatch color={x.leave_type?.color} />{x.leave_type?.name}</span></td>
                      <td>{formatDate(x.start_date)}{x.end_date !== x.start_date ? ` – ${formatDate(x.end_date)}` : ''}</td>
                      <td className={crud.r}>{formatNumber(x.days, { maximumFractionDigits: 1 })}</td>
                      <td><StatusBadge status={x.status} /></td>
                      <td>{x.rejection_reason ?? x.reason ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

// ---- Shifts -----------------------------------------------------------------------

function AssignShiftDrawer({ open, employee, onClose }: { open: boolean; employee: Employee; onClose: () => void }) {
  const tz = useCompanyTz();
  const shiftOpts = useShiftOptions(open);
  const invalidate = useInvalidate();
  const toast = useToast();
  const [shiftId, setShiftId] = useState('');
  const [from, setFrom] = useState(() => todayInTz(tz));
  const [to, setTo] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const assign = useMutation<unknown, ApiError>({
    mutationFn: () => hrApi.assignShift(employee.id, { shift_id: Number(shiftId), effective_from: from, effective_to: to || null }),
    onSuccess: () => { void invalidate('employee-shifts'); toast.success('Shift assigned'); onClose(); },
    onError: (err) => {
      if (err.status === 422 && err.errors) setErrors(Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, v[0] ?? 'Invalid'])));
      else setErrors({ _: err.message });
    },
  });
  const submit = () => {
    const next: Record<string, string> = {};
    if (!shiftId) next.shift_id = 'Choose a shift';
    if (!from) next.effective_from = 'Start date is required';
    if (to && from && to < from) next.effective_to = 'Must be on or after the start date';
    setErrors(next);
    if (!Object.keys(next).length) assign.mutate();
  };

  return (
    <Drawer open={open} onClose={onClose} title="Assign shift" description={`${employee.name} — an open-ended earlier assignment is closed the day before the new one starts.`} size="sm"
      footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button onClick={submit} loading={assign.isPending}>Assign</Button></>}>
      {errors._ && <Alert variant="danger" style={{ marginBottom: 16 }}>{errors._}</Alert>}
      <FormGrid columns={1}>
        <FormField label="Shift" required error={errors.shift_id}>
          <Select value={shiftId} onChange={(e) => setShiftId(e.target.value)} placeholder={shiftOpts.query.isLoading ? 'Loading…' : 'Choose a shift'} options={shiftOpts.options} />
        </FormField>
        <FormField label="Effective from" required error={errors.effective_from}><DateInput value={from || null} onValueChange={(d) => setFrom(d ?? '')} /></FormField>
        <FormField label="Effective to" hint="Leave empty for open-ended" error={errors.effective_to}><DateInput value={to || null} min={from || undefined} onValueChange={(d) => setTo(d ?? '')} /></FormField>
      </FormGrid>
    </Drawer>
  );
}

export function ShiftsTab({ employee, canAssign }: { employee: Employee; canAssign: boolean }) {
  const companyId = useCompanyId();
  const tz = useCompanyTz();
  const today = todayInTz(tz);
  const q = useQuery({ queryKey: [companyId, 'employee-shifts', employee.id], queryFn: () => hrApi.shiftAssignments(employee.id) });
  const [assigning, setAssigning] = useState(false);
  const rows = q.data ?? [];
  const current = rows.find((a) => a.effective_from <= today && (!a.effective_to || a.effective_to >= today));

  return (
    <Card>
      <CardHeader
        title="Shift assignments"
        description={current ? `Current: ${current.shift?.name ?? 'Shift'}` : 'No current assignment — the default shift applies.'}
        actions={canAssign && <Button size="sm" variant="secondary" leftIcon={<CalendarClock size={14} />} onClick={() => setAssigning(true)}>Assign shift</Button>}
      />
      <CardBody padding="none">
        {q.error ? <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} /> : q.isLoading ? <Loading /> : rows.length === 0 ? (
          <EmptyState size="sm" icon={<CalendarClock size={20} />} title="No shift assignments" />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines}>
              <thead><tr><th>Shift</th><th>From</th><th>To</th><th /></tr></thead>
              <tbody>
                {rows.map((a) => (
                  <tr key={a.id}>
                    <td>{a.shift?.name ?? `#${a.shift_id}`}</td>
                    <td>{formatDate(a.effective_from)}</td>
                    <td>{a.effective_to ? formatDate(a.effective_to) : 'Open-ended'}</td>
                    <td>{a === current && <StatusBadge status="active" label="Current" />}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>
      <AssignShiftDrawer key={assigning ? 'open' : 'closed'} open={assigning} employee={employee} onClose={() => setAssigning(false)} />
    </Card>
  );
}

// ---- Performance ------------------------------------------------------------------

export function PerformanceTab({ employee }: { employee: Employee }) {
  const q = reviews.useList({ 'filter[employee_id]': employee.id, per_page: 20, sort: '-period_end' });
  const rows = q.data?.data ?? [];
  return (
    <Card>
      <CardHeader title="Performance reviews" actions={<Button size="sm" variant="ghost" href={`/hr/performance?f.employee_id=${employee.id}`}>Open reviews</Button>} />
      <CardBody padding="none">
        {q.error ? <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} /> : q.isLoading ? <Loading /> : rows.length === 0 ? (
          <EmptyState size="sm" icon={<ClipboardCheck size={20} />} title="No reviews yet" />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines}>
              <thead><tr><th>Cycle</th><th>Period</th><th>Reviewer</th><th>Rating</th><th>Status</th></tr></thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.id}>
                    <td><Link href={`/hr/performance/${r.id}`}>{r.cycle}</Link></td>
                    <td>{formatDate(r.period_start)} – {formatDate(r.period_end)}</td>
                    <td>{r.reviewer?.name ?? '—'}</td>
                    <td><Stars rating={r.overall_rating} /></td>
                    <td><StatusBadge status={r.status} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>
    </Card>
  );
}
