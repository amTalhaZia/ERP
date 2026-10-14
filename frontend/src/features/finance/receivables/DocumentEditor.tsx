'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Alert, Button, Card, CardBody, CardHeader, DateInput, FormField, FormGrid, Input, Select, Textarea } from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { partnerLabel, useAccountOptions, useCurrency, usePartners, useTaxRateOptions } from './api';
import { DOC_CONFIG, docDate, docPartnerId, docResource, isInvoice, type AnyDoc, type DocInput, type DocKind } from './documents';
import { DocumentLinesEditor, blankDocLine, docLineToInput, draftFromLine, isBlankDocLine, type DraftDocLine } from './DocumentLinesEditor';
import { BranchField } from './shared';
import styles from './receivables.module.scss';

const HEADER_FIELDS = ['customer_id', 'supplier_id', 'supplier_reference', 'invoice_date', 'bill_date', 'due_date', 'branch_id', 'notes', 'terms', 'currency', 'lines'];

/**
 * Create / edit a draft invoice or bill (controlled state; lines are
 * replaced wholesale on save). Totals preview mirrors the server rounding.
 */
export function DocumentEditor({ kind, doc, onDone }: { kind: DocKind; doc: AnyDoc | null; onDone?: () => void }) {
  const cfg = DOC_CONFIG[kind];
  const router = useRouter();
  const currency = useCurrency();
  const res = docResource(kind);
  const create = res.useCreate({ onSuccess: (d) => router.replace(`${cfg.path}/${d.id}`) });
  const update = res.useUpdate({ onSuccess: () => onDone?.() });
  const partners = usePartners(cfg.partnerType);
  const tax = useTaxRateOptions(cfg.taxScope);
  const accounts = useAccountOptions(cfg.accountTypes);

  const [partnerId, setPartnerId] = useState(doc ? String(docPartnerId(doc)) : '');
  const [date, setDate] = useState(doc ? docDate(doc) : new Date().toISOString().slice(0, 10));
  const [dueDate, setDueDate] = useState(doc?.due_date ?? '');
  const [supplierRef, setSupplierRef] = useState(doc && !isInvoice(doc) ? doc.supplier_reference : '');
  const [branchId, setBranchId] = useState(doc?.branch_id ? String(doc.branch_id) : '');
  const [notes, setNotes] = useState(doc?.notes ?? '');
  const [terms, setTerms] = useState(doc && isInvoice(doc) ? doc.terms ?? '' : '');
  const [lines, setLines] = useState<DraftDocLine[]>(() => (doc?.lines?.length ? doc.lines.map(draftFromLine) : [blankDocLine()]));
  const [error, setError] = useState<string | null>(null);
  const [fe, setFe] = useState<Record<string, string>>({});

  const docCurrency = doc?.currency ?? partners.data?.find((p) => String(p.id) === partnerId)?.currency ?? currency;
  const selectedPartner = partners.data?.find((p) => String(p.id) === partnerId);

  const save = async () => {
    setError(null);
    setFe({});
    const filled = lines.filter((l) => !isBlankDocLine(l));
    if (!partnerId) return setError(`Choose a ${cfg.partnerLabel.toLowerCase()}.`);
    if (kind === 'bill' && !supplierRef.trim()) return setFe({ supplier_reference: "Enter the supplier's invoice number." });
    if (filled.length === 0) return setError('Add at least one line.');
    const missing = filled.findIndex((l) => !l.description.trim());
    if (missing >= 0) return setError(`Line ${missing + 1}: description is required.`);
    const badQty = filled.findIndex((l) => !(Number(l.quantity) > 0));
    if (badQty >= 0) return setError(`Line ${badQty + 1}: quantity must be greater than zero.`);

    const common = {
      due_date: dueDate || null,
      branch_id: branchId ? Number(branchId) : null,
      notes: notes.trim() || null,
      lines: filled.map(docLineToInput),
    };
    const body: DocInput =
      kind === 'invoice'
        ? { ...common, customer_id: Number(partnerId), invoice_date: date, terms: terms.trim() || null }
        : { ...common, supplier_id: Number(partnerId), bill_date: date, supplier_reference: supplierRef.trim() };
    try {
      if (doc) await update.mutateAsync({ id: doc.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      const err = e as ApiError;
      if (err.status === 422 && err.errors) {
        const m: Record<string, string> = {};
        for (const [k, v] of Object.entries(err.errors)) m[k] = v[0] ?? '';
        setFe(m);
        const general = Object.entries(m).find(([k]) => !HEADER_FIELDS.includes(k) && !k.startsWith('lines.'));
        setError(general?.[1] ?? (Object.keys(m).some((k) => k.startsWith('lines.')) ? 'Please correct the highlighted lines.' : 'Please correct the highlighted fields.'));
      } else {
        setError(err.message);
      }
    }
  };

  const dateField = kind === 'invoice' ? 'invoice_date' : 'bill_date';
  const partnerField = kind === 'invoice' ? 'customer_id' : 'supplier_id';

  return (
    <Card>
      <CardHeader
        title={doc ? `Edit ${doc.number}` : `New ${cfg.label.toLowerCase()}`}
        description={doc?.status === 'pending_approval' ? 'Pending approval — edits are still allowed until it is approved.' : 'Saved as a draft. Approving posts it to the general ledger.'}
      />
      <CardBody>
        {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
        <FormGrid columns={3}>
          <FormField label={cfg.partnerLabel} required error={fe[partnerField]} hint={partners.isSuccess && partners.data.length === 0 ? `No active ${cfg.partnerType}s found.` : undefined}>
            <Select
              value={partnerId}
              placeholder={partners.isLoading ? 'Loading…' : `Select ${cfg.partnerType}`}
              options={[
                ...(doc && !partners.data?.some((p) => p.id === docPartnerId(doc)) ? [{ value: String(docPartnerId(doc)), label: (isInvoice(doc) ? doc.customer?.name : doc.supplier?.name) ?? `#${docPartnerId(doc)}` }] : []),
                ...(partners.data ?? []).map((p) => ({ value: String(p.id), label: partnerLabel(p) })),
              ]}
              onChange={(e) => setPartnerId(e.target.value)}
            />
          </FormField>
          {kind === 'bill' && (
            <FormField label="Supplier reference" required hint="The supplier's own invoice number" error={fe.supplier_reference}>
              <Input value={supplierRef} maxLength={60} onChange={(e) => setSupplierRef(e.target.value)} />
            </FormField>
          )}
          <FormField label={cfg.dateLabel} required error={fe[dateField]}>
            <DateInput value={date} onValueChange={(v) => setDate(v ?? '')} />
          </FormField>
          <FormField
            label="Due date"
            error={fe.due_date}
            hint={dueDate ? undefined : `Leave empty to use ${selectedPartner?.payment_terms_days ? `the ${selectedPartner.payment_terms_days}-day` : 'the partner’s'} payment terms (30 days if none)`}
          >
            <DateInput value={dueDate} min={date || undefined} onValueChange={(v) => setDueDate(v ?? '')} />
          </FormField>
          <BranchField value={branchId} onChange={setBranchId} error={fe.branch_id} />
          <FormField label="Currency" hint="From the partner (or company default)">
            <Input value={docCurrency} readOnly />
          </FormField>
        </FormGrid>

        <div style={{ marginTop: 20 }}>
          {tax.query.isSuccess && tax.rows.length === 0 && <div className={styles.hint} style={{ marginBottom: 8 }}>No active {cfg.taxScope} tax rates are configured.</div>}
          <DocumentLinesEditor
            lines={lines}
            onChange={setLines}
            taxRates={tax.rows}
            accountOptions={accounts.options}
            accountPlaceholder={cfg.accountPlaceholder}
            priceField={cfg.priceField}
            currency={docCurrency}
            errors={fe}
          />
          {fe.lines && <Alert variant="danger" style={{ marginTop: 12 }}>{fe.lines}</Alert>}
        </div>

        <FormGrid columns={2} style={{ marginTop: 20 }}>
          <FormField label="Notes" error={fe.notes} span={kind === 'bill' ? 'full' : undefined}>
            <Textarea rows={3} maxLength={5000} value={notes} onChange={(e) => setNotes(e.target.value)} />
          </FormField>
          {kind === 'invoice' && (
            <FormField label="Terms" hint="Printed on the invoice PDF" error={fe.terms}>
              <Textarea rows={3} maxLength={5000} value={terms} onChange={(e) => setTerms(e.target.value)} />
            </FormField>
          )}
        </FormGrid>

        <div className={styles.row} style={{ marginTop: 20, justifyContent: 'flex-end' }}>
          <Button variant="secondary" onClick={() => (doc ? onDone?.() : router.push(cfg.path))}>Cancel</Button>
          <Button onClick={save} loading={create.isPending || update.isPending}>{doc ? 'Save changes' : 'Save draft'}</Button>
        </div>
      </CardBody>
    </Card>
  );
}
