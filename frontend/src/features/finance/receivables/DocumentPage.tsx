'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { BookOpen, CheckCircle2, Download, Pencil, Send, Trash2, Wallet, XCircle } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, ConfirmDialog, EmptyState, ErrorState, PageHeader, SkeletonCard, StatusBadge, useToast,
} from '@/components/ui';
import { get } from '@/lib/api/client';
import type { ApiError } from '@/lib/api/errors';
import type { Paginated, Single } from '@/lib/api/types';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime, formatMoney, humanize } from '@/lib/format';
import { toScaled } from '@/features/inventory/decimal';
import { downloadInvoicePdf, isEditableStatus, isOpenStatus, useCompanyId, type Payment } from './api';
import { DOC_CONFIG, docDate, docPartner, docPartnerId, docResource, isInvoice, type AnyDoc, type DocKind } from './documents';
import { DocumentEditor } from './DocumentEditor';
import { DocumentTotals } from './DocumentLinesEditor';
import { RecordPaymentDrawer } from './RecordPaymentDrawer';
import { DocumentStatusBadge, ReasonDialog, hasPaymentsExplanation } from './shared';
import crud from '@/components/crud/crud.module.scss';
import styles from './receivables.module.scss';

/**
 * Payments allocated to one document. The API has no "payments for document"
 * filter, so we list the partner's payments and read each one's allocations.
 */
function useDocumentPayments(kind: DocKind, doc: AnyDoc, enabled: boolean) {
  const companyId = useCompanyId();
  const cfg = DOC_CONFIG[kind];
  return useQuery({
    queryKey: [companyId, 'payments', 'for-document', kind, doc.id, doc.amount_paid],
    enabled,
    queryFn: async () => {
      const list = await get<Paginated<Payment>>('/payments', { per_page: 50, sort: '-payment_date', 'filter[partner_type]': cfg.partnerType, 'filter[partner_id]': docPartnerId(doc) });
      const full = await Promise.all(list.data.map((p) => get<Single<Payment>>(`/payments/${p.id}`).then((r) => r.data)));
      return full
        .map((p) => ({ payment: p, allocation: p.allocations?.find((a) => a.type === cfg.allocationType && a.document_id === doc.id) }))
        .filter((x) => !!x.allocation);
    },
  });
}

function PaymentsCard({ kind, doc }: { kind: DocKind; doc: AnyDoc }) {
  const can = useCan();
  const paid = toScaled(doc.amount_paid) > 0n;
  const q = useDocumentPayments(kind, doc, paid && can('payments.view'));
  const listPath = kind === 'invoice' ? '/finance/receipts' : '/finance/payments';
  return (
    <Card>
      <CardHeader title={kind === 'invoice' ? 'Receipts' : 'Payments'} description={`${formatMoney(doc.amount_paid, doc.currency)} paid · ${formatMoney(doc.balance_due, doc.currency)} due`} />
      <CardBody padding={paid ? 'none' : 'md'}>
        {!paid ? (
          <EmptyState size="sm" title="No payments yet" />
        ) : !can('payments.view') ? (
          <div className={styles.hint} style={{ padding: 16 }}>You don’t have permission to view payments.</div>
        ) : q.isLoading ? (
          <div style={{ padding: 16 }}><SkeletonCard /></div>
        ) : q.error ? (
          <div style={{ padding: 16 }}><ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} /></div>
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines}>
              <thead><tr><th>Number</th><th>Date</th><th>Method</th><th className={crud.r}>Allocated</th></tr></thead>
              <tbody>
                {(q.data ?? []).map(({ payment: p, allocation: a }) => (
                  <tr key={p.id}>
                    <td><a className={styles.linkBtn} href={`${listPath}?search=${encodeURIComponent(p.number)}`}>{p.number}</a> {p.status === 'void' && <StatusBadge status="void" size="sm" />}</td>
                    <td>{formatDate(p.payment_date)}</td>
                    <td>{humanize(p.method)}</td>
                    <td className={crud.r}>{formatMoney(a!.amount, doc.currency)}</td>
                  </tr>
                ))}
                {(q.data ?? []).length === 0 && <tr><td colSpan={4} className={styles.hint}>Allocations not found among the partner’s 50 most recent payments.</td></tr>}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>
    </Card>
  );
}

type Confirm = 'submit' | 'approve' | 'delete' | 'void' | null;

