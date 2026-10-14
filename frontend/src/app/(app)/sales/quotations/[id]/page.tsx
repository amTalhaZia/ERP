'use client';

import { Suspense, use, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { ArrowRightLeft, Check, Pencil, Printer, Send, Trash2, X } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, ConfirmDialog, DateInput, ErrorState, FormField, FormGrid, Modal, PageHeader, Select, SkeletonCard, Textarea,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { selectCompany } from '@/store/sessionSlice';
import { customers, quotations, type Quotation, type SalesOrder } from '@/features/sales/api';
import { blankLine, DocumentLinesTable, linesFromDocument, linesToPayload, remapLineErrors, SalesLinesEditor, TotalsBlock, type DraftLine } from '@/features/sales/SalesLinesEditor';
import { flattenErrors, nullable, SalesStatus, useCustomerOptions, useUserOptions } from '@/features/sales/shared';
import crud from '@/components/crud/crud.module.scss';
import styles from './document.module.scss';

function addDays(iso: string, days: number) {
  const d = new Date(`${iso}T00:00:00`);
  d.setDate(d.getDate() + days);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
const todayIso = () => addDays(new Date().toISOString().slice(0, 10), 0);

function QuotationEditor({ quotation, initialCustomerId, onDone }: { quotation: Quotation | null; initialCustomerId?: string; onDone: () => void }) {
  const router = useRouter();
  const companyCurrency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const [customerId, setCustomerId] = useState(quotation ? String(quotation.customer_id) : (initialCustomerId ?? ''));
  const [date, setDate] = useState(quotation?.date ?? todayIso());
  const [validUntil, setValidUntil] = useState<string | null>(quotation ? quotation.valid_until : addDays(todayIso(), 30));
  const [salesRepId, setSalesRepId] = useState(quotation?.sales_rep_id ? String(quotation.sales_rep_id) : '');
  const [notes, setNotes] = useState(quotation?.notes ?? '');
  const [terms, setTerms] = useState(quotation?.terms ?? '');
  const [lines, setLines] = useState<DraftLine[]>(quotation ? linesFromDocument(quotation.lines) : [blankLine()]);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const customerOpts = useCustomerOptions(quotation?.customer ?? null);
  const reps = useUserOptions([quotation?.sales_rep]);
  const customer = customers.useOne(customerId || undefined);
  const currency = quotation?.currency ?? customer.data?.currency ?? companyCurrency;
  const create = quotations.useCreate({ onSuccess: (q) => router.replace(`/sales/quotations/${q.id}`) });
  const update = quotations.useUpdate({ onSuccess: onDone });

  const save = async () => {
    setError(null);
    setFieldErrors({});
    const { payload, indexMap } = linesToPayload(lines);
    if (!customerId) {
      setFieldErrors({ customer_id: 'Choose a customer' });
      return;
    }
    if (payload.length === 0) {
      setFieldErrors({ lines: 'Add at least one line' });
      return;
    }
    const body = {
      customer_id: Number(customerId),
      date,
      valid_until: validUntil || null,
      notes: nullable(notes),
      terms: nullable(terms),
      ...(salesRepId || quotation ? { sales_rep_id: salesRepId ? Number(salesRepId) : null } : {}),
      lines: payload,
    };
    try {
      if (quotation) await update.mutateAsync({ id: quotation.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      const { fields, message } = flattenErrors(e as ApiError);
      setFieldErrors(remapLineErrors(fields, indexMap));
      setError(message);
    }
  };

  return (
    <Card>
      <CardHeader title={quotation ? `Edit ${quotation.number}` : 'New quotation'} description="Prices are snapshotted per line; totals are recalculated by the server." />
      <CardBody>
        {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
        <FormGrid columns={4}>
          <FormField label="Customer" required span={2} error={fieldErrors.customer_id}>
            <Select value={customerId} onChange={(e) => setCustomerId(e.target.value)} placeholder={customerOpts.isLoading ? 'Loading…' : 'Choose a customer'} options={customerOpts.options} disabled={!!quotation} />
          </FormField>
          <FormField label="Date" required error={fieldErrors.date}><DateInput value={date} onValueChange={(v) => setDate(v ?? todayIso())} /></FormField>
          <FormField label="Valid until" error={fieldErrors.valid_until}><DateInput value={validUntil} min={date} onValueChange={setValidUntil} /></FormField>
          <FormField label="Sales rep" span={2} error={fieldErrors.sales_rep_id} hint={!quotation && !salesRepId ? 'Left blank, the customer’s rep applies.' : undefined}>
            <Select value={salesRepId} onChange={(e) => setSalesRepId(e.target.value)} placeholder="Unassigned" options={reps.options} />
          </FormField>
          <FormField label="Currency" span={2} hint="Taken from the customer.">
            <span style={{ display: 'inline-block', paddingTop: 8 }} className={crud.mono}>{currency}</span>
          </FormField>
        </FormGrid>

        <div style={{ marginTop: 20 }}>
          <SalesLinesEditor lines={lines} onChange={setLines} currency={currency} errors={fieldErrors} />
        </div>

        <FormGrid columns={2} style={{ marginTop: 20 }}>
          <FormField label="Notes" hint="Shown to the customer" error={fieldErrors.notes}><Textarea rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={5000} /></FormField>
          <FormField label="Terms & conditions" error={fieldErrors.terms}><Textarea rows={3} value={terms} onChange={(e) => setTerms(e.target.value)} maxLength={5000} /></FormField>
        </FormGrid>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 16 }}>
          <Button variant="secondary" onClick={() => (quotation ? onDone() : router.back())}>Cancel</Button>
          <Button onClick={save} loading={create.isPending || update.isPending}>{quotation ? 'Save changes' : 'Save draft'}</Button>
        </div>
      </CardBody>
    </Card>
  );
}

function QuotationDetail({ q }: { q: Quotation }) {
  const router = useRouter();
  const can = useCan();
  const company = useAppSelector(selectCompany);
  const canManage = can('quotations.manage');
  const [editing, setEditing] = useState(false);
  const [confirm, setConfirm] = useState<'send' | 'accept' | 'convert' | 'delete' | null>(null);
  const [rejecting, setRejecting] = useState(false);
  const [reason, setReason] = useState('');
  const send = quotations.useAction('send', { success: 'Quotation marked as sent' });
  const accept = quotations.useAction('accept', { success: 'Quotation accepted' });
  const reject = quotations.useAction<{ reason: string | null }>('reject', { success: 'Quotation rejected' });
  const convert = quotations.useAction<Record<string, never>, SalesOrder>('convert', {
    success: 'Sales order created',
    onSuccess: (o) => router.push(`/sales/orders/${o.id}`),
  });
  const del = quotations.useDelete({ onSuccess: () => router.replace('/sales/quotations') });

  if (editing && q.status === 'draft') {
    return (
      <div className={styles.printRoot}>
        <PageHeader title={`Edit ${q.number}`} backHref="/sales/quotations" backLabel="Quotations" />
        <QuotationEditor quotation={q} onDone={() => setEditing(false)} />
      </div>
    );
  }

  const run = async (fn: () => Promise<unknown>) => {
    try {
      await fn();
    } catch {
      // toast already shown by the resource hook
    }
    setConfirm(null);
  };
  const canConvert = can('sales_orders.manage') && (q.status === 'sent' || q.status === 'accepted');

  return (
    <div className={styles.printRoot}>
      <div className={styles.noPrint}>
        <PageHeader
          title={q.number}
          description={q.customer ? <Link href={`/sales/customers/${q.customer_id}`}>{q.customer.name}</Link> : undefined}
          backHref="/sales/quotations"
          backLabel="Quotations"
          meta={<SalesStatus status={q.status} />}
          actions={
            <>
              <Button variant="ghost" leftIcon={<Printer size={16} />} onClick={() => window.print()}>Print</Button>
              {canManage && q.status === 'draft' && (
                <>
                  <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirm('delete')}>Delete</Button>
                  <Button variant="secondary" leftIcon={<Pencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>
                  <Button leftIcon={<Send size={16} />} onClick={() => setConfirm('send')} disabled={!q.lines?.length}>Send</Button>
                </>
              )}
              {canManage && q.status === 'sent' && (
                <>
                  <Button variant="secondary" leftIcon={<X size={16} />} onClick={() => { setReason(''); setRejecting(true); }}>Reject</Button>
                  <Button variant="secondary" leftIcon={<Check size={16} />} onClick={() => setConfirm('accept')}>Accept</Button>
                </>
              )}
              {canManage && canConvert && <Button leftIcon={<ArrowRightLeft size={16} />} onClick={() => setConfirm('convert')}>Convert to order</Button>}
            </>
          }
        />
      </div>

      <div className={styles.noPrint} style={{ display: 'contents' }}>
        {q.status !== 'draft' && <Alert variant="info">This quotation is {q.status} and can no longer be edited.</Alert>}
        {q.status === 'expired' && <Alert variant="warning">The validity date has passed. Create a new quotation to re-offer these prices.</Alert>}
        {q.status === 'rejected' && q.rejection_reason && <Alert variant="danger" title="Rejection reason">{q.rejection_reason}</Alert>}
        {q.converted_sales_order_id && (
          <Alert variant="success">Converted to a sales order. <Link href={`/sales/orders/${q.converted_sales_order_id}`}>Open the order →</Link></Alert>
        )}
      </div>

      {/* Print-only letterhead */}
      <div className={styles.printOnly}>
        <div className={styles.printHeader}>
          <div>
            <h1>Quotation</h1>
            <p><strong>{company?.name}</strong></p>
          </div>
          <div className={styles.printMeta}>
            <p><strong>{q.number}</strong></p>
            <p>Date: {formatDate(q.date)}</p>
            {q.valid_until && <p>Valid until: {formatDate(q.valid_until)}</p>}
            {q.sales_rep && <p>Sales rep: {q.sales_rep.name}</p>}
          </div>
        </div>
        <div className={styles.printParty}>
          <h2>Prepared for</h2>
          <p><strong>{q.customer?.name}</strong> ({q.customer?.code})</p>
        </div>
      </div>

      <div className={crud.detailGrid}>
        <Card>
          <CardHeader title="Lines" />
          <CardBody padding="none">
            <DocumentLinesTable lines={q.lines ?? []} currency={q.currency} />
            <div style={{ padding: 16 }}>
              <TotalsBlock currency={q.currency} subtotal={q.subtotal} discount={q.discount_total} tax={q.tax_total} total={q.total} />
            </div>
          </CardBody>
        </Card>
        <div className={styles.noPrint}>
          <Card>
            <CardHeader title="Details" />
            <CardBody>
              <dl className={crud.dl}>
                <dt>Customer</dt><dd>{q.customer ? <Link href={`/sales/customers/${q.customer_id}`}>{q.customer.name}</Link> : '—'}</dd>
                <dt>Date</dt><dd>{formatDate(q.date)}</dd>
                <dt>Valid until</dt><dd>{formatDate(q.valid_until)}</dd>
                <dt>Currency</dt><dd>{q.currency}</dd>
                <dt>Sales rep</dt><dd>{q.sales_rep?.name ?? '—'}</dd>
                <dt>Sent</dt><dd>{q.sent_at ? formatDateTime(q.sent_at) : '—'}</dd>
                {q.accepted_at && (<><dt>Accepted</dt><dd>{formatDateTime(q.accepted_at)}</dd></>)}
                {q.rejected_at && (<><dt>Rejected</dt><dd>{formatDateTime(q.rejected_at)}</dd></>)}
              </dl>
            </CardBody>
          </Card>
        </div>
      </div>

      {(q.notes || q.terms) && (
        <Card>
          <CardBody>
            <div className={crud.detailGrid}>
              {q.notes && <div className={styles.printNotes} style={{ marginTop: 0 }}><h2>Notes</h2>{q.notes}</div>}
              {q.terms && <div className={styles.printNotes} style={{ marginTop: 0 }}><h2>Terms & conditions</h2>{q.terms}</div>}
            </div>
          </CardBody>
        </Card>
      )}

      <ConfirmDialog
        open={confirm === 'send'}
        onClose={() => setConfirm(null)}
        title={`Mark ${q.number} as sent?`}
        description="The quotation becomes read-only. Email delivery is not automated — share the printed copy or PDF with the customer."
        confirmLabel="Mark as sent"
        onConfirm={() => run(() => send.mutateAsync({ id: q.id }))}
      />
      <ConfirmDialog
        open={confirm === 'accept'}
        onClose={() => setConfirm(null)}
        title={`Record acceptance of ${q.number}?`}
        description="Use this when the customer has accepted the offer. You can then convert it into a sales order."
        confirmLabel="Accept quotation"
        onConfirm={() => run(() => accept.mutateAsync({ id: q.id }))}
      />
      <ConfirmDialog
        open={confirm === 'convert'}
        onClose={() => setConfirm(null)}
        title={`Convert ${q.number} into a sales order?`}
        description="A draft sales order is created with the same customer, lines and prices. The quotation is marked as converted."
        confirmLabel="Create sales order"
        onConfirm={() => run(() => convert.mutateAsync({ id: q.id }))}
      />
      <ConfirmDialog
        open={confirm === 'delete'}
        onClose={() => setConfirm(null)}
        title={`Delete draft ${q.number}?`}
        destructive
        confirmLabel="Delete draft"
        onConfirm={() => run(() => del.mutateAsync(q.id))}
      />
      <Modal
        open={rejecting}
        onClose={() => setRejecting(false)}
        size="sm"
        title={`Reject ${q.number}?`}
        description="Records that the customer declined this offer."
        footer={
          <>
            <Button variant="secondary" onClick={() => setRejecting(false)}>Cancel</Button>
            <Button
              variant="danger"
              loading={reject.isPending}
              onClick={async () => {
                try {
                  await reject.mutateAsync({ id: q.id, body: { reason: nullable(reason) } });
                  setRejecting(false);
                } catch {
                  // toast shown
                }
              }}
            >
              Reject quotation
            </Button>
          </>
        }
      >
        <FormField label="Reason" hint="Optional — price, timing, competitor…">
          <Textarea rows={3} maxLength={500} value={reason} onChange={(e) => setReason(e.target.value)} autoFocus />
        </FormField>
      </Modal>
    </div>
  );
}

function QuotationPageInner({ id }: { id: string }) {
  const can = useCan();
  const search = useSearchParams();
  const isNew = id === 'new';
  const q = quotations.useOne(isNew ? undefined : id);

  if (isNew) {
    if (!can('quotations.manage')) return <Alert variant="warning">You don’t have permission to create quotations.</Alert>;
    return (
      <div className={styles.printRoot}>
        <PageHeader title="New quotation" backHref="/sales/quotations" backLabel="Quotations" />
        <QuotationEditor quotation={null} initialCustomerId={search.get('customer_id') ?? undefined} onDone={() => undefined} />
      </div>
    );
  }
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard lines={6} />;
  return <QuotationDetail key={`${q.data.id}`} q={q.data} />;
}

export default function QuotationPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  return (
    <Suspense fallback={<SkeletonCard lines={6} />}>
      <QuotationPageInner id={id} />
    </Suspense>
  );
}
