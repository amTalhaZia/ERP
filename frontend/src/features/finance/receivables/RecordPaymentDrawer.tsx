'use client';

import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Wand2 } from 'lucide-react';
import {
  Alert, Badge, Button, CurrencyInput, DateInput, Drawer, EmptyState, FormField, FormGrid, Input, SegmentedControl, Select, Skeleton, Textarea, useToast,
} from '@/components/ui';
import { api } from '@/lib/api/client';
import type { ApiError } from '@/lib/api/errors';
import type { Single } from '@/lib/api/types';
import { formatDate, formatMoney, humanize } from '@/lib/format';
import { fromScaled, toScaled } from '@/features/inventory/decimal';
import {
  ALLOCATION_HREF, PAYMENT_METHODS, fetchOpenDocuments, partnerLabel, useBankAccountOptions, useClaimants, useCompanyId, useCurrency, usePartners,
  type AllocationType, type OpenDocument, type PartnerType, type Payment, type PaymentInput, type PaymentMethod, type PaymentType,
} from './api';
import { BranchField, useInvalidateReceivables } from './shared';
import styles from './receivables.module.scss';

export interface PaymentPrefill {
  partnerType: PartnerType;
  partnerId: number;
  allocation?: { type: AllocationType; id: number; amount: string };
}

const docKey = (d: { type: AllocationType; id: number }) => `${d.type}:${d.id}`;
const today = () => new Date().toISOString().slice(0, 10);

/**
 * Record a receipt (customer) or payment (supplier / employee). The amount
 * must be fully allocated to the partner's open documents (backend rule).
 * Remount with a `key` every time it opens.
 */
