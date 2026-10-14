'use client';

import { use, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Ban, CheckCircle2, FileOutput, Pencil, Send, Trash2, XCircle } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, ConfirmDialog, DateInput, ErrorState, FormField, FormGrid, Modal, PageHeader, Select, SkeletonCard, Textarea, useToast,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { selectBranches, selectUser } from '@/store/sessionSlice';
import { departments } from '@/features/company/api';
import {
  REQUISITION_PRIORITIES, procurementApi, requisitions, suppliers, useWarehouses,
  type Requisition, type RequisitionInput, type RequisitionPriority, type SupplierOption,
} from '@/features/procurement/api';
import { LinesEditor, collectLines, editable, newLine, type DraftLine } from '@/features/procurement/LinesEditor';
import { DocStatus, PriorityBadge, ReasonDialog, Timeline, type TimelineStep } from '@/features/procurement/components';
import { NO_ERRORS, splitServerErrors, type SplitErrors } from '@/features/procurement/errors';
import { formatQty } from '@/features/procurement/decimal';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/procurement/procurement.module.scss';

function Editor({ req, onDone }: { req: Requisition | null; onDone?: () => void }) {
  const router = useRouter();
  const currency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const branches = useAppSelector(selectBranches);
  const deptList = departments.useList({ per_page: 100, sort: 'name' });
  const create = requisitions.useCreate({ onSuccess: (r) => router.replace(`/procurement/requisitions/${r.id}`) });
  const update = requisitions.useUpdate({ onSuccess: () => onDone?.() });
  const [departmentId, setDepartmentId] = useState(req?.department_id ? String(req.department_id) : '');
  const [branchId, setBranchId] = useState(req?.branch_id ? String(req.branch_id) : '');
  const [neededBy, setNeededBy] = useState(req?.needed_by ?? '');
  const [priority, setPriority] = useState<RequisitionPriority>(req?.priority ?? 'normal');
  const [justification, setJustification] = useState(req?.justification ?? '');
  const [lines, setLines] = useState<DraftLine[]>(
    req?.lines?.length
      ? req.lines.map((l) =>
          newLine({ product_id: l.product_id, description: l.description, quantity: editable(l.quantity), uom: l.uom ?? '', unit_price: l.estimated_unit_price ? editable(l.estimated_unit_price) : '' }),
        )
      : [newLine()],
  );
  const [errors, setErrors] = useState<SplitErrors>(NO_ERRORS);

  const save = async () => {
    setErrors(NO_ERRORS);
    const { rows, keys } = collectLines(lines);
    if (rows.length === 0) {
      setErrors({ ...NO_ERRORS, message: 'Add at least one line.' });
      return;
    }
    const body: RequisitionInput = {
      department_id: departmentId ? Number(departmentId) : null,
      branch_id: branchId ? Number(branchId) : null,
      needed_by: neededBy || null,
      priority,
      justification: justification.trim() || null,
      lines: rows.map((l) => ({ product_id: l.product_id, description: l.description.trim(), quantity: l.quantity, uom: l.uom.trim() || null, estimated_unit_price: l.unit_price || null })),
    };
    try {
      if (req) await update.mutateAsync({ id: req.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      setErrors(splitServerErrors(e as ApiError, keys));
    }
  };

  const h = errors.header;
  return (
    <Card>
      <CardHeader title={req ? `Edit ${req.number}` : 'New requisition'} description="Describe what is needed; prices are estimates for budgeting." />
      <CardBody>
        <div className={styles.stack}>
          {errors.message && <Alert variant="danger">{errors.message}</Alert>}
          <FormGrid columns={4}>
            <FormField label="Department" error={h.department_id}>
              <Select value={departmentId} placeholder="None" onChange={(e) => setDepartmentId(e.target.value)} options={[{ value: '', label: 'None' }, ...(deptList.data?.data ?? []).map((d) => ({ value: String(d.id), label: d.name }))]} />
            </FormField>
            <FormField label="Branch" error={h.branch_id}>
              <Select value={branchId} placeholder="None" onChange={(e) => setBranchId(e.target.value)} options={[{ value: '', label: 'None' }, ...branches.map((b) => ({ value: String(b.id), label: b.name }))]} />
            </FormField>
            <FormField label="Needed by" error={h.needed_by}>
              <DateInput value={neededBy} onValueChange={(v) => setNeededBy(v ?? '')} />
            </FormField>
            <FormField label="Priority" error={h.priority}>
              <Select value={priority} onChange={(e) => setPriority(e.target.value as RequisitionPriority)} options={REQUISITION_PRIORITIES} />
            </FormField>
            <FormField label="Justification" span="full" error={h.justification}>
              <Textarea rows={2} maxLength={2000} value={justification} onChange={(e) => setJustification(e.target.value)} />
            </FormField>
          </FormGrid>
          <LinesEditor mode="requisition" lines={lines} onChange={setLines} currency={currency} rowErrors={errors.rows} linesError={h.lines} />
          <div className={styles.row} style={{ justifyContent: 'flex-end' }}>
            <Button variant="secondary" onClick={() => (onDone ? onDone() : router.back())}>Cancel</Button>
            <Button onClick={save} loading={create.isPending || update.isPending}>Save draft</Button>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

function ConvertModal({ req, open, onClose }: { req: Requisition; open: boolean; onClose: () => void }) {
  const router = useRouter();
  const toast = useToast();
  const qc = useQueryClient();
  const supplierList = suppliers.useAll<SupplierOption>('all', {}, { enabled: open });
  const warehouses = useWarehouses();
  const [supplierId, setSupplierId] = useState('');
  const [expected, setExpected] = useState(req.needed_by ?? '');
  const [warehouseId, setWarehouseId] = useState('');
  const [address, setAddress] = useState('');
  const [errors, setErrors] = useState<SplitErrors>(NO_ERRORS);
  const convert = useMutation({
    mutationFn: () =>
      procurementApi.createOrderFromRequisition(req.id, {
        supplier_id: Number(supplierId),
        expected_date: expected || null,
        warehouse_id: warehouseId ? Number(warehouseId) : null,
        delivery_address: address.trim() || null,
      }),
    onSuccess: (po) => {
      void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes('requisitions') || q.queryKey.includes('purchase-orders') });
      toast.success(`Purchase order ${po.number} created`);
      router.push(`/procurement/orders/${po.id}`);
    },
    onError: (e: ApiError) => setErrors(splitServerErrors(e)),
  });
  const selected = supplierList.data?.find((s) => String(s.id) === supplierId);

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={`Create purchase order from ${req.number}`}
      description="All requisition lines are copied into a draft purchase order at their estimated prices."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button leftIcon={<FileOutput size={16} />} loading={convert.isPending} disabled={!supplierId} onClick={() => { setErrors(NO_ERRORS); convert.mutate(); }}>
            Create draft PO
          </Button>
        </>
      }
    >
      <div className={styles.stack}>
        {errors.message && <Alert variant="danger">{errors.message}</Alert>}
        <FormField label="Supplier" required error={errors.header.supplier_id}>
          <Select
            value={supplierId}
            placeholder={supplierList.isLoading ? 'Loading…' : 'Select supplier'}
            onChange={(e) => setSupplierId(e.target.value)}
            options={(supplierList.data ?? []).map((s) => ({ value: String(s.id), label: `${s.name} (${s.code})${s.is_approved ? '' : ' — not approved'}` }))}
          />
        </FormField>
        {selected && !selected.is_approved && <Alert variant="warning">This supplier has not been approved for purchasing yet.</Alert>}
        <FormGrid columns={2}>
          <FormField label="Expected delivery" error={errors.header.expected_date}>
            <DateInput value={expected} onValueChange={(v) => setExpected(v ?? '')} />
          </FormField>
          <FormField label="Receiving warehouse" error={errors.header.warehouse_id}>
            <Select value={warehouseId} placeholder="Not set" onChange={(e) => setWarehouseId(e.target.value)} options={[{ value: '', label: 'Not set' }, ...(warehouses.data ?? []).map((w) => ({ value: String(w.id), label: `${w.name} (${w.code})` }))]} />
          </FormField>
        </FormGrid>
        <FormField label="Delivery address" error={errors.header.delivery_address}>
          <Textarea rows={2} maxLength={500} value={address} onChange={(e) => setAddress(e.target.value)} />
        </FormField>
      </div>
    </Modal>
  );
}

