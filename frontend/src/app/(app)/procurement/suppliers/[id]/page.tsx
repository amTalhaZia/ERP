'use client';

import { use, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { useMutation, useQuery } from '@tanstack/react-query';
import { CalendarClock, ClipboardList, Mail, PackageCheck, Pencil, Phone, Plus, ShieldCheck, Trash2, Wallet } from 'lucide-react';
import {
  Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, EmptyState, ErrorState, IconButton, MetricCard, PageHeader, SkeletonCard, StatusBadge, useToast,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime, formatMoney, humanize } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { procurementApi, purchaseOrders, suppliers, type SupplierContact } from '@/features/procurement/api';
import { SupplierForm } from '@/features/procurement/SupplierForm';
import { ContactForm, useInvalidateSuppliers } from '@/features/procurement/ContactForm';
import { DocStatus, Stars } from '@/features/procurement/components';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/procurement/procurement.module.scss';

export default function SupplierDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const can = useCan();
  const toast = useToast();
  const companyId = useAppSelector((s) => s.session.current_company?.id ?? 0);
  const q = suppliers.useOne(id);
  const summary = useQuery({ queryKey: [companyId, 'suppliers', 'summary', id], queryFn: () => procurementApi.supplierSummary(id) });
  const orders = purchaseOrders.useList({ 'filter[supplier_id]': id, per_page: 5, sort: '-order_date' }, { enabled: can('purchase_orders.view') });
  const approve = suppliers.useAction('approve', { success: 'Supplier approved' });
  const del = suppliers.useDelete({ onSuccess: () => router.replace('/procurement/suppliers') });
  const invalidate = useInvalidateSuppliers();
  const removeContact = useMutation<void, ApiError, SupplierContact>({
    mutationFn: (c) => procurementApi.deleteContact(Number(id), c.id),
    onSuccess: () => {
      void invalidate();
      toast.success('Contact removed');
    },
    onError: (e) => toast.error('Could not remove contact', e.message),
  });
  const [editing, setEditing] = useState(false);
  const [contact, setContact] = useState<SupplierContact | 'new' | null>(null);
  const [confirm, setConfirm] = useState<'delete' | 'approve' | SupplierContact | null>(null);
  const canManage = can('suppliers.manage');

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const s = q.data;
  const sum = summary.data;
  const cur = sum?.currency ?? s.currency;

  return (
    <div className={styles.stack}>
      <PageHeader
        title={s.name}
        description={[s.code, humanize(s.type), s.categories.join(', ')].filter(Boolean).join(' · ')}
        backHref="/procurement/suppliers"
        backLabel="Suppliers"
        meta={
          <>
            <StatusBadge status={s.is_active ? 'active' : 'inactive'} />
            {s.is_approved ? <Badge variant="success">Approved</Badge> : <Badge variant="warning">Not approved</Badge>}
          </>
        }
        actions={
          canManage && (
            <>
              <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirm('delete')}>Delete</Button>
              <Button variant="secondary" leftIcon={<Pencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>
              {!s.is_approved && <Button leftIcon={<ShieldCheck size={16} />} onClick={() => setConfirm('approve')}>Approve</Button>}
            </>
          )
        }
      />

      <div className={styles.metrics}>
        <MetricCard label="Purchase orders" icon={<ClipboardList size={18} />} loading={summary.isLoading} value={sum?.purchase_orders.total_count ?? 0} footer={sum ? `${sum.purchase_orders.committed_count} committed` : undefined} />
        <MetricCard label="Committed spend" icon={<Wallet size={18} />} loading={summary.isLoading} value={formatMoney(sum?.purchase_orders.committed_total ?? '0', cur)} footer="Approved, received or closed orders" />
        <MetricCard label="Goods receipts" icon={<PackageCheck size={18} />} loading={summary.isLoading} value={sum?.goods_receipts_count ?? 0} footer={sum?.last_received_date ? `Last ${formatDate(sum.last_received_date)}` : 'Nothing received yet'} />
        <MetricCard
          label="Avg. lead time"
          icon={<CalendarClock size={18} />}
          loading={summary.isLoading}
          unavailable={sum ? sum.avg_lead_time_days === null : false}
          unavailableText="No confirmed receipts"
          value={sum?.avg_lead_time_days != null ? `${sum.avg_lead_time_days} days` : '—'}
          footer={sum?.promised_lead_time_days != null ? `Promised ${sum.promised_lead_time_days} days` : 'No promised lead time'}
        />
      </div>
      {summary.error && <ErrorState size="sm" error={summary.error} onRetry={() => void summary.refetch()} />}

      <div className={crud.detailGrid}>
        <div className={styles.stack}>
          <Card>
            <CardHeader
              title="Contacts"
              description={`${s.contacts?.length ?? 0} on file`}
              actions={canManage && <Button size="sm" variant="secondary" leftIcon={<Plus size={14} />} onClick={() => setContact('new')}>Add contact</Button>}
            />
            <CardBody padding="none">
              {!s.contacts?.length ? (
                <EmptyState size="sm" title="No contacts yet" description="Add the people you deal with at this supplier." />
              ) : (
                <div className={crud.tableScroll}>
                  <table className={crud.lines}>
                    <thead>
                      <tr><th>Name</th><th>Email</th><th>Phone</th>{canManage && <th aria-label="Actions" />}</tr>
                    </thead>
                    <tbody>
                      {s.contacts.map((c) => (
                        <tr key={c.id}>
                          <td>
                            <div className={crud.cellMain}>
                              <strong>{c.name} {c.is_primary && <Badge size="sm" variant="primary">Primary</Badge>}</strong>
                              <span>{c.title ?? ''}</span>
                            </div>
                          </td>
                          <td>{c.email ? <a href={`mailto:${c.email}`}><Mail size={12} aria-hidden /> {c.email}</a> : '—'}</td>
                          <td>{c.phone ? <a href={`tel:${c.phone}`}><Phone size={12} aria-hidden /> {c.phone}</a> : '—'}</td>
                          {canManage && (
                            <td className={crud.r} style={{ whiteSpace: 'nowrap' }}>
                              <IconButton size="sm" icon={<Pencil size={14} />} aria-label={`Edit ${c.name}`} onClick={() => setContact(c)} />
                              <IconButton size="sm" icon={<Trash2 size={14} />} aria-label={`Remove ${c.name}`} onClick={() => setConfirm(c)} />
                            </td>
                          )}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardBody>
          </Card>

          {can('purchase_orders.view') && (
            <Card>
              <CardHeader
                title="Recent purchase orders"
                actions={<Button size="sm" variant="ghost" href={`/procurement/orders?f.supplier_id=${s.id}`}>View all</Button>}
              />
              <CardBody padding="none">
                {orders.isLoading ? (
                  <div style={{ padding: 16 }}><SkeletonCard /></div>
                ) : !orders.data?.data.length ? (
                  <EmptyState size="sm" title="No purchase orders yet" />
                ) : (
                  <div className={crud.tableScroll}>
                    <table className={crud.lines}>
                      <thead><tr><th>Number</th><th>Date</th><th>Status</th><th className={crud.r}>Total</th></tr></thead>
                      <tbody>
                        {orders.data.data.map((o) => (
                          <tr key={o.id}>
                            <td><Link href={`/procurement/orders/${o.id}`} className={crud.mono}>{o.number}</Link></td>
                            <td>{formatDate(o.order_date)}</td>
                            <td><DocStatus status={o.status} /></td>
                            <td className={crud.r}>{formatMoney(o.total, o.currency)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </CardBody>
            </Card>
          )}
        </div>

        <Card>
          <CardHeader title="Details" />
          <CardBody>
            <dl className={crud.dl}>
              <dt>Email</dt><dd>{s.email ?? '—'}</dd>
              <dt>Phone</dt><dd>{s.phone ?? '—'}</dd>
              <dt>Website</dt><dd>{s.website ? <a href={s.website} target="_blank" rel="noreferrer">{s.website}</a> : '—'}</dd>
              <dt>Tax number</dt><dd>{s.tax_number ?? '—'}</dd>
              <dt>Currency</dt><dd>{s.currency}</dd>
              <dt>Payment terms</dt><dd>{s.payment_terms_days} days</dd>
              <dt>Credit limit</dt><dd>{s.credit_limit ? formatMoney(s.credit_limit, s.currency) : '—'}</dd>
              <dt>Lead time</dt><dd>{s.lead_time_days != null ? `${s.lead_time_days} days` : '—'}</dd>
              <dt>Rating</dt><dd><Stars rating={s.rating} /></dd>
              <dt>Bank</dt><dd>{s.bank_name ?? '—'}{s.bank_account_last4 ? ` · •••• ${s.bank_account_last4}` : ''}</dd>
              <dt>Address</dt>
              <dd>
                {[s.address.line1, s.address.line2, [s.address.city, s.address.state, s.address.postal_code].filter(Boolean).join(' '), s.address.country].filter(Boolean).join(', ') || '—'}
              </dd>
              <dt>Coordinates</dt><dd>{s.latitude && s.longitude ? `${s.latitude}, ${s.longitude}` : '—'}</dd>
              <dt>Approval</dt>
              <dd>{s.is_approved ? `${s.approved_at ? formatDateTime(s.approved_at) : 'Approved'}${s.approver ? ` by ${s.approver.name}` : ''}` : 'Pending approval'}</dd>
              <dt>Last order</dt><dd>{formatDate(sum?.last_order_date)}</dd>
              <dt>Outstanding payable</dt><dd>{sum?.outstanding_payable ? formatMoney(sum.outstanding_payable, cur) : 'Available with Accounts payable'}</dd>
              {s.notes && (<><dt>Notes</dt><dd style={{ whiteSpace: 'pre-wrap' }}>{s.notes}</dd></>)}
            </dl>
          </CardBody>
        </Card>
      </div>

      <SupplierForm key={editing ? `edit-${s.updated_at ?? ''}` : 'closed'} open={editing} supplier={s} onClose={() => setEditing(false)} />
      <ContactForm key={contact === null ? 'closed' : contact === 'new' ? 'new' : contact.id} supplierId={s.id} open={contact !== null} contact={contact === 'new' ? null : contact} onClose={() => setContact(null)} />

      <ConfirmDialog
        open={confirm === 'approve'}
        onClose={() => setConfirm(null)}
        title={`Approve ${s.name}?`}
        description="Approved suppliers are cleared for purchasing. The approval is recorded in the audit log."
        confirmLabel="Approve supplier"
        onConfirm={async () => { await approve.mutateAsync({ id: s.id }); setConfirm(null); }}
      />
      <ConfirmDialog
        open={confirm === 'delete'}
        onClose={() => setConfirm(null)}
        title={`Delete ${s.name}?`}
        description="The supplier will be archived. Existing purchase orders keep their history."
        destructive
        confirmLabel="Delete supplier"
        onConfirm={async () => { await del.mutateAsync(s.id); setConfirm(null); }}
      />
      <ConfirmDialog
        open={typeof confirm === 'object' && confirm !== null}
        onClose={() => setConfirm(null)}
        title={`Remove ${typeof confirm === 'object' && confirm ? confirm.name : 'contact'}?`}
        destructive
        confirmLabel="Remove contact"
        onConfirm={async () => {
          if (typeof confirm === 'object' && confirm) await removeContact.mutateAsync(confirm);
          setConfirm(null);
        }}
      />
    </div>
  );
}
