'use client';

import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { CalendarPlus, Check, CalendarDays, Paperclip, Pencil, Plus, RefreshCw, Trash2, X } from 'lucide-react';
import {
  Alert, Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, DataTable, DateInput, Drawer, EmptyState, ErrorState, FilterBar, FormField, FormGrid, Input,
  MenuItem, MenuSeparator, Modal, NumberInput, Select, SkeletonCard, StatusBadge, Textarea, paginationFromMeta, useToast, type DataTableColumn,
} from '@/components/ui';
import { api } from '@/lib/api/client';
import { applyServerErrors } from '@/lib/crud/resource';
import { useListState } from '@/lib/crud/useListState';
import type { ApiError } from '@/lib/api/errors';
import { formatDate, formatNumber } from '@/lib/format';
import { departments } from '@/features/company/api';
import {
  LEAVE_STATUSES, UPLOAD_ACCEPT, UPLOAD_MAX_BYTES, hrApi, leaveBalances, leaveRequests, leaveTypes, useEmployeeOptions, useInvalidate, useLeaveTypeOptions,
  useMyEmployee, type LeaveBalance, type LeaveRequest, type LeaveType,
} from './api';
import { NotLinkedNotice, ReasonDialog, Swatch, nul } from './components';
import { LeaveTypeForm } from './SimpleForms';
import { todayInTz, useCompanyTz } from './time';
import crud from '@/components/crud/crud.module.scss';
import styles from './hr.module.scss';

const n1 = (v: string | number | null | undefined) => formatNumber(v, { maximumFractionDigits: 1 });

/** Mon–Fri count — a hint only; the server excludes holidays and uses the employee's shift. */
function weekdaysBetween(a: string, b: string) {
  if (!a || !b || b < a) return 0;
  let n = 0;
  const d = new Date(`${a}T00:00:00Z`);
  const end = new Date(`${b}T00:00:00Z`);
  while (d <= end && n < 400) {
    const w = d.getUTCDay();
    if (w !== 0 && w !== 6) n++;
    d.setUTCDate(d.getUTCDate() + 1);
  }
  return n;
}

/** Shows server 422 on approve (e.g. insufficient balance) — the generic resource hook only toasts non-422s. */
function useLeaveAction(action: 'approve' | 'reject' | 'cancel', success: string) {
  const invalidate = useInvalidate();
  const toast = useToast();
  return useMutation<LeaveRequest, ApiError, { id: number; reason?: string }>({
    mutationFn: ({ id, reason }) => api.post<{ data: LeaveRequest }>(`/leave-requests/${id}/${action}`, reason ? { reason } : {}).then((r) => r.data.data),
    onSuccess: () => {
      void invalidate('leave-requests', 'leave-balances');
      toast.success(success);
    },
    onError: (e) => {
      if (action === 'reject' && e.status === 422) return; // shown in the reason dialog
      toast.error(e.status === 409 ? 'Already handled' : 'Action failed', e.errors ? Object.values(e.errors)[0]?.[0] ?? e.message : e.message);
    },
  });
}

// ---- Request drawer -------------------------------------------------------------------

const requestSchema = z
  .object({
    leave_type_id: z.string().min(1, 'Choose a leave type'),
    start_date: z.string().min(1, 'Start date is required'),
    end_date: z.string().min(1, 'End date is required'),
    reason: z.string().max(1000),
    attachment: z.custom<File | null>().refine((f) => !(f instanceof File) || f.size <= UPLOAD_MAX_BYTES, 'The file is larger than 10 MB'),
  })
  .refine((v) => !v.end_date || !v.start_date || v.end_date >= v.start_date, { path: ['end_date'], message: 'End date must be on or after the start date' });
type RequestValues = z.infer<typeof requestSchema>;