export function RecordPaymentDrawer({ open, onClose, type, prefill, onRecorded }: { open: boolean; onClose: () => void; type: PaymentType; prefill?: PaymentPrefill; onRecorded?: (p: Payment) => void }) {
  const companyId = useCompanyId();
  const companyCurrency = useCurrency();
  const toast = useToast();
  const invalidate = useInvalidateReceivables();
  const [partnerType, setPartnerType] = useState<PartnerType>(prefill?.partnerType ?? (type === 'receipt' ? 'customer' : 'supplier'));
  const [partnerId, setPartnerId] = useState(prefill?.partnerId ? String(prefill.partnerId) : '');
  const [bankId, setBankId] = useState('');
  const [date, setDate] = useState(today());
  const [method, setMethod] = useState<PaymentMethod>('bank_transfer');
  const [reference, setReference] = useState('');
  const [notes, setNotes] = useState('');
  const [branchId, setBranchId] = useState('');
  const [amount, setAmount] = useState<string>(prefill?.allocation?.amount ?? '');
  const [alloc, setAlloc] = useState<Record<string, string>>(prefill?.allocation ? { [docKey(prefill.allocation)]: prefill.allocation.amount } : {});
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);

  const customers = usePartners('customer', { enabled: open && partnerType === 'customer' });
  const suppliers = usePartners('supplier', { enabled: open && partnerType === 'supplier' });
  const claimants = useClaimants({ enabled: open && partnerType === 'employee' });
  const banks = useBankAccountOptions({ enabled: open });
  const effectiveBank = bankId || (banks.rows.length === 1 ? String(banks.rows[0]!.id) : '');
  const bank = banks.rows.find((b) => String(b.id) === effectiveBank);
  const currency = bank?.currency ?? companyCurrency;

  const docs = useQuery({
    queryKey: [companyId, 'payments', 'open-documents', partnerType, partnerId],
    queryFn: () => fetchOpenDocuments(partnerType, Number(partnerId)),
    enabled: open && !!partnerId,
  });
  const openDocs = useMemo(
    () => [...(docs.data ?? [])].sort((a, b) => (a.due_date ?? a.date).localeCompare(b.due_date ?? b.date) || a.id - b.id),
    [docs.data],
  );

  const partnerOptions =
    partnerType === 'customer'
      ? (customers.data ?? []).map((p) => ({ value: String(p.id), label: partnerLabel(p) }))
      : partnerType === 'supplier'
        ? (suppliers.data ?? []).map((p) => ({ value: String(p.id), label: partnerLabel(p) }))
        : (claimants.data ?? []).map((p) => ({ value: String(p.id), label: p.name }));
  // Keep a prefilled partner visible even before (or without) the lookup list.
  if (prefill && partnerId === String(prefill.partnerId) && !partnerOptions.some((o) => o.value === partnerId)) {
    partnerOptions.unshift({ value: partnerId, label: `#${partnerId}` });
  }

  const allocated = openDocs.reduce((acc, d) => acc + toScaled(alloc[docKey(d)]), 0n);
  const target = toScaled(amount);
  const diff = target - allocated;
  const overAllocated = openDocs.filter((d) => toScaled(alloc[docKey(d)]) > toScaled(d.balance_due));
  const currencyMismatch = bank ? openDocs.filter((d) => toScaled(alloc[docKey(d)]) > 0n && d.currency !== bank.currency) : [];
  const valid = !!partnerId && !!effectiveBank && target > 0n && diff === 0n && overAllocated.length === 0 && currencyMismatch.length === 0;

  const autoAllocate = () => {
    let remaining = target > 0n ? target : openDocs.reduce((acc, d) => acc + toScaled(d.balance_due), 0n);
    if (target <= 0n) setAmount(fromScaled(remaining));
    const next: Record<string, string> = {};
    for (const d of openDocs) {
      if (remaining <= 0n) break;
      const bal = toScaled(d.balance_due);
      const take = bal < remaining ? bal : remaining;
      if (take > 0n) next[docKey(d)] = fromScaled(take);
      remaining -= take;
    }
    setAlloc(next);
  };

  const switchPartnerType = (pt: PartnerType) => {
    setPartnerType(pt);
    setPartnerId('');
    setAlloc({});
  };

  const submit = async () => {
    setError(null);
    setFieldErrors({});
    if (!partnerId) return setError(`Choose a ${partnerType}.`);
    if (!effectiveBank) return setError('Choose the bank or cash account.');
    if (target <= 0n) return setError('Enter an amount greater than zero.');
    if (overAllocated.length) return setError(`Allocation to ${overAllocated[0]!.number} exceeds its balance due.`);
    if (currencyMismatch.length) return setError(`${currencyMismatch[0]!.number} is in ${currencyMismatch[0]!.currency}; choose a ${currencyMismatch[0]!.currency} bank account.`);
    if (diff !== 0n) return setError(`Allocations must equal the amount exactly (${diff > 0n ? 'unallocated' : 'over-allocated'} ${formatMoney(fromScaled(diff < 0n ? -diff : diff), currency)}).`);

    const allocations = openDocs.filter((d) => toScaled(alloc[docKey(d)]) > 0n).map((d) => ({ type: d.type, id: d.id, amount: fromScaled(toScaled(alloc[docKey(d)])) }));
    const body: PaymentInput = {
      type,
      partner_type: partnerType,
      partner_id: Number(partnerId),
      payment_date: date,
      method,
      bank_account_id: Number(effectiveBank),
      amount: fromScaled(target),
      reference: reference.trim() || null,
      notes: notes.trim() || null,
      branch_id: branchId ? Number(branchId) : null,
      allocations,
    };
    setSaving(true);
    try {
      const res = await api.post<Single<Payment>>('/payments', body);
      void invalidate();
      toast.success(type === 'receipt' ? `Receipt ${res.data.data.number} recorded` : `Payment ${res.data.data.number} recorded`);
      onRecorded?.(res.data.data);
      onClose();
    } catch (e) {
      const err = e as ApiError;
      if (err.status === 422 && err.errors) {
        const fe: Record<string, string> = {};
        for (const [k, v] of Object.entries(err.errors)) fe[k] = v[0] ?? '';
        // allocations.N.* → document key
        const mapped: Record<string, string> = {};
        for (const [k, v] of Object.entries(fe)) {
          const m = /^allocations\.(\d+)\./.exec(k);
          const a = m ? allocations[Number(m[1])] : undefined;
          if (a) mapped[docKey(a)] = v;
          else mapped[k] = v;
        }
        setFieldErrors(mapped);
        setError(Object.values(fe)[0] ?? err.message);
      } else {
        setError(err.message);
      }
    } finally {
      setSaving(false);
    }
  };

  const noun = type === 'receipt' ? 'receipt' : 'payment';
  const docNoun = partnerType === 'customer' ? 'invoices' : partnerType === 'supplier' ? 'bills' : 'approved expense claims';

  return (
    <Drawer
      open={open}
      onClose={onClose}
      size="xl"
      title={type === 'receipt' ? 'Record receipt' : 'Record payment'}
      description={type === 'receipt' ? 'Money received from a customer, allocated to their open invoices.' : 'Money paid to a supplier or employee, allocated to open bills or expense claims.'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button onClick={submit} loading={saving} disabled={!valid}>Record {noun}</Button>
        </>
      }
    >
      {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
      {type === 'payment' && !prefill && (
        <div style={{ marginBottom: 16 }}>
          <SegmentedControl<PartnerType>
            aria-label="Pay to"
            value={partnerType}
            onChange={switchPartnerType}
            options={[{ value: 'supplier', label: 'Supplier' }, { value: 'employee', label: 'Employee (expenses)' }]}
          />
        </div>
      )}
      <FormGrid columns={2}>
        <FormField label={humanize(partnerType)} required error={fieldErrors.partner_id}>
          <Select
            value={partnerId}
            disabled={!!prefill}
            placeholder={partnerType === 'employee' && claimants.data?.length === 0 ? 'No approved claims awaiting payment' : `Select ${partnerType}`}
            options={partnerOptions}
            onChange={(e) => {
              setPartnerId(e.target.value);
              setAlloc({});
            }}
          />
        </FormField>
        <FormField label="Bank / cash account" required error={fieldErrors.bank_account_id} hint={banks.query.isSuccess && banks.rows.length === 0 ? 'No active bank accounts — add one under Finance → Bank.' : undefined}>
          <Select value={effectiveBank} placeholder="Select account" options={banks.options} onChange={(e) => setBankId(e.target.value)} />
        </FormField>
        <FormField label="Date" required error={fieldErrors.payment_date}>
          <DateInput value={date} onValueChange={(v) => setDate(v ?? '')} />
        </FormField>
        <FormField label="Method" required error={fieldErrors.method}>
          <Select value={method} options={PAYMENT_METHODS} onChange={(e) => setMethod(e.target.value as PaymentMethod)} />
        </FormField>
        <FormField label="Amount" required error={fieldErrors.amount}>
          <CurrencyInput currency={currency} value={amount || null} onValueChange={(v) => setAmount(v ?? '')} />
        </FormField>
        <FormField label="Reference" hint="Cheque / transfer reference" error={fieldErrors.reference}>
          <Input value={reference} maxLength={100} onChange={(e) => setReference(e.target.value)} />
        </FormField>
        <BranchField value={branchId} onChange={setBranchId} error={fieldErrors.branch_id} />
        <FormField label="Notes" span="full" error={fieldErrors.notes}>
          <Textarea rows={2} maxLength={1000} value={notes} onChange={(e) => setNotes(e.target.value)} />
        </FormField>
      </FormGrid>

      <div className={styles.row} style={{ marginTop: 24, marginBottom: 8 }}>
        <strong>Allocate to open {docNoun}</strong>
        <Button size="sm" variant="secondary" leftIcon={<Wand2 size={14} />} onClick={autoAllocate} disabled={openDocs.length === 0}>
          Auto-allocate oldest first
        </Button>
      </div>
      {!partnerId ? (
        <EmptyState size="sm" title={`Choose a ${partnerType}`} description={`Their open ${docNoun} will be listed here.`} />
      ) : docs.isLoading ? (
        <Skeleton style={{ height: 120 }} />
      ) : docs.error ? (
        <Alert variant="danger">{(docs.error as unknown as ApiError).message}</Alert>
      ) : openDocs.length === 0 ? (
        <EmptyState size="sm" title={`No open ${docNoun}`} description="Payments must be fully allocated, so there is nothing to record against right now." />
      ) : (
        <div className={styles.scroll}>
          <table className={styles.allocTable} style={{ minWidth: 560 }}>
            <thead>
              <tr>
                <th>Document</th>
                <th>Due</th>
                <th className={styles.num}>Total</th>
                <th className={styles.num}>Balance due</th>
                <th className={styles.num} style={{ width: 170 }}>Allocate</th>
              </tr>
            </thead>
            <tbody>
              {openDocs.map((d: OpenDocument) => {
                const k = docKey(d);
                const over = toScaled(alloc[k]) > toScaled(d.balance_due);
                const msg = fieldErrors[k] ?? (over ? 'Exceeds balance due' : bank && d.currency !== bank.currency && toScaled(alloc[k]) > 0n ? `Currency ${d.currency} ≠ ${bank.currency}` : undefined);
                return (
                  <tr key={k} className={msg ? styles.invalidRow : undefined}>
                    <td>
                      <a href={ALLOCATION_HREF[d.type](d.id)} target="_blank" rel="noreferrer" className={styles.linkBtn}>{d.number}</a>
                      <div className={styles.hint}>{formatDate(d.date)}</div>
                    </td>
                    <td>{d.due_date ? formatDate(d.due_date) : '—'}</td>
                    <td className={styles.num}>{formatMoney(d.total, d.currency)}</td>
                    <td className={styles.num}>{formatMoney(d.balance_due, d.currency)}</td>
                    <td>
                      <CurrencyInput
                        aria-label={`Allocate to ${d.number}`}
                        currency={d.currency}
                        symbol="none"
                        invalid={!!msg}
                        value={alloc[k] || null}
                        onValueChange={(v) => setAlloc((a) => ({ ...a, [k]: v ?? '' }))}
                      />
                      {msg && <small className={styles.fieldError}>{msg}</small>}
                    </td>
                  </tr>
                );
              })}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={4}>Allocated</td>
                <td className={styles.num}>{formatMoney(fromScaled(allocated), currency)}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      )}
      {partnerId && openDocs.length > 0 && (
        <div className={styles.allocStatus}>
          {fieldErrors.allocations && <Alert variant="danger">{fieldErrors.allocations}</Alert>}
          {target <= 0n ? (
            <Badge variant="neutral">Enter an amount, or auto-allocate to settle all open {docNoun}</Badge>
          ) : diff === 0n ? (
            <Badge variant="success">Fully allocated</Badge>
          ) : (
            <Badge variant="warning">
              {diff > 0n ? 'Unallocated' : 'Over-allocated by'} {formatMoney(fromScaled(diff < 0n ? -diff : diff), currency)}
            </Badge>
          )}
        </div>
      )}
    </Drawer>
  );
}