export function DocumentPage({ kind, id }: { kind: DocKind; id: string }) {
  const cfg = DOC_CONFIG[kind];
  const router = useRouter();
  const can = useCan();
  const toast = useToast();
  const res = docResource(kind);
  const isNew = id === 'new';
  const q = res.useOne(isNew ? undefined : id);
  const submit = res.useAction('submit', { success: `${cfg.label} submitted for approval` });
  const approve = res.useAction('approve', { success: `${cfg.label} approved and posted` });
  const voidDoc = res.useAction<{ reason: string }>('void', { success: `${cfg.label} voided` });
  const del = res.useDelete({ onSuccess: () => router.replace(cfg.path) });
  const [confirm, setConfirm] = useState<Confirm>(null);
  const [editing, setEditing] = useState(false);
  const [paying, setPaying] = useState(0);
  const [pdfBusy, setPdfBusy] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  if (isNew) {
    if (!can(cfg.perm.create)) return <Alert variant="warning">You don’t have permission to create {cfg.plural.toLowerCase()}.</Alert>;
    return (
      <div className={styles.stack}>
        <PageHeader title={`New ${cfg.label.toLowerCase()}`} backHref={cfg.path} backLabel={cfg.plural} />
        <DocumentEditor kind={kind} doc={null} />
      </div>
    );
  }
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const d = q.data as AnyDoc;
  const editable = isEditableStatus(d.status);

  if (editing && editable) {
    return (
      <div className={styles.stack}>
        <PageHeader title={`Edit ${d.number}`} backHref={cfg.path} backLabel={cfg.plural} />
        <DocumentEditor kind={kind} doc={d} onDone={() => setEditing(false)} />
      </div>
    );
  }

  const run = async (fn: () => Promise<unknown>) => {
    setActionError(null);
    try {
      await fn();
      setConfirm(null);
    } catch (e) {
      setActionError((e as ApiError).message);
      setConfirm(null);
    }
  };

  const pdf = async () => {
    setPdfBusy(true);
    try {
      await downloadInvoicePdf(d);
    } catch (e) {
      toast.error('Could not download PDF', (e as ApiError).message);
    } finally {
      setPdfBusy(false);
    }
  };

  const partner = docPartner(d);
  const canPay = isOpenStatus(d.status) && toScaled(d.balance_due) > 0n && can('payments.create');

  return (
    <div className={styles.stack}>
      <PageHeader
        title={d.number}
        description={partner?.name ?? undefined}
        backHref={cfg.path}
        backLabel={cfg.plural}
        meta={<DocumentStatusBadge status={d.status} overdue={d.is_overdue} />}
        actions={
          <>
            {editable && can(cfg.perm.update) && <Button variant="secondary" leftIcon={<Pencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>}
            {editable && can(cfg.perm.delete) && <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirm('delete')}>Delete</Button>}
            {kind === 'invoice' && <Button variant="secondary" leftIcon={<Download size={16} />} loading={pdfBusy} onClick={pdf}>PDF</Button>}
            {d.status === 'draft' && (can(cfg.perm.create) || can(cfg.perm.update)) && <Button variant="secondary" leftIcon={<Send size={16} />} onClick={() => setConfirm('submit')}>Submit</Button>}
            {editable && can(cfg.perm.approve) && <Button leftIcon={<CheckCircle2 size={16} />} onClick={() => setConfirm('approve')}>Approve</Button>}
            {canPay && <Button leftIcon={<Wallet size={16} />} onClick={() => setPaying((n) => n + 1)}>Record {cfg.paymentType}</Button>}
            {d.status !== 'void' && can(cfg.perm.approve) && <Button variant="ghost" leftIcon={<XCircle size={16} />} onClick={() => setConfirm('void')}>Void</Button>}
          </>
        }
      />

      {actionError && <Alert variant="danger" dismissible onDismiss={() => setActionError(null)}>{actionError}</Alert>}
      {d.status === 'void' && <Alert variant="warning" title="Void">This {cfg.label.toLowerCase()} was voided {d.voided_at ? `on ${formatDateTime(d.voided_at)}` : ''}{d.void_reason ? ` — ${d.void_reason}` : ''}. Any posting was reversed.</Alert>}
      {d.is_overdue && d.status !== 'void' && <Alert variant="danger">Overdue since {formatDate(d.due_date)} — {formatMoney(d.balance_due, d.currency)} outstanding.</Alert>}

      <div className={crud.detailGrid}>
        <Card>
          <CardHeader title="Lines" />
          <CardBody padding="none">
            <div className={crud.tableScroll}>
              <table className={crud.lines} style={{ minWidth: 720 }}>
                <thead>
                  <tr><th>Description</th><th>Account</th><th className={crud.r}>Qty</th><th className={crud.r}>Unit price</th><th className={crud.r}>Disc</th><th className={crud.r}>Tax</th><th className={crud.r}>Total</th></tr>
                </thead>
                <tbody>
                  {d.lines?.map((l) => (
                    <tr key={l.id}>
                      <td>{l.description}</td>
                      <td>{l.account ? <><span className={crud.mono}>{l.account.code}</span> {l.account.name}</> : '—'}</td>
                      <td className={crud.r}>{Number(l.quantity).toLocaleString()}</td>
                      <td className={crud.r}>{formatMoney(l.unit_price, d.currency)}</td>
                      <td className={crud.r}>{Number(l.discount_percent) ? `${Number(l.discount_percent)}%` : '—'}</td>
                      <td className={crud.r}>{Number(l.tax_amount) ? <>{formatMoney(l.tax_amount, d.currency)}<div className={styles.hint}>{Number(l.tax_rate)}%</div></> : '—'}</td>
                      <td className={crud.r}>{formatMoney(l.line_total, d.currency)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div style={{ padding: 16 }}>
              <DocumentTotals currency={d.currency} subtotal={d.subtotal} discount={d.discount_total} tax={d.tax_total} total={d.total} paid={d.amount_paid} balance={d.balance_due} />
            </div>
          </CardBody>
        </Card>
        <div className={styles.stack}>
          <Card>
            <CardHeader title="Details" />
            <CardBody>
              <dl className={crud.dl}>
                <dt>{cfg.partnerLabel}</dt><dd>{partner?.name ?? `#${docPartnerId(d)}`}</dd>
                {!isInvoice(d) && <><dt>Supplier ref.</dt><dd>{d.supplier_reference}</dd></>}
                <dt>{cfg.dateLabel}</dt><dd>{formatDate(docDate(d))}</dd>
                <dt>Due date</dt><dd className={d.is_overdue ? styles.danger : undefined}>{formatDate(d.due_date)}</dd>
                <dt>Currency</dt><dd>{d.currency}</dd>
                <dt>Branch</dt><dd>{d.branch?.name ?? '—'}</dd>
                <dt>Status</dt><dd>{humanize(d.status)}</dd>
                {isInvoice(d) && <><dt>Created by</dt><dd>{d.creator?.name ?? '—'}</dd></>}
                <dt>Approved</dt><dd>{d.approved_at ? `${formatDateTime(d.approved_at)}${d.approver ? ` by ${d.approver.name}` : ''}` : '—'}</dd>
                <dt>Journal</dt>
                <dd>{d.journal_entry_id ? <a className={styles.linkBtn} href={`/finance/journals/${d.journal_entry_id}`}><BookOpen size={12} style={{ display: 'inline', verticalAlign: '-1px' }} /> View journal entry</a> : 'Not posted'}</dd>
              </dl>
              {(d.notes || (isInvoice(d) && d.terms)) && (
                <dl className={crud.dl} style={{ marginTop: 16 }}>
                  {d.notes && <><dt>Notes</dt><dd style={{ whiteSpace: 'pre-wrap' }}>{d.notes}</dd></>}
                  {isInvoice(d) && d.terms && <><dt>Terms</dt><dd style={{ whiteSpace: 'pre-wrap' }}>{d.terms}</dd></>}
                </dl>
              )}
            </CardBody>
          </Card>
          {d.status !== 'draft' && d.status !== 'pending_approval' && <PaymentsCard kind={kind} doc={d} />}
        </div>
      </div>

      <ConfirmDialog
        open={confirm === 'submit'}
        onClose={() => setConfirm(null)}
        title={`Submit ${d.number} for approval?`}
        description="It stays editable until an approver posts it."
        confirmLabel="Submit"
        onConfirm={() => run(() => submit.mutateAsync({ id: d.id }))}
      />
      <ConfirmDialog
        open={confirm === 'approve'}
        onClose={() => setConfirm(null)}
        title={`Approve ${d.number}?`}
        description={`Approving posts ${formatMoney(d.total, d.currency)} to the general ledger (${kind === 'invoice' ? 'Dr receivables / Cr revenue & tax' : 'Dr expense & tax / Cr payables'}). It can then only be voided, not edited.`}
        confirmLabel="Approve & post"
        onConfirm={() => run(() => approve.mutateAsync({ id: d.id }))}
      />
      <ConfirmDialog
        open={confirm === 'delete'}
        onClose={() => setConfirm(null)}
        title={`Delete ${d.number}?`}
        destructive
        confirmLabel="Delete"
        onConfirm={() => run(() => del.mutateAsync(d.id))}
      />
      <ReasonDialog
        key={confirm === 'void' ? 'void-open' : 'void-closed'}
        open={confirm === 'void'}
        onClose={() => setConfirm(null)}
        title={`Void ${d.number}?`}
        description={d.journal_entry_id ? 'A reversing journal entry dated today will be posted. This cannot be undone.' : 'The document will be marked void. This cannot be undone.'}
        confirmLabel={`Void ${cfg.label.toLowerCase()}`}
        explain={hasPaymentsExplanation(kind)}
        onSubmit={(reason) => voidDoc.mutateAsync({ id: d.id, body: { reason } })}
      />
      {paying > 0 && (
        <RecordPaymentDrawer
          key={paying}
          open
          onClose={() => setPaying(0)}
          type={cfg.paymentType}
          prefill={{ partnerType: cfg.partnerType, partnerId: docPartnerId(d), allocation: { type: cfg.allocationType, id: d.id, amount: d.balance_due } }}
        />
      )}
    </div>
  );
}
