'use client';

import { Suspense, use, useRef, useState } from 'react';
import Link from 'next/link';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { useMutation } from '@tanstack/react-query';
import { Camera, Pencil, RotateCcw, UserMinus } from 'lucide-react';
import {
  Alert, Avatar, Button, Card, CardBody, CardHeader, ConfirmDialog, DateInput, ErrorState, FormField, Modal, PageHeader, SkeletonCard, StatusBadge, Tab,
  TabList, TabPanel, Tabs, Textarea, useToast,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import type { ApiError } from '@/lib/api/errors';
import { formatDate, formatMoney, humanize } from '@/lib/format';
import { PHOTO_MAX_BYTES, employees, hrApi, typeLabel, useInvalidate, useMyEmployee, type Employee } from '@/features/hr/api';
import { ManagerDialog } from '@/features/hr/profile/ManagerDialog';
import { ContractsTab } from '@/features/hr/profile/ContractsTab';
import { DocumentsTab } from '@/features/hr/profile/DocumentsTab';
import { AttendanceTab, LeaveTab, PerformanceTab, ShiftsTab } from '@/features/hr/profile/ActivityTabs';
import { todayInTz, useCompanyTz } from '@/features/hr/time';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/hr/hr.module.scss';

function TerminateDialog({ open, employee, onClose }: { open: boolean; employee: Employee; onClose: () => void }) {
  const tz = useCompanyTz();
  const invalidate = useInvalidate();
  const toast = useToast();
  const [date, setDate] = useState(() => todayInTz(tz));
  const [reason, setReason] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const terminate = useMutation<Employee, ApiError>({
    mutationFn: () => hrApi.terminate(employee.id, { terminated_at: date, reason: reason.trim() }),
    onSuccess: () => {
      void invalidate('employees');
      toast.success(`${employee.name} terminated`, 'Their company login has been deactivated.');
      onClose();
    },
    onError: (e) => {
      if (e.status === 422 && e.errors) setErrors({ date: e.errors.terminated_at?.[0] ?? '', reason: e.errors.reason?.[0] ?? '' });
      else setErrors({ _: e.message });
    },
  });
  const submit = () => {
    const next: Record<string, string> = {};
    if (!date) next.date = 'Choose the last working date';
    if (!reason.trim()) next.reason = 'A reason is required';
    setErrors(next);
    if (!Object.keys(next).length) terminate.mutate();
  };

  return (
    <Modal open={open} onClose={onClose} size="sm" title={`Terminate ${employee.name}?`}
      description="The employee is marked terminated and drops out of the directory, org chart and bulk attendance. Active contracts are terminated and a linked login loses access to this company."
      footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button variant="danger" onClick={submit} loading={terminate.isPending}>Terminate</Button></>}>
      {errors._ && <Alert variant="danger" style={{ marginBottom: 12 }}>{errors._}</Alert>}
      <FormField label="Termination date" required error={errors.date || undefined}><DateInput value={date || null} onValueChange={(d) => setDate(d ?? '')} /></FormField>
      <div style={{ height: 12 }} />
      <FormField label="Reason" required error={errors.reason || undefined}><Textarea rows={3} maxLength={500} value={reason} onChange={(e) => setReason(e.target.value)} /></FormField>
    </Modal>
  );
}

