'use client';

import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { LogIn, LogOut, Save } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, DateInput, Drawer, EmptyState, ErrorState, FormField, FormGrid, Input, Select, SkeletonCard, SkeletonTable,
  Textarea, useToast,
} from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { formatDate } from '@/lib/format';
import { useCan } from '@/lib/auth/useCan';
import { departments } from '@/features/company/api';
import {
  ATTENDANCE_STATUSES, attendance, hrApi, useCompanyId, useDirectory, useEmployeeOptions, useInvalidate, useMyEmployee, type AttendanceRecord,
  type AttendanceStatus,
} from './api';
import { AttendanceBadge, NotLinkedNotice, nul } from './components';
import { formatTimeTz, minutesLabel, timeInputTz, todayInTz, useCompanyTz, zonedToIso } from './time';
import crud from '@/components/crud/crud.module.scss';
import styles from './hr.module.scss';

/** Live HH:mm:ss in the company zone. */
function useClock(tz: string) {
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    const t = setInterval(() => setNow(new Date()), 1000);
    return () => clearInterval(t);
  }, []);
  return new Intl.DateTimeFormat('en-US', { timeZone: tz, hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).format(now);
}

/** Next calendar day for yyyy-MM-dd. */
function nextDay(date: string) {
  const d = new Date(`${date}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + 1);
  return d.toISOString().slice(0, 10);
}

/** Combine a date with in/out wall-clock times; an out time before the in time rolls to the next day (night shifts). */
export function toInstants(date: string, inTime: string, outTime: string, tz: string) {
  const check_in = inTime ? zonedToIso(date, inTime, tz) : null;
  const check_out = outTime ? zonedToIso(inTime && outTime <= inTime ? nextDay(date) : date, outTime, tz) : null;
  return { check_in, check_out };
}

// ---- Self-service ------------------------------------------------------------------

export function SelfServiceCard() {
  const tz = useCompanyTz();
  const can = useCan();
  const toast = useToast();
  const invalidate = useInvalidate();
  const clock = useClock(tz);
  const me = useMyEmployee();
  const today = todayInTz(tz);
  const [latest, setLatest] = useState<AttendanceRecord | null>(null);
  const todayQ = attendance.useList(
    { 'filter[employee_id]': me.employeeId ?? 0, from: today, to: today, per_page: 1 },
    { enabled: !!me.employeeId && can('attendance.view') },
  );
  const record = latest ?? todayQ.data?.data[0] ?? null;
  const onDone = (verb: string) => (r: AttendanceRecord) => {
    setLatest(r);
    void invalidate('attendance');
    toast.success(`${verb} at ${formatTimeTz(verb === 'Checked in' ? r.check_in : r.check_out, tz)}`);
  };
  const onError = (e: ApiError) => {
    if (e.status === 409) {
      toast.warning('Already recorded', e.message);
      void todayQ.refetch();
    }
    else toast.error('Could not record attendance', e.errors?.employee?.[0] ?? e.message);
  };
  const checkIn = useMutation<AttendanceRecord, ApiError>({ mutationFn: hrApi.checkIn, onSuccess: onDone('Checked in'), onError });
  const checkOut = useMutation<AttendanceRecord, ApiError>({ mutationFn: hrApi.checkOut, onSuccess: onDone('Checked out'), onError });

  if (me.loading) return <SkeletonCard lines={2} />;
  if (me.notLinked) return <NotLinkedNotice message={me.linkMessage} compact />;
  if (!me.linked) return null;

  const checkedIn = !!record?.check_in;
  const checkedOut = !!record?.check_out;

  return (
    <Card>
      <CardBody>
        <div className={styles.clock}>
          <div>
            <div className={styles.small}>{formatDate(today, 'EEEE, dd MMM yyyy')} · {tz}</div>
            <div className={styles.clockTime} aria-live="off">{clock}</div>
          </div>
          <dl className={styles.clockFacts}>
            <div><dt>Status</dt><dd>{record ? <AttendanceBadge status={record.status} /> : <span className={styles.muted}>Not checked in</span>}</dd></div>
            <div><dt>Checked in</dt><dd>{formatTimeTz(record?.check_in, tz)}</dd></div>
            <div><dt>Checked out</dt><dd>{formatTimeTz(record?.check_out, tz)}</dd></div>
            {record?.late_minutes ? <div><dt>Late</dt><dd>{minutesLabel(record.late_minutes)}</dd></div> : null}
            {checkedOut && <div><dt>Worked</dt><dd>{minutesLabel(record?.worked_minutes)}</dd></div>}
          </dl>
          <div className={styles.row}>
            <Button leftIcon={<LogIn size={16} />} onClick={() => checkIn.mutate()} loading={checkIn.isPending} disabled={checkedIn}>Check in</Button>
            <Button variant="secondary" leftIcon={<LogOut size={16} />} onClick={() => checkOut.mutate()} loading={checkOut.isPending} disabled={(!checkedIn && !!record) || checkedOut}>Check out</Button>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

// ---- Create / edit drawer -----------------------------------------------------------

const time = z.string().refine((v) => v === '' || /^\d{2}:\d{2}$/.test(v), 'Use HH:MM');
const schema = z.object({
  employee_id: z.string().min(1, 'Choose an employee'),
  date: z.string().min(1, 'Date is required'),
  status: z.enum(['present', 'absent', 'late', 'half_day', 'on_leave', 'holiday', 'weekend']),
  check_in: time,
  check_out: time,
  notes: z.string().max(500),
}).refine((v) => !v.check_out || !!v.check_in, { path: ['check_out'], message: 'Enter a check-in time first' });
type Values = z.infer<typeof schema>;

export function AttendanceForm({ open, row, onClose }: { open: boolean; row: AttendanceRecord | null; onClose: () => void }) {
  const tz = useCompanyTz();
  const emp = useEmployeeOptions(open && !row);
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, control, setError, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      employee_id: row ? String(row.employee_id) : '',
      date: row?.date ?? todayInTz(tz),
      status: row?.status ?? 'present',
      check_in: timeInputTz(row?.check_in, tz),
      check_out: timeInputTz(row?.check_out, tz),
      notes: row?.notes ?? '',
    },
  });
  const create = attendance.useCreate({ onSuccess: onClose });
  const update = attendance.useUpdate({ onSuccess: onClose });
  const e = formState.errors;

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const { check_in, check_out } = toInstants(v.date, v.check_in, v.check_out, tz);
    const body: Record<string, unknown> = { status: v.status, check_in, check_out, notes: nul(v.notes) };
    try {
      if (row) await update.mutateAsync({ id: row.id, body });
      else await create.mutateAsync({ ...body, employee_id: Number(v.employee_id), date: v.date, source: 'manual' });
    } catch (err) {
      setFormError(applyServerErrors(err as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  return (
    <Drawer open={open} onClose={onClose} size="sm"
      title={row ? 'Edit attendance' : 'Record attendance'}
      description={row ? `${row.employee?.name ?? 'Employee'} · ${formatDate(row.date)}` : 'Saving a second record for the same employee and day updates the first.'}
      footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button type="submit" form="attendance-form" loading={formState.isSubmitting}>{row ? 'Save changes' : 'Save record'}</Button></>}>
      <form id="attendance-form" noValidate onSubmit={handleSubmit(onSubmit)}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          {!row && (
            <>
              <FormField label="Employee" required span="full" error={e.employee_id?.message}>
                <Select {...register('employee_id')} placeholder={emp.query.isLoading ? 'Loading…' : 'Choose an employee'} options={emp.options} />
              </FormField>
              <FormField label="Date" required span="full" error={e.date?.message}>
                <Controller control={control} name="date" render={({ field }) => <DateInput value={field.value || null} onValueChange={(d) => field.onChange(d ?? '')} />} />
              </FormField>
            </>
          )}
          <FormField label="Status" required span="full" error={e.status?.message}><Select {...register('status')} options={ATTENDANCE_STATUSES} /></FormField>
          <FormField label="Check in" hint={tz} error={e.check_in?.message}><Input type="time" {...register('check_in')} /></FormField>
          <FormField label="Check out" hint="Earlier than check-in = next day" error={e.check_out?.message}><Input type="time" {...register('check_out')} /></FormField>
          <FormField label="Notes" span="full" error={e.notes?.message}><Textarea rows={2} maxLength={500} {...register('notes')} /></FormField>
        </FormGrid>
        <p className={styles.small} style={{ marginTop: 12 }}>Worked and late minutes are calculated from the employee&apos;s shift when you save.</p>
      </form>
    </Drawer>
  );
}

// ---- Bulk entry -----------------------------------------------------------------------

type BulkRow = { status: AttendanceStatus | 'skip'; in: string; out: string };

export function BulkEntry({ onDone }: { onDone: () => void }) {
  const tz = useCompanyTz();
  const toast = useToast();
  const invalidate = useInvalidate();
  const directory = useDirectory();
  const deps = departments.useList({ per_page: 100, sort: 'name' });
  const [date, setDate] = useState(() => todayInTz(tz));
  const [dept, setDept] = useState('');
  const [rows, setRows] = useState<Record<number, BulkRow>>({});
  const [error, setError] = useState<string | null>(null);
  const [defaults, setDefaults] = useState<BulkRow>({ status: 'present', in: '09:00', out: '17:00' });
  const people = useMemo(() => (directory.data ?? []).filter((d) => d.status !== 'terminated' && (!dept || String(d.department_id) === dept)), [directory.data, dept]);
  const rowOf = (id: number): BulkRow => rows[id] ?? defaults;
  const patch = (id: number, p: Partial<BulkRow>) => setRows((r) => ({ ...r, [id]: { ...rowOf(id), ...p } }));
  const timed = (s: BulkRow['status']) => s === 'present' || s === 'late' || s === 'half_day';

  const save = useMutation<number, ApiError>({
    mutationFn: async () => {
      const records = people
        .map((p) => ({ p, r: rowOf(p.id) }))
        .filter(({ r }) => r.status !== 'skip')
        .map(({ p, r }) => {
          const { check_in, check_out } = timed(r.status) ? toInstants(date, r.in, r.out, tz) : { check_in: null, check_out: null };
          return { employee_id: p.id, status: r.status as AttendanceStatus, check_in, check_out };
        });
      if (!records.length) throw { status: 0, code: 'empty', message: 'Nothing to save — every row is set to skip.' } satisfies ApiError;
      let written = 0;
      for (let i = 0; i < records.length; i += 500) {
        written += (await hrApi.bulkAttendance({ date, records: records.slice(i, i + 500) })).data.written;
      }
      return written;
    },
    onSuccess: (n) => {
      void invalidate('attendance');
      toast.success(`${n} attendance record${n === 1 ? '' : 's'} saved`, formatDate(date));
      setError(null);
      onDone();
    },
    onError: (e) => setError(e.status === 422 && e.errors ? Object.values(e.errors)[0]?.[0] ?? e.message : e.message),
  });

  return (
    <Card>
      <CardHeader
        title="Bulk entry"
        description={`Record one day for many employees. Existing records for that day are overwritten. Times in ${tz}.`}
        actions={<Button leftIcon={<Save size={16} />} onClick={() => save.mutate()} loading={save.isPending} disabled={!date || !people.length}>Save {people.length} rows</Button>}
      />
      <CardBody>
        <div className={styles.stack}>
          {error && <Alert variant="danger">{error}</Alert>}
          <div className={styles.row}>
            <FormField label="Date" required><DateInput value={date || null} onValueChange={(d) => setDate(d ?? '')} /></FormField>
            <FormField label="Department">
              <Select value={dept} onChange={(e) => setDept(e.target.value)} placeholder="All departments" options={(deps.data?.data ?? []).map((d) => ({ value: String(d.id), label: d.name }))} />
            </FormField>
            <FormField label="Default status">
              <Select value={defaults.status} onChange={(e) => setDefaults({ ...defaults, status: e.target.value as BulkRow['status'] })}
                options={[...ATTENDANCE_STATUSES, { value: 'skip', label: '— Skip —' }]} />
            </FormField>
            <FormField label="Default in"><Input type="time" className={styles.timeInput} value={defaults.in} onChange={(e) => setDefaults({ ...defaults, in: e.target.value })} /></FormField>
            <FormField label="Default out"><Input type="time" className={styles.timeInput} value={defaults.out} onChange={(e) => setDefaults({ ...defaults, out: e.target.value })} /></FormField>
            <Button variant="ghost" size="sm" onClick={() => setRows({})} style={{ alignSelf: 'flex-end' }}>Reset rows to defaults</Button>
          </div>
          {directory.error ? <ErrorState size="sm" error={directory.error} onRetry={() => void directory.refetch()} /> : directory.isLoading ? <SkeletonTable rows={5} cols={4} /> : people.length === 0 ? (
            <EmptyState size="sm" title="No active employees" description={dept ? 'Nobody in this department.' : undefined} />
          ) : (
            <div className={crud.tableScroll}>
              <table className={`${crud.lines} ${styles.bulkTable}`}>
                <thead><tr><th>Employee</th><th>Status</th><th>Check in</th><th>Check out</th></tr></thead>
                <tbody>
                  {people.map((p) => {
                    const r = rowOf(p.id);
                    return (
                      <tr key={p.id}>
                        <td><div className={crud.cellMain}><strong>{p.name}</strong><span>{p.employee_code}{p.department ? ` · ${p.department}` : ''}</span></div></td>
                        <td>
                          <Select aria-label={`Status for ${p.name}`} size="sm" value={r.status} onChange={(e) => patch(p.id, { status: e.target.value as BulkRow['status'] })}
                            options={[...ATTENDANCE_STATUSES, { value: 'skip', label: '— Skip —' }]} />
                        </td>
                        <td><Input aria-label={`Check in for ${p.name}`} size="sm" type="time" className={styles.timeInput} disabled={!timed(r.status)} value={timed(r.status) ? r.in : ''} onChange={(e) => patch(p.id, { in: e.target.value })} /></td>
                        <td><Input aria-label={`Check out for ${p.name}`} size="sm" type="time" className={styles.timeInput} disabled={!timed(r.status)} value={timed(r.status) ? r.out : ''} onChange={(e) => patch(p.id, { out: e.target.value })} /></td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </CardBody>
    </Card>
  );
}

// ---- Summary ----------------------------------------------------------------------------

export function AttendanceSummary() {
  const tz = useCompanyTz();
  const companyId = useCompanyId();
  const today = todayInTz(tz);
  const [from, setFrom] = useState(`${today.slice(0, 8)}01`);
  const [to, setTo] = useState(today);
  const [dept, setDept] = useState('');
  const deps = departments.useList({ per_page: 100, sort: 'name' });
  const valid = !!from && !!to && from <= to;
  const q = useQuery({
    queryKey: [companyId, 'attendance', 'summary', from, to, dept],
    queryFn: () => hrApi.attendanceSummary({ from, to, department_id: dept ? Number(dept) : undefined }),
    enabled: valid,
  });
  const rows = q.data?.data ?? [];

  return (
    <Card>
      <CardHeader title="Summary by employee" description="Day counts and hours for the period." />
      <CardBody>
        <div className={styles.stack}>
          <div className={styles.row}>
            <DateInput aria-label="From" value={from || null} max={to || undefined} onValueChange={(d) => setFrom(d ?? '')} />
            <DateInput aria-label="To" value={to || null} min={from || undefined} onValueChange={(d) => setTo(d ?? '')} />
            <Select aria-label="Department" value={dept} onChange={(e) => setDept(e.target.value)} placeholder="All departments"
              options={(deps.data?.data ?? []).map((d) => ({ value: String(d.id), label: d.name }))} />
          </div>
          {!valid ? <Alert variant="warning">Choose a start date on or before the end date.</Alert> : q.error ? (
            <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} />
          ) : q.isLoading ? <SkeletonTable rows={5} cols={7} /> : rows.length === 0 ? (
            <EmptyState size="sm" title="No attendance in this period" />
          ) : (
            <div className={crud.tableScroll}>
              <table className={crud.lines}>
                <thead>
                  <tr><th>Employee</th><th className={crud.r}>Present</th><th className={crud.r}>Late</th><th className={crud.r}>Half days</th><th className={crud.r}>Absent</th><th className={crud.r}>On leave</th><th className={crud.r}>Hours</th><th className={crud.r}>Late time</th></tr>
                </thead>
                <tbody>
                  {rows.map((r) => (
                    <tr key={r.employee_id}>
                      <td><div className={crud.cellMain}><strong>{r.name}</strong><span>{r.employee_code}</span></div></td>
                      <td className={crud.r}>{r.present_days}</td>
                      <td className={crud.r}>{r.late_days}</td>
                      <td className={crud.r}>{r.half_days}</td>
                      <td className={crud.r}>{r.absent_days}</td>
                      <td className={crud.r}>{r.leave_days}</td>
                      <td className={crud.r}>{r.worked_hours.toFixed(1)}</td>
                      <td className={crud.r}>{minutesLabel(r.late_minutes)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </CardBody>
    </Card>
  );
}