export function LeaveRequestForm({ open, balances, onClose }: { open: boolean; balances: LeaveBalance[]; onClose: () => void }) {
  const tz = useCompanyTz();
  const types = useLeaveTypeOptions(true, open);
  const invalidate = useInvalidate();
  const toast = useToast();
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, control, setError, setValue, formState } = useForm<RequestValues>({
    resolver: zodResolver(requestSchema),
    defaultValues: { leave_type_id: '', start_date: todayInTz(tz), end_date: todayInTz(tz), reason: '', attachment: null },
  });
  const [typeId, start, end] = useWatch({ control, name: ['leave_type_id', 'start_date', 'end_date'] });
  const balance = balances.find((b) => String(b.leave_type_id) === typeId);
  const type = types.rows.find((t) => String(t.id) === typeId);
  const estimate = weekdaysBetween(start, end);
  const e = formState.errors;

  const onSubmit = async (v: RequestValues) => {
    setFormError(null);
    try {
      const r = await hrApi.requestLeave({ leave_type_id: Number(v.leave_type_id), start_date: v.start_date, end_date: v.end_date, reason: nul(v.reason), attachment: v.attachment });
      void invalidate('leave-requests', 'leave-balances');
      toast.success(r.status === 'approved' ? 'Leave approved' : 'Leave requested', `${n1(r.days)} working day${Number(r.days) === 1 ? '' : 's'}`);
      onClose();
    } catch (err) {
      const apiErr = err as ApiError;
      const left = applyServerErrors(apiErr, setError as never, ['leave_type_id', 'start_date', 'end_date', 'reason', 'attachment']);
      setFormError(apiErr.errors?.employee?.[0] ?? left);
    }
  };

  return (
    <Drawer open={open} onClose={onClose} title="Request leave" size="sm"
      footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button type="submit" form="leave-request-form" loading={formState.isSubmitting}>Submit request</Button></>}>
      <form id="leave-request-form" noValidate onSubmit={handleSubmit(onSubmit)}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          <FormField label="Leave type" required span="full" error={e.leave_type_id?.message}
            hint={type ? `${type.is_paid ? 'Paid' : 'Unpaid'}${type.requires_approval ? ' · needs approval' : ' · approved automatically'}${balance ? ` · ${n1(balance.remaining)} days left` : ''}` : undefined}>
            <Select {...register('leave_type_id')} placeholder={types.query.isLoading ? 'Loading…' : 'Choose a type'} options={types.options} />
          </FormField>
          <FormField label="From" required error={e.start_date?.message}>
            <Controller control={control} name="start_date" render={({ field }) => <DateInput value={field.value || null} onValueChange={(d) => field.onChange(d ?? '')} />} />
          </FormField>
          <FormField label="To" required error={e.end_date?.message}>
            <Controller control={control} name="end_date" render={({ field }) => <DateInput value={field.value || null} min={start || undefined} onValueChange={(d) => field.onChange(d ?? '')} />} />
          </FormField>
          <FormField label="Reason" span="full" error={e.reason?.message}><Textarea rows={3} maxLength={1000} {...register('reason')} /></FormField>
          <FormField label="Attachment" span="full" hint="Optional, e.g. a medical certificate (up to 10 MB)" error={e.attachment?.message}>
            <Input type="file" accept={UPLOAD_ACCEPT} onChange={(ev) => setValue('attachment', ev.target.files?.[0] ?? null, { shouldValidate: true })} />
          </FormField>
        </FormGrid>
        {estimate > 0 && (
          <p className={styles.small} style={{ marginTop: 12 }}>
            About {estimate} weekday{estimate === 1 ? '' : 's'}. The final count excludes public holidays and follows your shift&apos;s working days.
            {balance && type?.is_paid && estimate > Number(balance.remaining) ? ' This may exceed your remaining balance.' : ''}
          </p>
        )}
      </form>
    </Drawer>
  );
}

// ---- My leave --------------------------------------------------------------------------