function Overview({ e, canUpdate }: { e: Employee; canUpdate: boolean }) {
  const [changingManager, setChangingManager] = useState(false);
  const addr = [e.address.line1, e.address.line2, [e.address.city, e.address.state, e.address.postal_code].filter(Boolean).join(' '), e.address.country].filter(Boolean).join(', ');
  return (
    <div className={crud.detailGrid}>
      <div className={styles.stack}>
        <Card>
          <CardHeader title="Employment" />
          <CardBody>
            <dl className={crud.dl}>
              <dt>Employee code</dt><dd className={crud.mono}>{e.employee_code}</dd>
              <dt>Designation</dt><dd>{e.designation?.title ?? '—'}</dd>
              <dt>Department</dt><dd>{e.department?.name ?? '—'}</dd>
              <dt>Branch</dt><dd>{e.branch?.name ?? '—'}</dd>
              <dt>Reports to</dt>
              <dd className={styles.managerCell}>
                {e.manager ? <Link href={`/hr/employees/${e.manager.id}`}>{e.manager.name}</Link> : <span>Nobody</span>}
                {canUpdate && !e.terminated_at && (
                  <Button size="sm" variant="ghost" leftIcon={<Pencil size={12} />} onClick={() => setChangingManager(true)}>
                    Change
                  </Button>
                )}
              </dd>
              <dt>Direct reports</dt><dd>{e.reports_count ?? 0}</dd>
              <dt>Employment type</dt><dd>{typeLabel(e.employment_type)}</dd>
              <dt>Hired</dt><dd>{formatDate(e.hired_at)}</dd>
              <dt>Probation ends</dt><dd>{formatDate(e.probation_ends_at)}</dd>
              {e.terminated_at && (<><dt>Terminated</dt><dd>{formatDate(e.terminated_at)}{e.termination_reason ? ` — ${e.termination_reason}` : ''}</dd></>)}
              <dt>Base salary</dt><dd>{e.base_salary ? formatMoney(e.base_salary, e.currency ?? 'USD') : '—'}</dd>
              <dt>Linked login</dt><dd>{e.user?.email ?? 'Not linked'}</dd>
            </dl>
          </CardBody>
        </Card>
        {e.notes && (
          <Card>
            <CardHeader title="Notes" />
            <CardBody><p style={{ whiteSpace: 'pre-wrap', margin: 0 }}>{e.notes}</p></CardBody>
          </Card>
        )}
      </div>
      <div className={styles.stack}>
        <Card>
          <CardHeader title="Personal & contact" />
          <CardBody>
            <dl className={crud.dl}>
              <dt>Email</dt><dd><a href={`mailto:${e.email}`}>{e.email}</a></dd>
              <dt>Phone</dt><dd>{e.phone ? <a href={`tel:${e.phone}`}>{e.phone}</a> : '—'}</dd>
              <dt>Date of birth</dt><dd>{formatDate(e.date_of_birth)}</dd>
              <dt>Gender</dt><dd>{e.gender ? humanize(e.gender) : '—'}</dd>
              <dt>Marital status</dt><dd>{e.marital_status ? humanize(e.marital_status) : '—'}</dd>
              <dt>Address</dt><dd>{addr || '—'}</dd>
              <dt>Emergency contact</dt>
              <dd>{e.emergency_contact.name ? `${e.emergency_contact.name}${e.emergency_contact.relationship ? ` (${e.emergency_contact.relationship})` : ''}${e.emergency_contact.phone ? ` · ${e.emergency_contact.phone}` : ''}` : '—'}</dd>
            </dl>
          </CardBody>
        </Card>
        {e.sensitive && (
          <Card>
            <CardHeader title="Identity, bank & tax" description="Masked — full values are never displayed." />
            <CardBody>
              <dl className={crud.dl}>
                <dt>National ID</dt><dd className={crud.mono}>{e.sensitive.national_id ?? '—'}</dd>
                <dt>Tax identifier</dt><dd className={crud.mono}>{e.sensitive.tax_identifier ?? '—'}</dd>
                <dt>Bank</dt><dd>{e.bank_name ?? '—'}</dd>
                <dt>Account number</dt><dd className={crud.mono}>{e.sensitive.bank_account_number ?? '—'}</dd>
                <dt>IBAN</dt><dd className={crud.mono}>{e.sensitive.bank_iban ?? '—'}</dd>
              </dl>
            </CardBody>
          </Card>
        )}
      </div>
      {canUpdate && <ManagerDialog key={changingManager ? 'open' : 'closed'} open={changingManager} employee={e} onClose={() => setChangingManager(false)} />}
    </div>
  );
}

const TABS = ['overview', 'contracts', 'documents', 'attendance', 'leave', 'shifts', 'performance'] as const;

