'use client';

import { use, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import { CheckCircle2, Plus, RotateCcw, Send, Trash2, X } from 'lucide-react';
import {
  Alert, Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, CurrencyInput, DateInput, ErrorState, FormField, FormGrid, IconButton, Input, PageHeader, Select, SkeletonCard, StatusBadge, Textarea,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime, formatMoney, humanize } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { accounts, journals, type JournalEntry } from '@/features/finance/api';
import styles from '@/components/crud/crud.module.scss';

interface DraftLine {
  key: number;
  account_id: string;
  description: string;
  debit: string;
  credit: string;
}

/** Exact decimal sum of 4dp strings using integer arithmetic (no float drift). */
function sum(values: string[]): bigint {
  return values.reduce((acc, v) => {
    const s = (v || '0').trim();
    const neg = s.startsWith('-');
    const [i = '0', f = ''] = s.replace('-', '').split('.');
    const n = BigInt(i || '0') * 10000n + BigInt((f + '0000').slice(0, 4) || '0');
    return acc + (neg ? -n : n);
  }, 0n);
}
const fmt4 = (n: bigint) => {
  const neg = n < 0n;
  const a = neg ? -n : n;
  return `${neg ? '-' : ''}${a / 10000n}.${(a % 10000n).toString().padStart(4, '0')}`;
};

let seq = 0;
const blank = (): DraftLine => ({ key: ++seq, account_id: '', description: '', debit: '', credit: '' });

function Editor({ entry }: { entry: JournalEntry | null }) {
  const router = useRouter();
  const currency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const acctList = accounts.useList({ per_page: 100, sort: 'code', 'filter[is_active]': 'true' });
  const create = journals.useCreate({ onSuccess: (j) => router.replace(`/finance/journals/${j.id}`) });
  const update = journals.useUpdate();
  const [date, setDate] = useState(entry?.entry_date ?? new Date().toISOString().slice(0, 10));
  const [reference, setReference] = useState(entry?.reference ?? '');
  const [memo, setMemo] = useState(entry?.memo ?? '');
  const [lines, setLines] = useState<DraftLine[]>(
    entry?.lines?.map((l) => ({ key: ++seq, account_id: String(l.account_id), description: l.description ?? '', debit: Number(l.debit) ? l.debit : '', credit: Number(l.credit) ? l.credit : '' })) ?? [blank(), blank()],
  );
  const [error, setError] = useState<string | null>(null);
  const [lineErrors, setLineErrors] = useState<Record<string, string>>({});

  const debit = sum(lines.map((l) => l.debit));
  const credit = sum(lines.map((l) => l.credit));
  const diff = debit - credit;
  const balanced = diff === 0n && debit > 0n;

  const setLine = (key: number, patch: Partial<DraftLine>) =>
    setLines((ls) => ls.map((l) => (l.key === key ? { ...l, ...patch } : l)));

  const save = async () => {
    setError(null);
    setLineErrors({});
    const payload = {
      entry_date: date,
      reference: reference || null,
      memo: memo || null,
      lines: lines
        .filter((l) => l.account_id || l.debit || l.credit)
        .map((l) => ({ account_id: Number(l.account_id), description: l.description || null, debit: l.debit || '0', credit: l.credit || '0' })),
    };
    try {
      if (entry) await update.mutateAsync({ id: entry.id, body: payload });
      else await create.mutateAsync(payload);
    } catch (e) {
      const err = e as ApiError;
      if (err.errors) {
        const le: Record<string, string> = {};
        for (const [k, v] of Object.entries(err.errors)) le[k] = v[0] ?? '';
        setLineErrors(le);
        setError(Object.values(le)[0] ?? err.message);
      } else setError(err.message);
    }
  };

  const options = (acctList.data?.data ?? []).map((a) => ({ value: String(a.id), label: `${a.code} · ${a.name}` }));

  return (
    <Card>
      <CardHeader title={entry ? `Edit draft ${entry.number}` : 'New journal entry'} description="Every entry must balance: total debits equal total credits." />
      <CardBody>
        {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
        <FormGrid columns={3}>
          <FormField label="Date" required error={lineErrors.entry_date}><DateInput value={date} onValueChange={(v) => setDate(v ?? '')} /></FormField>
          <FormField label="Reference" error={lineErrors.reference}><Input value={reference} onChange={(e) => setReference(e.target.value)} maxLength={100} /></FormField>
          <FormField label="Memo" error={lineErrors.memo}><Textarea rows={1} value={memo} onChange={(e) => setMemo(e.target.value)} /></FormField>
        </FormGrid>

        <div className={styles.tableScroll} style={{ marginTop: 20 }}>
          <table className={styles.lines} style={{ minWidth: 760 }}>
            <thead>
              <tr>
                <th style={{ width: '34%' }}>Account</th>
                <th>Description</th>
                <th className={styles.r} style={{ width: 150 }}>Debit</th>
                <th className={styles.r} style={{ width: 150 }}>Credit</th>
                <th style={{ width: 44 }} aria-label="Remove" />
              </tr>
            </thead>
            <tbody>
              {lines.map((l, i) => (
                <tr key={l.key}>
                  <td>
                    <Select aria-label={`Line ${i + 1} account`} value={l.account_id} placeholder="Select account" options={options} onChange={(e) => setLine(l.key, { account_id: e.target.value })} aria-invalid={!!lineErrors[`lines.${i}.account_id`]} />
                  </td>
                  <td><Input aria-label={`Line ${i + 1} description`} value={l.description} onChange={(e) => setLine(l.key, { description: e.target.value })} /></td>
                  <td><CurrencyInput aria-label={`Line ${i + 1} debit`} currency={currency} value={l.debit || null} onValueChange={(v) => setLine(l.key, { debit: v ?? '', credit: v ? '' : l.credit })} /></td>
                  <td><CurrencyInput aria-label={`Line ${i + 1} credit`} currency={currency} value={l.credit || null} onValueChange={(v) => setLine(l.key, { credit: v ?? '', debit: v ? '' : l.debit })} /></td>
                  <td>
                    <IconButton icon={<X size={16} />} aria-label={`Remove line ${i + 1}`} size="sm" disabled={lines.length <= 2} onClick={() => setLines((ls) => ls.filter((x) => x.key !== l.key))} />
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={2}>
                  <Button size="sm" variant="ghost" leftIcon={<Plus size={14} />} onClick={() => setLines((ls) => [...ls, blank()])}>Add line</Button>
                </td>
                <td className={styles.r}>{formatMoney(fmt4(debit), currency)}</td>
                <td className={styles.r}>{formatMoney(fmt4(credit), currency)}</td>
                <td />
              </tr>
            </tfoot>
          </table>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, marginTop: 16, flexWrap: 'wrap' }}>
          {balanced ? (
            <Badge variant="success"><CheckCircle2 size={12} /> Balanced</Badge>
          ) : (
            <Badge variant="warning">{debit === 0n ? 'Enter amounts' : `Out of balance by ${formatMoney(fmt4(diff < 0n ? -diff : diff), currency)}`}</Badge>
          )}
          <div style={{ display: 'flex', gap: 8 }}>
            <Button variant="secondary" onClick={() => router.back()}>Cancel</Button>
            <Button onClick={save} loading={create.isPending || update.isPending} disabled={!balanced}>Save draft</Button>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

export default function JournalEntryPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const can = useCan();
  const currency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const isNew = id === 'new';
  const q = journals.useOne(isNew ? undefined : id);
  const post = journals.useAction('post', { success: 'Entry posted to the ledger' });
  const reverse = journals.useAction<{ memo?: string }>('reverse', { success: 'Reversing entry posted', onSuccess: (r) => router.push(`/finance/journals/${(r as JournalEntry).id}`) });
  const del = journals.useDelete({ onSuccess: () => router.replace('/finance/journals') });
  const [confirm, setConfirm] = useState<'post' | 'reverse' | 'delete' | null>(null);
  const [editing, setEditing] = useState(false);

  const totals = useMemo(() => q.data && { d: q.data.total_debit, c: q.data.total_credit }, [q.data]);

  if (isNew) {
    if (!can('journals.create')) return <Alert variant="warning">You don’t have permission to create journal entries.</Alert>;
    return (
      <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        <PageHeader title="New journal entry" backHref="/finance/journals" backLabel="Journal entries" />
        <Editor entry={null} />
      </div>
    );
  }
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const j = q.data;

  if (editing && j.status === 'draft') {
    return (
      <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        <PageHeader title={`Edit ${j.number}`} backHref="/finance/journals" backLabel="Journal entries" />
        <Editor entry={j} />
      </div>
    );
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <PageHeader
        title={j.number}
        description={j.memo ?? undefined}
        backHref="/finance/journals"
        backLabel="Journal entries"
        meta={<StatusBadge status={j.status} />}
        actions={
          <>
            {j.status === 'draft' && can('journals.create') && <Button variant="secondary" onClick={() => setEditing(true)}>Edit</Button>}
            {j.status === 'draft' && can('journals.create') && <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirm('delete')}>Delete</Button>}
            {j.status === 'draft' && can('journals.post') && <Button leftIcon={<Send size={16} />} onClick={() => setConfirm('post')} disabled={!j.is_balanced}>Post</Button>}
            {j.status === 'posted' && can('journals.post') && !j.reverses_entry_id && <Button variant="secondary" leftIcon={<RotateCcw size={16} />} onClick={() => setConfirm('reverse')}>Reverse</Button>}
          </>
        }
      />

      {j.status === 'reversed' && j.reversed_by_entry_id && (
        <Alert variant="info">This entry was reversed. <a href={`/finance/journals/${j.reversed_by_entry_id}`}>View the reversing entry →</a></Alert>
      )}
      {j.reverses_entry_id && <Alert variant="info">This entry reverses <a href={`/finance/journals/${j.reverses_entry_id}`}>an earlier posting</a>.</Alert>}

      <div className={styles.detailGrid}>
        <Card>
          <CardHeader title="Lines" />
          <CardBody padding="none">
            <div className={styles.tableScroll}>
              <table className={styles.lines}>
                <thead><tr><th>Account</th><th>Description</th><th className={styles.r}>Debit</th><th className={styles.r}>Credit</th></tr></thead>
                <tbody>
                  {j.lines?.map((l) => (
                    <tr key={l.id}>
                      <td><span className={styles.mono}>{l.account?.code}</span> {l.account?.name}</td>
                      <td>{l.description ?? ''}</td>
                      <td className={styles.r}>{Number(l.debit) ? formatMoney(l.debit, currency) : ''}</td>
                      <td className={styles.r}>{Number(l.credit) ? formatMoney(l.credit, currency) : ''}</td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr><td colSpan={2}>Total</td><td className={styles.r}>{formatMoney(totals!.d, currency)}</td><td className={styles.r}>{formatMoney(totals!.c, currency)}</td></tr>
                </tfoot>
              </table>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardHeader title="Details" />
          <CardBody>
            <dl className={styles.dl}>
              <dt>Date</dt><dd>{formatDate(j.entry_date)}</dd>
              <dt>Period</dt><dd>{j.period?.name ?? '—'}</dd>
              <dt>Reference</dt><dd>{j.reference ?? '—'}</dd>
              <dt>Source</dt><dd>{humanize(j.source_type ?? 'manual')}{j.source_id ? ` #${j.source_id}` : ''}</dd>
              <dt>Created by</dt><dd>{j.creator?.name ?? '—'}</dd>
              <dt>Posted</dt><dd>{j.posted_at ? `${formatDateTime(j.posted_at)} by ${j.poster?.name ?? '—'}` : 'Not posted'}</dd>
            </dl>
          </CardBody>
        </Card>
      </div>

      <ConfirmDialog
        open={confirm === 'post'}
        onClose={() => setConfirm(null)}
        title={`Post ${j.number}?`}
        description="Posting writes to the general ledger. Posted entries cannot be edited — only reversed."
        confirmLabel="Post entry"
        onConfirm={async () => { await post.mutateAsync({ id: j.id }); setConfirm(null); }}
      />
      <ConfirmDialog
        open={confirm === 'reverse'}
        onClose={() => setConfirm(null)}
        title={`Reverse ${j.number}?`}
        description="A new entry with debits and credits swapped will be posted today, and this entry will be marked reversed."
        confirmLabel="Post reversal"
        onConfirm={async () => { await reverse.mutateAsync({ id: j.id, body: {} }); setConfirm(null); }}
      />
      <ConfirmDialog
        open={confirm === 'delete'}
        onClose={() => setConfirm(null)}
        title={`Delete draft ${j.number}?`}
        destructive
        confirmLabel="Delete draft"
        onConfirm={async () => { await del.mutateAsync(j.id); setConfirm(null); }}
      />
    </div>
  );
}