export function BalanceCards({ balances }: { balances: LeaveBalance[] }) {
  if (!balances.length) return <EmptyState size="sm" icon={<CalendarDays size={20} />} title="No leave balances for this year" description="HR generates balances at the start of each year." />;
  return (
    <div className={styles.balances}>
      {balances.map((b) => {
        const total = Number(b.entitled) + Number(b.carried_forward) + Number(b.adjusted);
        const pct = total > 0 ? Math.min(100, (Number(b.used) / total) * 100) : 0;
        const color = b.leave_type?.color ?? 'var(--chart-1)';
        return (
          <div key={b.id} className={styles.balance} style={{ borderLeftColor: color }}>
            <span className={styles.balanceName}>{b.leave_type?.name ?? 'Leave'}</span>
            <strong>{n1(b.remaining)}</strong>
            <span>days left · {n1(b.used)} of {n1(total)} used{Number(b.carried_forward) ? ` · ${n1(b.carried_forward)} carried` : ''}</span>
            <div className={styles.meter} role="img" aria-label={`${Math.round(pct)}% used`}><span style={{ width: `${pct}%`, background: color }} /></div>
          </div>
        );
      })}
    </div>
  );
}

function RequestRows({ rows, onCancel, highlightId }: { rows: LeaveRequest[]; onCancel?: (r: LeaveRequest) => void; highlightId?: number | null }) {
  return (
    <div className={crud.tableScroll}>
      <table className={crud.lines}>
        <thead><tr><th>Type</th><th>Dates</th><th className={crud.r}>Days</th><th>Status</th><th>Notes</th>{onCancel && <th aria-label="Actions" />}</tr></thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.id} className={r.id === highlightId ? styles.highlightRow : undefined} aria-current={r.id === highlightId ? 'true' : undefined}>
              <td><span className={styles.row}><Swatch color={r.leave_type?.color} />{r.leave_type?.name}</span></td>
              <td>{formatDate(r.start_date)}{r.end_date !== r.start_date ? ` – ${formatDate(r.end_date)}` : ''}</td>
              <td className={crud.r}>{n1(r.days)}</td>
              <td><StatusBadge status={r.status} /></td>
              <td>
                {r.status === 'rejected' && r.rejection_reason ? <span>Rejected: {r.rejection_reason}</span> : r.reason ?? '—'}
                {r.has_attachment && <Paperclip size={12} aria-label="Has attachment" style={{ marginLeft: 6 }} />}
              </td>
              {onCancel && <td className={crud.r}>{r.status === 'pending' && <Button size="sm" variant="ghost" onClick={() => onCancel(r)}>Cancel</Button>}</td>}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function MyLeave({ canRequest, highlightId }: { canRequest: boolean; highlightId?: number | null }) {
  const me = useMyEmployee();
  const [requesting, setRequesting] = useState(false);
  const [cancelling, setCancelling] = useState<LeaveRequest | null>(null);
  const cancel = useLeaveAction('cancel', 'Leave request cancelled');
  const requests = leaveRequests.useList(
    { ...(me.employeeId ? { 'filter[employee_id]': me.employeeId } : {}), per_page: 25, sort: '-start_date' },
    { enabled: me.linked },
  );

  if (me.loading) return <SkeletonCard lines={3} />;
  if (me.notLinked) return <NotLinkedNotice message={me.linkMessage} />;
  if (me.balancesQuery.error) return <ErrorState error={me.balancesQuery.error} onRetry={() => void me.balancesQuery.refetch()} />;

  return (
    <div className={styles.stack}>
      <Card>
        <CardHeader
          title={`My balances ${new Date().getFullYear()}`}
          actions={canRequest && <Button leftIcon={<CalendarPlus size={16} />} onClick={() => setRequesting(true)}>Request leave</Button>}
        />
        <CardBody><BalanceCards balances={me.balances ?? []} /></CardBody>
      </Card>
      <Card>
        <CardHeader title="My requests" />
        <CardBody padding="none">
          {requests.error ? <ErrorState size="sm" error={requests.error} onRetry={() => void requests.refetch()} /> : requests.isLoading ? (
            <div style={{ padding: 16 }}><SkeletonCard lines={3} /></div>
          ) : !requests.data?.data.length ? (
            <EmptyState size="sm" title="No leave requests yet" />
          ) : (
            <RequestRows rows={requests.data.data} onCancel={setCancelling} highlightId={highlightId} />
          )}
        </CardBody>
      </Card>
      <LeaveRequestForm key={requesting ? 'open' : 'closed'} open={requesting} balances={me.balances ?? []} onClose={() => setRequesting(false)} />
      <ConfirmDialog
        open={cancelling !== null}
        onClose={() => setCancelling(null)}
        title="Cancel this leave request?"
        description={cancelling ? `${cancelling.leave_type?.name} · ${formatDate(cancelling.start_date)} – ${formatDate(cancelling.end_date)}` : undefined}
        destructive
        confirmLabel="Cancel request"
        cancelLabel="Keep"
        onConfirm={async () => {
          if (cancelling) await cancel.mutateAsync({ id: cancelling.id }).catch(() => undefined);
          setCancelling(null);
        }}
      />
    </div>
  );
}

// ---- Approvals & all requests -------------------------------------------------------------

export function RequestsTable({ mode, canApprove, canManage, highlightId }: { mode: 'approvals' | 'all'; canApprove: boolean; canManage: boolean; highlightId?: number | null }) {
  const list = useListState({ sort: mode === 'approvals' ? 'start_date' : '-created_at' });
  const { state, update, clearFilters, activeFilterCount } = list;
  const me = useMyEmployee();
  const { ['filter[department_id]']: dept, ...rest } = list.apiParams;
  const params: Record<string, unknown> = { ...rest, ...(dept ? { department_id: dept } : {}) };
  if (mode === 'approvals') params['filter[status]'] = 'pending';
  const query = leaveRequests.useList(params);
  const types = useLeaveTypeOptions(false);
  const emp = useEmployeeOptions();
  const deps = departments.useList({ per_page: 100, sort: 'name' });
  const approve = useLeaveAction('approve', 'Leave approved');
  const reject = useLeaveAction('reject', 'Leave rejected');
  const cancel = useLeaveAction('cancel', 'Leave request cancelled');
  const [rejecting, setRejecting] = useState<LeaveRequest | null>(null);
  const [approving, setApproving] = useState<LeaveRequest | null>(null);
  const meta = query.data?.meta?.pagination;
  const f = state.filters;
  // Approvers can't act on their own requests (the API forbids it), so hide them here.
  const data = (query.data?.data ?? []).filter((r) => mode !== 'approvals' || r.employee_id !== me.employeeId);
  const own = (r: LeaveRequest) => me.employeeId !== null && r.employee_id === me.employeeId;

  const columns: DataTableColumn<LeaveRequest>[] = [
    { id: 'employee', header: 'Employee', enableHiding: false, cell: ({ row }) => <div className={crud.cellMain}><strong>{row.original.employee?.name}</strong><span>{row.original.employee?.employee_code}</span></div> },
    { id: 'type', header: 'Type', cell: ({ row }) => <span className={styles.row}><Swatch color={row.original.leave_type?.color} />{row.original.leave_type?.name}{row.original.leave_type && !row.original.leave_type.is_paid && <Badge size="sm">Unpaid</Badge>}</span> },
    { id: 'start_date', accessorKey: 'start_date', header: 'Dates', enableSorting: true, cell: ({ row }) => `${formatDate(row.original.start_date)}${row.original.end_date !== row.original.start_date ? ` – ${formatDate(row.original.end_date)}` : ''}` },
    { id: 'days', accessorKey: 'days', header: 'Days', enableSorting: true, meta: { align: 'right', numeric: true }, cell: ({ row }) => n1(row.original.days) },
    { id: 'reason', header: 'Reason', cell: ({ row }) => <span>{row.original.reason ?? '—'}{row.original.has_attachment && <Paperclip size={12} aria-label="Has attachment" style={{ marginLeft: 6 }} />}</span> },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <StatusBadge status={row.original.status} /> },
    { id: 'created_at', accessorKey: 'created_at', header: 'Requested', enableSorting: true, cell: ({ row }) => formatDate(row.original.created_at) },
  ];
  if (mode === 'approvals' && canApprove) {
    columns.push({
      id: 'decide', header: 'Decision', enableHiding: false,
      cell: ({ row }) => (
        <div className={styles.row} style={{ flexWrap: 'nowrap' }} onClick={(ev) => ev.stopPropagation()}>
          <Button size="sm" leftIcon={<Check size={14} />} onClick={() => setApproving(row.original)}>Approve</Button>
          <Button size="sm" variant="secondary" leftIcon={<X size={14} />} onClick={() => setRejecting(row.original)}>Reject</Button>
        </div>
      ),
    });
  }

  return (
    <div className={styles.stack}>
      <FilterBar activeCount={activeFilterCount} onClear={clearFilters}>
        <DateInput aria-label="Starting from" value={state.from ?? null} max={state.to} onValueChange={(v) => update({ from: v ?? undefined })} />
        <DateInput aria-label="Starting until" value={state.to ?? null} min={state.from} onValueChange={(v) => update({ to: v ?? undefined })} />
        <Select aria-label="Employee" placeholder="Any employee" value={f.employee_id ?? ''} onChange={(e) => update({ filters: { employee_id: e.target.value || undefined } })} options={emp.options} />
        <Select aria-label="Department" placeholder="Any department" value={f.department_id ?? ''} onChange={(e) => update({ filters: { department_id: e.target.value || undefined } })}
          options={(deps.data?.data ?? []).map((d) => ({ value: String(d.id), label: d.name }))} />
        <Select aria-label="Leave type" placeholder="Any type" value={f.leave_type_id ?? ''} onChange={(e) => update({ filters: { leave_type_id: e.target.value || undefined } })} options={types.options} />
        {mode === 'all' && <Select aria-label="Status" placeholder="Any status" value={f.status ?? ''} onChange={(e) => update({ filters: { status: e.target.value || undefined } })} options={LEAVE_STATUSES} />}
      </FilterBar>
      <DataTable<LeaveRequest>
        aria-label={mode === 'approvals' ? 'Pending leave requests' : 'Leave requests'}
        columns={columns}
        data={data}
        isLoading={query.isLoading || (query.isFetching && query.isPlaceholderData)}
        error={query.error}
        onRetry={() => void query.refetch()}
        rowClassName={(r) => (r.id === highlightId ? styles.highlightRow : undefined)}
        sort={state.sort}
        onSortChange={(sort) => update({ sort })}
        pagination={meta ? { ...paginationFromMeta(meta), onPageChange: (page: number) => update({ page }, false), onPerPageChange: (perPage: number) => update({ perPage }) } : undefined}
        rowActions={mode === 'all' ? (r) => (
          <>
            {canApprove && r.status === 'pending' && !own(r) && (
              <>
                <MenuItem icon={<Check size={16} />} onSelect={() => setApproving(r)}>Approve</MenuItem>
                <MenuItem icon={<X size={16} />} onSelect={() => setRejecting(r)}>Reject</MenuItem>
              </>
            )}
            {r.status === 'pending' && (canManage || own(r)) && (
              <>
                <MenuSeparator />
                <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => cancel.mutate({ id: r.id })}>Cancel request</MenuItem>
              </>
            )}
          </>
        ) : undefined}
        emptyState={<EmptyState icon={<CalendarDays size={22} />} title={mode === 'approvals' ? 'Nothing waiting for approval' : 'No leave requests'} description={mode === 'approvals' ? 'New requests from your team will appear here.' : undefined} />}
      />
      <ConfirmDialog
        open={approving !== null}
        onClose={() => setApproving(null)}
        title={`Approve leave for ${approving?.employee?.name ?? 'employee'}?`}
        description={approving ? `${approving.leave_type?.name} · ${formatDate(approving.start_date)} – ${formatDate(approving.end_date)} · ${n1(approving.days)} day(s). Paid leave is deducted from the balance.` : undefined}
        confirmLabel="Approve"
        onConfirm={async () => {
          if (approving) await approve.mutateAsync({ id: approving.id }).catch(() => undefined);
          setApproving(null);
        }}
      />
      <ReasonDialog
        key={rejecting?.id ?? 'none'}
        open={rejecting !== null}
        onClose={() => setRejecting(null)}
        title={`Reject leave for ${rejecting?.employee?.name ?? 'employee'}?`}
        description="The employee sees this reason."
        confirmLabel="Reject"
        onSubmit={(reason) => (rejecting ? reject.mutateAsync({ id: rejecting.id, reason }) : Promise.resolve())}
      />
    </div>
  );
}