export default function RequisitionPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const can = useCan();
  const me = useAppSelector(selectUser);
  const currency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const isNew = id === 'new';
  const q = requisitions.useOne(isNew ? undefined : id);
  const submit = requisitions.useAction('submit', { success: 'Requisition submitted for approval' });
  const approve = requisitions.useAction('approve', { success: 'Requisition approved' });
  const reject = requisitions.useAction<{ reason: string }>('reject', { success: 'Requisition rejected' });
  const cancel = requisitions.useAction('cancel', { success: 'Requisition cancelled' });
  const del = requisitions.useDelete({ onSuccess: () => router.replace('/procurement/requisitions') });
  const [editing, setEditing] = useState(false);
  const [confirm, setConfirm] = useState<'submit' | 'approve' | 'cancel' | 'delete' | null>(null);
  const [rejecting, setRejecting] = useState(false);
  const [converting, setConverting] = useState(false);

  if (isNew) {
    if (!can('requisitions.create')) return <Alert variant="warning">You don’t have permission to raise requisitions.</Alert>;
    return (
      <div className={styles.stack}>
        <PageHeader title="New requisition" backHref="/procurement/requisitions" backLabel="Requisitions" />
        <Editor req={null} />
      </div>
    );
  }
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const r = q.data;
  const isRequester = r.requester_id === me?.id;
  const canEdit = isRequester && can('requisitions.create');

  if (editing && r.status === 'draft' && canEdit) {
    return (
      <div className={styles.stack}>
        <PageHeader title={`Edit ${r.number}`} backHref="/procurement/requisitions" backLabel="Requisitions" />
        <Editor req={r} onDone={() => setEditing(false)} />
      </div>
    );
  }

  const decided = r.status === 'rejected' ? 'bad' : r.approved_at ? 'done' : 'pending';
  const steps: TimelineStep[] = [
    { label: 'Created', at: r.created_at, detail: r.requester?.name, state: 'done' },
    { label: 'Submitted', at: r.submitted_at, state: r.submitted_at ? 'done' : 'pending' },
    r.status === 'rejected'
      ? { label: 'Rejected', at: r.rejected_at, detail: r.approver?.name, state: 'bad' }
      : { label: 'Approved', at: r.approved_at, detail: r.approver?.name, state: decided },
    { label: 'Ordered', state: r.status === 'ordered' ? 'done' : 'pending', detail: r.purchase_orders?.map((p) => p.number).join(', ') || undefined },
  ];
  if (r.status === 'cancelled') steps.push({ label: 'Cancelled', state: 'bad' });

  const run = async (fn: () => Promise<unknown>) => {
    try {
      await fn();
    } finally {
      setConfirm(null);
    }
  };

  return (
    <div className={styles.stack}>
      <PageHeader
        title={r.number}
        description={r.justification ?? undefined}
        backHref="/procurement/requisitions"
        backLabel="Requisitions"
        meta={<><DocStatus status={r.status} /><PriorityBadge priority={r.priority} /></>}
        actions={
          <>
            {r.status === 'draft' && canEdit && <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirm('delete')}>Delete</Button>}
            {(r.status === 'draft' || r.status === 'submitted') && canEdit && <Button variant="ghost" leftIcon={<Ban size={16} />} onClick={() => setConfirm('cancel')}>Cancel</Button>}
            {r.status === 'draft' && canEdit && <Button variant="secondary" leftIcon={<Pencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>}
            {r.status === 'draft' && canEdit && <Button leftIcon={<Send size={16} />} onClick={() => setConfirm('submit')}>Submit</Button>}
            {r.status === 'submitted' && !isRequester && can('requisitions.approve') && (
              <>
                <Button variant="secondary" leftIcon={<XCircle size={16} />} onClick={() => setRejecting(true)}>Reject</Button>
                <Button leftIcon={<CheckCircle2 size={16} />} onClick={() => setConfirm('approve')}>Approve</Button>
              </>
            )}
            {r.status === 'approved' && can('purchase_orders.manage') && (
              <Button leftIcon={<FileOutput size={16} />} onClick={() => setConverting(true)}>Create purchase order</Button>
            )}
          </>
        }
      />

      {r.status === 'rejected' && r.rejection_reason && <Alert variant="danger" title="Rejected">{r.rejection_reason}</Alert>}
      {r.status === 'submitted' && isRequester && can('requisitions.approve') && (
        <Alert variant="info">You raised this requisition, so another approver must review it.</Alert>
      )}
      {!!r.purchase_orders?.length && (
        <Alert variant="success" title="Purchase orders">
          {r.purchase_orders.map((p, i) => (
            <span key={p.id}>
              {i > 0 && ', '}
              <Link href={`/procurement/orders/${p.id}`}>{p.number}</Link> (<DocStatus status={p.status} />)
            </span>
          ))}
        </Alert>
      )}

      <div className={crud.detailGrid}>
        <Card>
          <CardHeader title="Lines" />
          <CardBody padding="none">
            <div className={crud.tableScroll}>
              <table className={crud.lines} style={{ minWidth: 560 }}>
                <thead>
                  <tr><th>#</th><th>Description</th><th className={crud.r}>Qty</th><th>UoM</th><th className={crud.r}>Est. price</th><th className={crud.r}>Total</th></tr>
                </thead>
                <tbody>
                  {r.lines?.map((l) => (
                    <tr key={l.id}>
                      <td className={crud.muted}>{l.line_no}</td>
                      <td>{l.description}{l.product_id && <span className={styles.linked}>Catalogue item #{l.product_id}</span>}</td>
                      <td className={crud.r}>{formatQty(l.quantity)}</td>
                      <td>{l.uom ?? ''}</td>
                      <td className={crud.r}>{l.estimated_unit_price ? formatMoney(l.estimated_unit_price, currency) : '—'}</td>
                      <td className={crud.r}>{formatMoney(l.line_total, currency)}</td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr><td colSpan={5}>Estimated total</td><td className={crud.r}>{formatMoney(r.estimated_total, currency)}</td></tr>
                </tfoot>
              </table>
            </div>
          </CardBody>
        </Card>
        <div className={styles.stack}>
          <Card>
            <CardHeader title="Details" />
            <CardBody>
              <dl className={crud.dl}>
                <dt>Requested by</dt><dd>{r.requester?.name ?? '—'}</dd>
                <dt>Department</dt><dd>{r.department?.name ?? '—'}</dd>
                <dt>Branch</dt><dd>{r.branch?.name ?? '—'}</dd>
                <dt>Needed by</dt><dd>{formatDate(r.needed_by)}</dd>
                <dt>Created</dt><dd>{formatDateTime(r.created_at)}</dd>
              </dl>
            </CardBody>
          </Card>
          <Card>
            <CardHeader title="Progress" />
            <CardBody><Timeline steps={steps} /></CardBody>
          </Card>
        </div>
      </div>

      <ConfirmDialog open={confirm === 'submit'} onClose={() => setConfirm(null)} title={`Submit ${r.number}?`} description="Approvers will be notified. You can no longer edit the lines once submitted." confirmLabel="Submit for approval" onConfirm={() => run(() => submit.mutateAsync({ id: r.id }))} />
      <ConfirmDialog open={confirm === 'approve'} onClose={() => setConfirm(null)} title={`Approve ${r.number}?`} description={`Estimated value ${formatMoney(r.estimated_total, currency)}. Purchasing can then raise a purchase order.`} confirmLabel="Approve" onConfirm={() => run(() => approve.mutateAsync({ id: r.id }))} />
      <ConfirmDialog open={confirm === 'cancel'} onClose={() => setConfirm(null)} title={`Cancel ${r.number}?`} description="The requisition will be withdrawn and can’t be reopened." destructive confirmLabel="Cancel requisition" cancelLabel="Keep" onConfirm={() => run(() => cancel.mutateAsync({ id: r.id }))} />
      <ConfirmDialog open={confirm === 'delete'} onClose={() => setConfirm(null)} title={`Delete draft ${r.number}?`} destructive confirmLabel="Delete draft" onConfirm={() => run(() => del.mutateAsync(r.id))} />
      {rejecting && (
        <ReasonDialog
          open
          onClose={() => setRejecting(false)}
          title={`Reject ${r.number}?`}
          description="The requester will see this reason."
          confirmLabel="Reject requisition"
          onSubmit={(reason) => reject.mutateAsync({ id: r.id, body: { reason } })}
        />
      )}
      {converting && <ConvertModal req={r} open onClose={() => setConverting(false)} />}
    </div>
  );
}
