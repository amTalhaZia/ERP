'use client';

import { useId, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { BookOpen, CheckCircle2, Paperclip, Pencil, Plus, Send, Trash2, Upload, Wallet, X, XCircle } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, ConfirmDialog, CurrencyInput, DateInput, ErrorState, FormField, FormGrid, IconButton, Input, PageHeader, Select, SkeletonCard, StatusBadge, Textarea, useToast,
} from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import { useAppSelector } from '@/store';
import { selectUser } from '@/store/sessionSlice';
import { toScaled } from '@/features/inventory/decimal';
import {
  downloadExpenseReceipt, expenseClaims, uploadExpenseReceipt, useAccountOptions, useCurrency, useTaxRateOptions,
  type ExpenseClaim, type ExpenseClaimInput, type ExpenseClaimLine,
} from './api';
import { amountWithTax, centsToDecimal, trimDecimal } from './money';
import { RecordPaymentDrawer } from './RecordPaymentDrawer';
import { BranchField, ReasonDialog, useInvalidateReceivables } from './shared';
import crud from '@/components/crud/crud.module.scss';
import styles from './receivables.module.scss';

const CATEGORIES = ['Travel', 'Accommodation', 'Meals', 'Fuel', 'Transport', 'Office supplies', 'Telephone & internet', 'Training', 'Entertainment', 'Other'];
const today = () => new Date().toISOString().slice(0, 10);

interface DraftClaimLine {
  key: number;
  expense_date: string;
  category: string;
  account_id: string;
  description: string;
  amount: string;
  tax_rate_id: string;
}
let seq = 0;
const blank = (): DraftClaimLine => ({ key: ++seq, expense_date: today(), category: '', account_id: '', description: '', amount: '', tax_rate_id: '' });
const fromLine = (l: ExpenseClaimLine): DraftClaimLine => ({
  key: ++seq,
  expense_date: l.expense_date,
  category: l.category,
  account_id: String(l.account_id),
  description: l.description ?? '',
  amount: trimDecimal(l.amount),
  tax_rate_id: l.tax_rate_id ? String(l.tax_rate_id) : '',
});
const isBlank = (l: DraftClaimLine) => !l.category.trim() && !l.amount && !l.account_id && !l.description.trim();