// ---- Balances admin -------------------------------------------------------------------------

const dec = z.string().trim().refine((v) => /^-?\d{1,3}(\.\d)?$/.test(v), 'Up to 999.9 with one decimal');
const adjustSchema = z.object({ entitled: dec, carried_forward: dec, adjusted: dec, note: z.string().max(500) });

function AdjustDrawer({ open, row, onClose }: { open: boolean; row: LeaveBalance; onClose: () => void }) {
  const invalidate = useInvalidate();
  const toast = useToast();
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, formState } = useForm<z.infer<typeof adjustSchema>>({
    resolver: zodResolver(adjustSchema),
    defaultValues: { entitled: String(Number(row.entitled)), carried_forward: String(Number(row.carried_forward)), adjusted: String(Number(row.adjusted)), note: '' },
  });
  const e = formState.errors;
  return (
    <Drawer open={open} onClose={onClose} size="sm" title="Adjust balance" description={`${row.employee?.name} · ${row.leave_type?.name} · ${row.year}`}
      footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button type="submit" form="adjust-form" loading={formState.isSubmitting}>Save</Button></>}>
      <form id="adjust-form" noValidate onSubmit={handleSubmit(async (v) => {
        setFormError(null);
        try {
          await hrApi.adjustBalance(row.id, { entitled: v.entitled, carried_forward: v.carried_forward, adjusted: v.adjusted, note: nul(v.note) });
          void invalidate('leave-balances');
          toast.success('Balance adjusted');
          onClose();
        } catch (err) {
          setFormError(applyServerErrors(err as ApiError, setError as never, Object.keys(adjustSchema.shape)));
        }
      })}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={1}>
          <FormField label="Entitled" error={e.entitled?.message}><Input inputMode="decimal" {...register('entitled')} /></FormField>
          <FormField label="Carried forward" error={e.carried_forward?.message}><Input inputMode="decimal" {...register('carried_forward')} /></FormField>
          <FormField label="Manual adjustment" hint="Positive adds days, negative removes them" error={e.adjusted?.message}><Input inputMode="decimal" {...register('adjusted')} /></FormField>
          <FormField label="Note" hint="Recorded in the audit log" error={e.note?.message}><Textarea rows={2} maxLength={500} {...register('note')} /></FormField>
        </FormGrid>
        <p className={styles.small} style={{ marginTop: 12 }}>Used: {n1(row.used)} days (from approved requests; not editable).</p>
      </form>
    </Drawer>
  );
}