function ProfileInner({ id }: { id: string }) {
  const router = useRouter();
  const pathname = usePathname();
  const search = useSearchParams();
  const can = useCan();
  const toast = useToast();
  const invalidate = useInvalidate();
  const me = useMyEmployee();
  const q = employees.useOne(id);
  const fileRef = useRef<HTMLInputElement>(null);
  const [dialog, setDialog] = useState<'terminate' | 'reactivate' | null>(null);
  const reactivate = employees.useAction('reactivate', { success: 'Employee reactivated' });
  const photo = useMutation<Employee, ApiError, File>({
    mutationFn: (f) => hrApi.uploadPhoto(Number(id), f),
    onSuccess: () => { void invalidate('employees'); toast.success('Photo updated'); },
    onError: (e) => toast.error('Could not upload photo', e.errors?.photo?.[0] ?? e.message),
  });

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const e = q.data;
  const canUpdate = can('employees.update');
  const isSelf = me.employeeId === e.id;
  const visible = {
    overview: true,
    contracts: can('employees.view'),
    documents: can('employees.documents.view') || isSelf,
    attendance: can('attendance.view'),
    leave: can(['leave.view', 'leave.approve', 'leave.manage'], 'any'),
    shifts: can(['shifts.manage', 'attendance.view', 'employees.view'], 'any'),
    performance: can(['performance.view', 'performance.manage'], 'any') || isSelf,
  };
  const requested = search.get('tab') as (typeof TABS)[number] | null;
  const tab = requested && TABS.includes(requested) && visible[requested] ? requested : 'overview';
  const setTab = (t: string) => {
    const next = new URLSearchParams(search.toString());
    if (t === 'overview') next.delete('tab');
    else next.set('tab', t);
    router.replace(next.toString() ? `${pathname}?${next}` : pathname, { scroll: false });
  };
  const terminated = e.status === 'terminated';

  return (
    <div className={styles.stack}>
      <PageHeader
        backHref="/hr/employees"
        backLabel="Employees"
        title={
          <span className={styles.profileHead}>
            <span className={styles.photoWrap}>
              <Avatar name={e.name} src={e.photo_url ?? undefined} size="xl" />
              {canUpdate && (
                <>
                  <button type="button" className={styles.photoBtn} aria-label="Change photo" title="Change photo" onClick={() => fileRef.current?.click()} disabled={photo.isPending}>
                    <Camera size={14} aria-hidden />
                  </button>
                  <input
                    ref={fileRef}
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    className={styles.hiddenInput}
                    tabIndex={-1}
                    aria-hidden
                    onChange={(ev) => {
                      const f = ev.target.files?.[0];
                      ev.target.value = '';
                      if (!f) return;
                      if (f.size > PHOTO_MAX_BYTES) toast.error('Photo too large', 'Choose an image up to 2 MB.');
                      else photo.mutate(f);
                    }}
                  />
                </>
              )}
            </span>
            <span>{e.name}</span>
          </span>
        }
        description={[e.designation?.title, e.department?.name, e.employee_code].filter(Boolean).join(' · ')}
        meta={<StatusBadge status={e.status} />}
        actions={
          canUpdate && (
            <>
              {terminated ? (
                <Button variant="secondary" leftIcon={<RotateCcw size={16} />} onClick={() => setDialog('reactivate')}>Reactivate</Button>
              ) : (
                <Button variant="ghost" leftIcon={<UserMinus size={16} />} onClick={() => setDialog('terminate')}>Terminate</Button>
              )}
              <Button leftIcon={<Pencil size={16} />} href={`/hr/employees/${e.id}/edit`}>Edit</Button>
            </>
          )
        }
      />
      {terminated && (
        <Alert variant="warning" title={`Terminated on ${formatDate(e.terminated_at)}`}>
          {e.termination_reason ?? 'No reason recorded.'} Reactivating sets them back to active and re-enables a linked login for this company.
        </Alert>
      )}

      <Tabs value={tab} onValueChange={setTab}>
        <TabList>
          <Tab value="overview">Overview</Tab>
          {visible.contracts && <Tab value="contracts">Contracts</Tab>}
          {visible.documents && <Tab value="documents">Documents</Tab>}
          {visible.attendance && <Tab value="attendance">Attendance</Tab>}
          {visible.leave && <Tab value="leave">Leave</Tab>}
          {visible.shifts && <Tab value="shifts">Shifts</Tab>}
          {visible.performance && <Tab value="performance">Performance</Tab>}
        </TabList>
        <TabPanel value="overview"><div className={styles.tabBody}><Overview e={e} canUpdate={canUpdate} /></div></TabPanel>
        {visible.contracts && <TabPanel value="contracts"><div className={styles.tabBody}><ContractsTab employee={e} canManage={canUpdate} /></div></TabPanel>}
        {visible.documents && <TabPanel value="documents"><div className={styles.tabBody}><DocumentsTab employee={e} canManage={canUpdate} /></div></TabPanel>}
        {visible.attendance && <TabPanel value="attendance"><div className={styles.tabBody}><AttendanceTab employee={e} /></div></TabPanel>}
        {visible.leave && <TabPanel value="leave"><div className={styles.tabBody}><LeaveTab employee={e} /></div></TabPanel>}
        {visible.shifts && <TabPanel value="shifts"><div className={styles.tabBody}><ShiftsTab employee={e} canAssign={can('shifts.manage') && canUpdate} /></div></TabPanel>}
        {visible.performance && <TabPanel value="performance"><div className={styles.tabBody}><PerformanceTab employee={e} /></div></TabPanel>}
      </Tabs>

      <TerminateDialog key={dialog === 'terminate' ? 'open' : 'closed'} open={dialog === 'terminate'} employee={e} onClose={() => setDialog(null)} />
      <ConfirmDialog
        open={dialog === 'reactivate'}
        onClose={() => setDialog(null)}
        title={`Reactivate ${e.name}?`}
        description="The employee returns to active status, reappears in the directory and org chart, and a linked login regains access."
        confirmLabel="Reactivate"
        onConfirm={async () => {
          await reactivate.mutateAsync({ id: e.id }).catch(() => undefined);
          setDialog(null);
        }}
      />
    </div>
  );
}

export default function EmployeeProfilePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  return (
    <Suspense>
      <ProfileInner id={id} />
    </Suspense>
  );
}