function ClaimEditor({ claim, onDone }: { claim: ExpenseClaim | null; onDone?: () => void }) {
  const router = useRouter();
  const currency = useCurrency();
  const listId = useId();
  const create = expenseClaims.useCreate({ onSuccess: (c) => router.replace(`/finance/expenses/${c.id}`) });
  const update = expenseClaims.useUpdate({ onSuccess: () => onDone?.() });
  const accounts = useAccountOptions('expense');
  const tax = useTaxRateOptions('purchase');
  const [date, setDate] = useState(claim?.claim_date ?? today());
  const [branchId, setBranchId] = useState(claim?.branch_id ? String(claim.branch_id) : '');
  const [notes, setNotes] = useState(claim?.notes ?? '');
  const [lines, setLines] = useState<DraftClaimLine[]>(() => (claim?.lines?.length ? claim.lines.map(fromLine) : [blank()]));
  const [error, setError] = useState<string | null>(null);
  const [fe, setFe] = useState<Record<string, string>>({});
  const hadReceipts = claim?.lines?.some((l) => l.has_receipt) ?? false;

  const set = (key: number, patch: Partial<DraftClaimLine>) => setLines((ls) => ls.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const submittedKeys = lines.filter((l) => !isBlank(l)).map((l) => l.key);
  const err = (key: number, f: string) => {
    const i = submittedKeys.indexOf(key);
    return i >= 0 ? fe[`lines.${i}.${f}`] : undefined;
  };
  const amounts = lines.map((l) => amountWithTax(l.amount, l.tax_rate_id ? tax.byId.get(Number(l.tax_rate_id))?.rate ?? '0' : '0'));
  const totals = amounts.reduce((a, x, i) => (isBlank(lines[i]!) ? a : { net: a.net + x.net, tax: a.tax + x.tax, total: a.total + x.total }), { net: 0n, tax: 0n, total: 0n });

  const save = async () => {
    setError(null);
    setFe({});
    const filled = lines.filter((l) => !isBlank(l));
    if (filled.length === 0) return setError('Add at least one expense line.');
    const bad = filled.findIndex((l) => !l.category.trim() || !l.account_id || !(toScaled(l.amount) > 0n) || !l.expense_date);
    if (bad >= 0) return setError(`Line ${bad + 1}: date, category, account and an amount above zero are required.`);
    const body: ExpenseClaimInput = {
      claim_date: date,
      branch_id: branchId ? Number(branchId) : null,
      notes: notes.trim() || null,
      lines: filled.map((l) => ({
        expense_date: l.expense_date,
        category: l.category.trim(),
        account_id: Number(l.account_id),
        description: l.description.trim() || null,
        amount: l.amount,
        tax_rate_id: l.tax_rate_id ? Number(l.tax_rate_id) : null,
      })),
    };
    try {
      if (claim) await update.mutateAsync({ id: claim.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      const x = e as ApiError;
      if (x.status === 422 && x.errors) {
        const m: Record<string, string> = {};
        for (const [k, v] of Object.entries(x.errors)) m[k] = v[0] ?? '';
        setFe(m);
        setError(Object.keys(m).some((k) => k.startsWith('lines.')) ? 'Please correct the highlighted lines.' : Object.values(m)[0] ?? x.message);
      } else setError(x.message);
    }
  };

  return (
    <Card>
      <CardHeader title={claim ? `Edit ${claim.number}` : 'New expense claim'} description="Saved as a draft. Attach receipts to each line after saving, then submit for approval." />
      <CardBody>
        {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
        {hadReceipts && <Alert variant="warning" style={{ marginBottom: 16 }}>Saving replaces all lines — receipts already attached will be removed and must be uploaded again.</Alert>}
        {claim?.status === 'rejected' && <Alert variant="info" style={{ marginBottom: 16 }}>Saving moves this rejected claim back to draft so you can resubmit it.</Alert>}
        <FormGrid columns={3}>
          <FormField label="Claim date" required error={fe.claim_date}><DateInput value={date} onValueChange={(v) => setDate(v ?? '')} /></FormField>
          <BranchField value={branchId} onChange={setBranchId} error={fe.branch_id} />
          <FormField label="Notes" error={fe.notes} span={2}><Textarea rows={1} maxLength={5000} value={notes} onChange={(e) => setNotes(e.target.value)} /></FormField>
        </FormGrid>
        <datalist id={listId}>{CATEGORIES.map((c) => <option key={c} value={c} />)}</datalist>
        <div className={crud.tableScroll} style={{ marginTop: 20 }}>
          <table className={crud.lines} style={{ minWidth: 1000 }}>
            <thead>
              <tr>
                <th style={{ width: 150 }}>Date</th>
                <th style={{ width: 160 }}>Category</th>
                <th style={{ width: 210 }}>Expense account</th>
                <th>Description</th>
                <th className={crud.r} style={{ width: 140 }}>Amount (net)</th>
                <th style={{ width: 140 }}>Tax</th>
                <th className={crud.r} style={{ width: 120 }}>Total</th>
                <th style={{ width: 44 }} aria-label="Remove" />
              </tr>
            </thead>
            <tbody>
              {lines.map((l, i) => (
                <tr key={l.key}>
                  <td><DateInput aria-label={`Line ${i + 1} date`} value={l.expense_date} invalid={!!err(l.key, 'expense_date')} onValueChange={(v) => set(l.key, { expense_date: v ?? '' })} />{err(l.key, 'expense_date') && <small className={styles.fieldError}>{err(l.key, 'expense_date')}</small>}</td>
                  <td><Input aria-label={`Line ${i + 1} category`} list={listId} maxLength={60} value={l.category} invalid={!!err(l.key, 'category')} onChange={(e) => set(l.key, { category: e.target.value })} />{err(l.key, 'category') && <small className={styles.fieldError}>{err(l.key, 'category')}</small>}</td>
                  <td><Select aria-label={`Line ${i + 1} account`} placeholder="Select account" value={l.account_id} options={accounts.options} invalid={!!err(l.key, 'account_id')} onChange={(e) => set(l.key, { account_id: e.target.value })} />{err(l.key, 'account_id') && <small className={styles.fieldError}>{err(l.key, 'account_id')}</small>}</td>
                  <td><Input aria-label={`Line ${i + 1} description`} maxLength={255} value={l.description} onChange={(e) => set(l.key, { description: e.target.value })} /></td>
                  <td><CurrencyInput aria-label={`Line ${i + 1} amount`} currency={currency} value={l.amount || null} invalid={!!err(l.key, 'amount')} onValueChange={(v) => set(l.key, { amount: v ?? '' })} />{err(l.key, 'amount') && <small className={styles.fieldError}>{err(l.key, 'amount')}</small>}</td>
                  <td><Select aria-label={`Line ${i + 1} tax`} placeholder="No tax" value={l.tax_rate_id} options={tax.rows.map((t) => ({ value: String(t.id), label: `${t.code} · ${trimDecimal(t.rate) || '0'}%` }))} onChange={(e) => set(l.key, { tax_rate_id: e.target.value })} /></td>
                  <td className={crud.r} style={{ paddingTop: 14 }}>{formatMoney(centsToDecimal(amounts[i]!.total), currency)}</td>
                  <td><IconButton icon={<X size={16} />} size="sm" aria-label={`Remove line ${i + 1}`} disabled={lines.length <= 1} onClick={() => setLines((ls) => ls.filter((x) => x.key !== l.key))} /></td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={8}><Button size="sm" variant="ghost" leftIcon={<Plus size={14} />} onClick={() => setLines((ls) => [...ls, blank()])}>Add line</Button></td>
              </tr>
            </tfoot>
          </table>
        </div>
        {fe.lines && <Alert variant="danger" style={{ marginTop: 12 }}>{fe.lines}</Alert>}
        <div className={styles.linesFooter}>
          <span className={styles.hint}>Preview — the server recalculates tax per line (2-decimal rounding).</span>
          <div className={crud.totals}>
            <span>Net</span><span>{formatMoney(centsToDecimal(totals.net), currency)}</span>
            <span>Tax</span><span>{formatMoney(centsToDecimal(totals.tax), currency)}</span>
            <span className={crud.grand}>Total</span><span className={crud.grand}>{formatMoney(centsToDecimal(totals.total), currency)}</span>
          </div>
        </div>
        <div className={styles.row} style={{ marginTop: 20, justifyContent: 'flex-end' }}>
          <Button variant="secondary" onClick={() => (claim ? onDone?.() : router.push('/finance/expenses'))}>Cancel</Button>
          <Button onClick={save} loading={create.isPending || update.isPending}>{claim ? 'Save changes' : 'Save draft'}</Button>
        </div>
      </CardBody>
    </Card>
  );
}

function ReceiptCell({ claim, line, canUpload }: { claim: ExpenseClaim; line: ExpenseClaimLine; canUpload: boolean }) {
  const toast = useToast();
  const invalidate = useInvalidateReceivables();
  const input = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const upload = async (file: File) => {
    setError(null);
    if (file.size > 5 * 1024 * 1024) return setError('Max 5 MB.');
    setBusy(true);
    try {
      await uploadExpenseReceipt(claim.id, line.id, file);
      toast.success('Receipt attached');
      void invalidate();
    } catch (e) {
      const x = e as ApiError;
      setError(x.errors?.receipt?.[0] ?? x.message);
    } finally {
      setBusy(false);
      if (input.current) input.current.value = '';
    }
  };

  return (
    <div className={styles.fileCell}>
      {line.has_receipt && (
        <button
          type="button"
          className={styles.linkBtn}
          onClick={() => downloadExpenseReceipt(claim.id, line).catch((e: ApiError) => toast.error('Download failed', e.message))}
        >
          <Paperclip size={12} style={{ display: 'inline', verticalAlign: '-1px' }} /> {line.receipt_name ?? 'Receipt'}
        </button>
      )}
      {canUpload && (
        <>
          <input ref={input} type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" onChange={(e) => e.target.files?.[0] && void upload(e.target.files[0])} />
          <Button size="sm" variant="ghost" leftIcon={<Upload size={14} />} loading={busy} onClick={() => input.current?.click()}>
            {line.has_receipt ? 'Replace' : 'Upload'}
          </Button>
        </>
      )}
      {!line.has_receipt && !canUpload && <span className={crud.muted}>—</span>}
      {error && <small className={styles.fieldError}>{error}</small>}
    </div>
  );
}

type Confirm = 'submit' | 'approve' | 'reject' | 'delete' | null;

export function ExpenseClaimPage({ id }: { id: string }) {
  const router = useRouter();
  const can = useCan();
  const me = useAppSelector(selectUser);
  const isNew = id === 'new';
  const q = expenseClaims.useOne(isNew ? undefined : id);
  const submit = expenseClaims.useAction('submit', { success: 'Claim submitted for approval' });
  const approve = expenseClaims.useAction('approve', { success: 'Claim approved and posted' });
  const reject = expenseClaims.useAction<{ reason: string }>('reject', { success: 'Claim rejected' });
  const del = expenseClaims.useDelete({ onSuccess: () => router.replace('/finance/expenses') });
  const [confirm, setConfirm] = useState<Confirm>(null);
  const [editing, setEditing] = useState(false);
  const [paying, setPaying] = useState(0);
  const [actionError, setActionError] = useState<string | null>(null);

  if (isNew) {
    if (!can('expenses.submit')) return <Alert variant="warning">You don’t have permission to submit expense claims.</Alert>;
    return (
      <div className={styles.stack}>
        <PageHeader title="New expense claim" backHref="/finance/expenses" backLabel="Expense claims" />
        <ClaimEditor claim={null} />
      </div>
    );
  }
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const c = q.data;
  const own = me?.id === c.user_id;
  const editable = own && can('expenses.submit') && (c.status === 'draft' || c.status === 'rejected');
  const canDecide = c.status === 'submitted' && can('expenses.approve') && !own;

  if (editing && editable) {
    return (
      <div className={styles.stack}>
        <PageHeader title={`Edit ${c.number}`} backHref="/finance/expenses" backLabel="Expense claims" />
        <ClaimEditor claim={c} onDone={() => setEditing(false)} />
      </div>
    );
  }

  const run = async (fn: () => Promise<unknown>) => {
    setActionError(null);
    try {
      await fn();
    } catch (e) {
      setActionError((e as ApiError).message);
    } finally {
      setConfirm(null);
    }
  };

  return (
    <div className={styles.stack}>
      <PageHeader
        title={c.number}
        description={c.user?.name ?? undefined}
        backHref="/finance/expenses"
        backLabel="Expense claims"
        meta={<StatusBadge status={c.status} />}
        actions={
          <>
            {editable && <Button variant="secondary" leftIcon={<Pencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>}
            {editable && <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirm('delete')}>Delete</Button>}
            {editable && c.status === 'draft' && <Button leftIcon={<Send size={16} />} onClick={() => setConfirm('submit')}>Submit</Button>}
            {canDecide && <Button variant="secondary" leftIcon={<XCircle size={16} />} onClick={() => setConfirm('reject')}>Reject</Button>}
            {canDecide && <Button leftIcon={<CheckCircle2 size={16} />} onClick={() => setConfirm('approve')}>Approve</Button>}
            {c.status === 'approved' && toScaled(c.balance_due) > 0n && can('payments.create') && <Button leftIcon={<Wallet size={16} />} onClick={() => setPaying((n) => n + 1)}>Pay claim</Button>}
          </>
        }
      />
      {actionError && <Alert variant="danger" dismissible onDismiss={() => setActionError(null)}>{actionError}</Alert>}
      {c.status === 'rejected' && <Alert variant="warning" title="Rejected">{c.rejection_reason ?? 'No reason given.'} {own ? 'Edit and save the claim to move it back to draft, then resubmit.' : ''}</Alert>}
      {c.status === 'submitted' && own && can('expenses.approve') && <Alert variant="info">You can’t approve your own claim — another approver must decide it.</Alert>}

      <div className={crud.detailGrid}>
        <Card>
          <CardHeader title="Expenses" description={editable ? 'Attach a receipt (PDF or image, max 5 MB) to each line before submitting.' : undefined} />
          <CardBody padding="none">
            <div className={crud.tableScroll}>
              <table className={crud.lines} style={{ minWidth: 760 }}>
                <thead><tr><th>Date</th><th>Category</th><th>Description</th><th className={crud.r}>Net</th><th className={crud.r}>Tax</th><th className={crud.r}>Total</th><th>Receipt</th></tr></thead>
                <tbody>
                  {c.lines?.map((l) => (
                    <tr key={l.id}>
                      <td>{formatDate(l.expense_date)}</td>
                      <td>{l.category}</td>
                      <td>{l.description ?? '—'}</td>
                      <td className={crud.r}>{formatMoney(l.amount, c.currency)}</td>
                      <td className={crud.r}>{Number(l.tax_amount) ? formatMoney(l.tax_amount, c.currency) : '—'}</td>
                      <td className={crud.r}>{formatMoney(l.line_total, c.currency)}</td>
                      <td><ReceiptCell claim={c} line={l} canUpload={editable} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div style={{ padding: 16 }}>
              <div className={crud.totals}>
                <span>Net</span><span>{formatMoney(c.subtotal, c.currency)}</span>
                <span>Tax</span><span>{formatMoney(c.tax_total, c.currency)}</span>
                <span className={crud.grand}>Total</span><span className={crud.grand}>{formatMoney(c.total, c.currency)}</span>
                <span>Paid</span><span>{formatMoney(c.amount_paid, c.currency)}</span>
                <span>Balance due</span><span>{formatMoney(c.balance_due, c.currency)}</span>
              </div>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardHeader title="Details" />
          <CardBody>
            <dl className={crud.dl}>
              <dt>Claimant</dt><dd>{c.user?.name ?? '—'}</dd>
              <dt>Claim date</dt><dd>{formatDate(c.claim_date)}</dd>
              <dt>Currency</dt><dd>{c.currency}</dd>
              <dt>Submitted</dt><dd>{c.submitted_at ? formatDateTime(c.submitted_at) : '—'}</dd>
              <dt>Decided</dt><dd>{c.approved_at ? `${formatDateTime(c.approved_at)}${c.approver ? ` by ${c.approver.name}` : ''}` : c.approver ? c.approver.name : '—'}</dd>
              <dt>Journal</dt><dd>{c.journal_entry_id ? <a className={styles.linkBtn} href={`/finance/journals/${c.journal_entry_id}`}><BookOpen size={12} style={{ display: 'inline', verticalAlign: '-1px' }} /> View journal entry</a> : 'Not posted'}</dd>
              <dt>Notes</dt><dd style={{ whiteSpace: 'pre-wrap' }}>{c.notes ?? '—'}</dd>
            </dl>
          </CardBody>
        </Card>
      </div>

      <ConfirmDialog
        open={confirm === 'submit'}
        onClose={() => setConfirm(null)}
        title={`Submit ${c.number}?`}
        description={c.lines?.some((l) => !l.has_receipt) ? 'Some lines have no receipt attached. Submit anyway? The claim can no longer be edited once submitted.' : 'The claim goes to an approver and can no longer be edited.'}
        confirmLabel="Submit claim"
        onConfirm={() => run(() => submit.mutateAsync({ id: c.id }))}
      />
      <ConfirmDialog
        open={confirm === 'approve'}
        onClose={() => setConfirm(null)}
        title={`Approve ${c.number}?`}
        description={`Posts ${formatMoney(c.total, c.currency)} (Dr expenses & tax / Cr accrued liabilities) — payable to ${c.user?.name ?? 'the employee'}.`}
        confirmLabel="Approve & post"
        onConfirm={() => run(() => approve.mutateAsync({ id: c.id }))}
      />
      <ConfirmDialog open={confirm === 'delete'} onClose={() => setConfirm(null)} title={`Delete ${c.number}?`} destructive confirmLabel="Delete" onConfirm={() => run(() => del.mutateAsync(c.id))} />
      <ReasonDialog
        key={confirm === 'reject' ? 'reject-open' : 'reject-closed'}
        open={confirm === 'reject'}
        onClose={() => setConfirm(null)}
        title={`Reject ${c.number}?`}
        description="The claimant sees the reason and can correct and resubmit the claim."
        confirmLabel="Reject claim"
        onSubmit={(reason) => reject.mutateAsync({ id: c.id, body: { reason } })}
      />
      {paying > 0 && (
        <RecordPaymentDrawer
          key={paying}
          open
          onClose={() => setPaying(0)}
          type="payment"
          prefill={{ partnerType: 'employee', partnerId: c.user_id, allocation: { type: 'expense_claim', id: c.id, amount: c.balance_due } }}
        />
      )}
    </div>
  );
}