export function BalancesAdmin() {
  const list = useListState({ sort: '-year' });
  const { state, update, clearFilters, activeFilterCount } = list;
  const currentYear = new Date().getFullYear();
  const year = state.filters.year ?? String(currentYear);
  const query = leaveBalances.useList({ ...list.apiParams, 'filter[year]': year });
  const emp = useEmployeeOptions();
  const types = useLeaveTypeOptions(false);
  const invalidate = useInvalidate();
  const toast = useToast();
  const [editing, setEditing] = useState<LeaveBalance | null>(null);
  const [genOpen, setGenOpen] = useState(false);
  const [genYear, setGenYear] = useState<number | null>(currentYear);
  const generate = useMutation<{ message: string }, ApiError, number>({
    mutationFn: (y) => hrApi.generateBalances(y),
    onSuccess: (r) => { void invalidate('leave-balances'); toast.success('Balances generated', r.message); setGenOpen(false); },
    onError: (e) => toast.error('Could not generate balances', e.errors?.year?.[0] ?? e.message),
  });
  const meta = query.data?.meta?.pagination;
  const f = state.filters;

  const columns: DataTableColumn<LeaveBalance>[] = [
    { id: 'employee', header: 'Employee', enableHiding: false, cell: ({ row }) => <div className={crud.cellMain}><strong>{row.original.employee?.name}</strong><span>{row.original.employee?.employee_code}</span></div> },
    { id: 'type', header: 'Type', cell: ({ row }) => <span className={styles.row}><Swatch color={row.original.leave_type?.color} />{row.original.leave_type?.name}</span> },
    { id: 'year', accessorKey: 'year', header: 'Year', enableSorting: true },
    { id: 'entitled', accessorKey: 'entitled', header: 'Entitled', enableSorting: true, meta: { align: 'right', numeric: true }, cell: ({ row }) => n1(row.original.entitled) },
    { id: 'carried', header: 'Carried', meta: { align: 'right', numeric: true }, cell: ({ row }) => n1(row.original.carried_forward) },
    { id: 'adjusted', header: 'Adjusted', meta: { align: 'right', numeric: true }, cell: ({ row }) => n1(row.original.adjusted) },
    { id: 'used', accessorKey: 'used', header: 'Used', enableSorting: true, meta: { align: 'right', numeric: true }, cell: ({ row }) => n1(row.original.used) },
    { id: 'remaining', header: 'Remaining', meta: { align: 'right', numeric: true }, cell: ({ row }) => <strong>{n1(row.original.remaining)}</strong> },
  ];

  return (
    <div className={styles.stack}>
      <div className={styles.between}>
        <FilterBar activeCount={activeFilterCount} onClear={clearFilters}>
          <Select aria-label="Year" value={year} onChange={(e) => update({ filters: { year: e.target.value } })}
            options={[currentYear + 1, currentYear, currentYear - 1, currentYear - 2].map((y) => ({ value: String(y), label: String(y) }))} />
          <Select aria-label="Employee" placeholder="Any employee" value={f.employee_id ?? ''} onChange={(e) => update({ filters: { employee_id: e.target.value || undefined } })} options={emp.options} />
          <Select aria-label="Leave type" placeholder="Any type" value={f.leave_type_id ?? ''} onChange={(e) => update({ filters: { leave_type_id: e.target.value || undefined } })} options={types.options} />
        </FilterBar>
        <Button variant="secondary" leftIcon={<RefreshCw size={16} />} onClick={() => setGenOpen(true)}>Generate balances for year</Button>
      </div>
      <DataTable<LeaveBalance>
        aria-label="Leave balances"
        columns={columns}
        data={query.data?.data ?? []}
        isLoading={query.isLoading || (query.isFetching && query.isPlaceholderData)}
        error={query.error}
        onRetry={() => void query.refetch()}
        sort={state.sort}
        onSortChange={(sort) => update({ sort })}
        pagination={meta ? { ...paginationFromMeta(meta), onPageChange: (page: number) => update({ page }, false), onPerPageChange: (perPage: number) => update({ perPage }) } : undefined}
        onRowClick={(r) => setEditing(r)}
        rowActions={(r) => <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(r)}>Adjust</MenuItem>}
        emptyState={<EmptyState icon={<CalendarDays size={22} />} title={`No balances for ${year}`} description="Generate balances to give every employee their yearly entitlement for each active leave type." action={<Button onClick={() => { setGenYear(Number(year)); setGenOpen(true); }}>Generate for {year}</Button>} />}
      />
      {editing && <AdjustDrawer key={`${editing.id}-${editing.updated_at ?? ''}`} open row={editing} onClose={() => setEditing(null)} />}
      <Modal open={genOpen} onClose={() => setGenOpen(false)} size="sm" title="Generate leave balances"
        description="Creates missing balance rows for every employed person and active leave type, applying carry-forward from the previous year. Existing rows are left unchanged."
        footer={<><Button variant="secondary" onClick={() => setGenOpen(false)}>Cancel</Button><Button onClick={() => genYear && generate.mutate(genYear)} loading={generate.isPending} disabled={!genYear || genYear < 2000 || genYear > 2100}>Generate</Button></>}>
        <FormField label="Year" required><NumberInput value={genYear} onValueChange={(v) => setGenYear(v)} min={2000} max={2100} step={1} precision={0} /></FormField>
      </Modal>
    </div>
  );
}

// ---- Leave types admin --------------------------------------------------------------------

export function LeaveTypesAdmin() {
  const q = leaveTypes.useList({ per_page: 100, sort: 'name' });
  const del = leaveTypes.useDelete();
  const [editing, setEditing] = useState<LeaveType | 'new' | null>(null);
  const [deleting, setDeleting] = useState<LeaveType | null>(null);
  const rows = q.data?.data ?? [];

  return (
    <Card>
      <CardHeader title="Leave types" description="Entitlements and rules applied when balances are generated." actions={<Button size="sm" leftIcon={<Plus size={14} />} onClick={() => setEditing('new')}>New leave type</Button>} />
      <CardBody padding="none">
        {q.error ? <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} /> : q.isLoading ? <div style={{ padding: 16 }}><SkeletonCard lines={3} /></div> : rows.length === 0 ? (
          <EmptyState size="sm" title="No leave types" description="Create types such as Annual, Sick and Unpaid leave." action={<Button size="sm" onClick={() => setEditing('new')}>Add a leave type</Button>} />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines}>
              <thead><tr><th>Name</th><th>Code</th><th className={crud.r}>Days / year</th><th>Rules</th><th>Status</th><th aria-label="Actions" /></tr></thead>
              <tbody>
                {rows.map((t) => (
                  <tr key={t.id}>
                    <td><span className={styles.row}><Swatch color={t.color} /><strong>{t.name}</strong></span></td>
                    <td className={crud.mono}>{t.code}</td>
                    <td className={crud.r}>{n1(t.days_per_year)}</td>
                    <td>
                      <span className={styles.row}>
                        <Badge size="sm" variant={t.is_paid ? 'success' : 'neutral'}>{t.is_paid ? 'Paid' : 'Unpaid'}</Badge>
                        {t.requires_approval ? <Badge size="sm" variant="info">Approval</Badge> : <Badge size="sm">Auto-approve</Badge>}
                        {t.carry_forward && <Badge size="sm" variant="primary">Carry {t.max_carry_forward_days ? `≤ ${n1(t.max_carry_forward_days)}` : 'all'}</Badge>}
                      </span>
                    </td>
                    <td><StatusBadge status={t.is_active ? 'active' : 'inactive'} /></td>
                    <td className={crud.r} style={{ whiteSpace: 'nowrap' }}>
                      <Button size="sm" variant="ghost" leftIcon={<Pencil size={14} />} onClick={() => setEditing(t)}>Edit</Button>
                      <Button size="sm" variant="ghost" leftIcon={<Trash2 size={14} />} onClick={() => setDeleting(t)} aria-label={`Delete ${t.name}`}>Delete</Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>
      <LeaveTypeForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} open={editing !== null} row={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name ?? 'leave type'}?`}
        description="Types already used by leave requests can’t be deleted — deactivate them instead."
        destructive
        confirmLabel="Delete"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </Card>
  );
}
