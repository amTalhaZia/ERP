'use client';

import { use, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { CalendarDays, FileText, Pencil, Plus, Receipt, ShoppingCart, Target, Trash2, Wallet } from 'lucide-react';
import {
  Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, DataTable, EmptyState, ErrorState, MetricCard, PageHeader, SkeletonCard, StatusBadge, Tab, TabList, TabPanel, Tabs,
  type DataTableColumn,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney, humanize } from '@/lib/format';
import { useAppSelector } from '@/store';
import { customers, quotations, salesApi, salesOrders, type Customer, type PostalAddress, type Quotation, type SalesOrder } from '@/features/sales/api';
import { CustomerForm } from '@/features/sales/CustomerForm';
import { CommunicationsPanel, ContactsPanel } from '@/features/sales/CustomerPanels';
import { SalesStatus } from '@/features/sales/shared';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/sales/sales.module.scss';

const addressLines = (a: PostalAddress) =>
  [a.line1, a.line2, [a.city, a.state, a.postal_code].filter(Boolean).join(', '), a.country].filter(Boolean) as string[];

function Overview({ customer }: { customer: Customer }) {
  const companyId = useAppSelector((s) => s.session.current_company?.id ?? 0);
  const q = useQuery({ queryKey: [companyId, 'customers', customer.id, 'summary'], queryFn: () => salesApi.customerSummary(customer.id) });
  const s = q.data;
  const money = (v: string | null | undefined) => formatMoney(v, customer.currency);
  const openOpps = s?.opportunities.by_status.open;

  return (
    <div className={styles.stack}>
      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} size="sm" />
      ) : (
        <section className={styles.metrics} aria-label="Customer summary">
          <MetricCard label="Quotations" icon={<FileText size={18} />} loading={q.isLoading} value={money(s?.quotations.total)} footer={s ? `${s.quotations.count} quotation${s.quotations.count === 1 ? '' : 's'}` : undefined} />
          <MetricCard label="Sales orders" icon={<ShoppingCart size={18} />} loading={q.isLoading} value={money(s?.sales_orders.total)} footer={s ? `${s.sales_orders.count} order${s.sales_orders.count === 1 ? '' : 's'}` : undefined} />
          <MetricCard label="Open opportunities" icon={<Target size={18} />} loading={q.isLoading} value={money(openOpps?.total ?? '0')} footer={`${openOpps?.count ?? 0} open`} />
          <MetricCard label="Last order" icon={<CalendarDays size={18} />} loading={q.isLoading} value={s?.last_order_date ? formatDate(s.last_order_date) : 'None yet'} />
          <MetricCard
            label="AR balance"
            icon={<Wallet size={18} />}
            loading={q.isLoading}
            value={money(s?.outstanding_balance)}
            unavailable={!q.isLoading && (s?.outstanding_balance ?? null) === null}
            unavailableText="Pending"
            footer={s && s.outstanding_balance === null ? (s.outstanding_balance_note ?? 'Provided by Accounts Receivable; not yet available.') : undefined}
          />
        </section>
      )}

      <div className={crud.detailGrid}>
        <Card>
          <CardHeader title="Details" />
          <CardBody>
            <dl className={crud.dl}>
              <dt>Type</dt><dd>{humanize(customer.type)}</dd>
              <dt>Email</dt><dd>{customer.email ? <a href={`mailto:${customer.email}`}>{customer.email}</a> : '—'}</dd>
              <dt>Phone</dt><dd>{customer.phone ?? '—'}</dd>
              <dt>Website</dt><dd>{customer.website ?? '—'}</dd>
              <dt>Tax number</dt><dd>{customer.tax_number ?? '—'}</dd>
              <dt>Currency</dt><dd>{customer.currency}</dd>
              <dt>Payment terms</dt><dd>{customer.payment_terms_days} days</dd>
              <dt>Credit limit</dt><dd>{customer.credit_limit === null ? 'No limit' : money(customer.credit_limit)}</dd>
              <dt>Sales rep</dt><dd>{customer.sales_rep?.name ?? 'Unassigned'}</dd>
              <dt>Source</dt><dd>{customer.source ?? '—'}</dd>
              <dt>Tags</dt>
              <dd>{customer.tags.length ? customer.tags.map((t) => <span key={t} className={styles.tag}><Badge size="sm">{t}</Badge></span>) : '—'}</dd>
              {customer.notes && (<><dt>Notes</dt><dd style={{ whiteSpace: 'pre-wrap' }}>{customer.notes}</dd></>)}
            </dl>
          </CardBody>
        </Card>
        <Card>
          <CardHeader title="Addresses" />
          <CardBody>
            <dl className={crud.dl}>
              <dt>Billing</dt>
              <dd>{addressLines(customer.billing_address).map((l) => <div key={l}>{l}</div>)}{addressLines(customer.billing_address).length === 0 && '—'}</dd>
              <dt>Shipping</dt>
              <dd>{addressLines(customer.shipping_address).map((l) => <div key={l}>{l}</div>)}{addressLines(customer.shipping_address).length === 0 && '—'}</dd>
              <dt>Coordinates</dt>
              <dd>{customer.latitude && customer.longitude ? `${Number(customer.latitude).toFixed(5)}, ${Number(customer.longitude).toFixed(5)}` : 'Not set'}</dd>
            </dl>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

function CustomerQuotations({ customer }: { customer: Customer }) {
  const router = useRouter();
  const can = useCan();
  const q = quotations.useList({ 'filter[customer_id]': customer.id, per_page: 25, sort: '-date' });
  const columns: DataTableColumn<Quotation>[] = [
    { id: 'number', header: 'Number', cell: ({ row }) => <span className={crud.mono}>{row.original.number}</span> },
    { id: 'date', header: 'Date', cell: ({ row }) => formatDate(row.original.date) },
    { id: 'valid_until', header: 'Valid until', cell: ({ row }) => formatDate(row.original.valid_until) },
    { id: 'status', header: 'Status', cell: ({ row }) => <SalesStatus status={row.original.status} /> },
    { id: 'total', header: 'Total', meta: { numeric: true }, cell: ({ row }) => formatMoney(row.original.total, row.original.currency) },
  ];
  return (
    <Card>
      <CardHeader
        title="Quotations"
        actions={can('quotations.manage') ? <Button size="sm" leftIcon={<Plus size={14} />} href={`/sales/quotations/new?customer_id=${customer.id}`}>New quotation</Button> : undefined}
      />
      <CardBody padding="none">
        <DataTable<Quotation>
          aria-label="Quotations for this customer"
          columns={columns}
          data={q.data?.data ?? []}
          isLoading={q.isLoading}
          error={q.error}
          onRetry={() => void q.refetch()}
          onRowClick={(r) => router.push(`/sales/quotations/${r.id}`)}
          emptyState={<EmptyState size="sm" icon={<FileText size={20} />} title="No quotations for this customer" />}
        />
        {(q.data?.meta.pagination.total ?? 0) > 25 && (
          <p style={{ padding: 16 }}><Link href={`/sales/quotations?f.customer_id=${customer.id}`}>View all {q.data?.meta.pagination.total} quotations →</Link></p>
        )}
      </CardBody>
    </Card>
  );
}

function CustomerOrders({ customer }: { customer: Customer }) {
  const router = useRouter();
  const can = useCan();
  const q = salesOrders.useList({ 'filter[customer_id]': customer.id, per_page: 25, sort: '-order_date' });
  const columns: DataTableColumn<SalesOrder>[] = [
    { id: 'number', header: 'Number', cell: ({ row }) => <span className={crud.mono}>{row.original.number}</span> },
    { id: 'order_date', header: 'Order date', cell: ({ row }) => formatDate(row.original.order_date) },
    { id: 'expected', header: 'Expected delivery', cell: ({ row }) => formatDate(row.original.expected_delivery_date) },
    { id: 'status', header: 'Status', cell: ({ row }) => <SalesStatus status={row.original.status} /> },
    { id: 'total', header: 'Total', meta: { numeric: true }, cell: ({ row }) => formatMoney(row.original.total, row.original.currency) },
  ];
  return (
    <Card>
      <CardHeader
        title="Sales orders"
        actions={can('sales_orders.manage') ? <Button size="sm" leftIcon={<Plus size={14} />} href={`/sales/orders/new?customer_id=${customer.id}`}>New order</Button> : undefined}
      />
      <CardBody padding="none">
        <DataTable<SalesOrder>
          aria-label="Sales orders for this customer"
          columns={columns}
          data={q.data?.data ?? []}
          isLoading={q.isLoading}
          error={q.error}
          onRetry={() => void q.refetch()}
          onRowClick={(r) => router.push(`/sales/orders/${r.id}`)}
          emptyState={<EmptyState size="sm" icon={<Receipt size={20} />} title="No sales orders for this customer" />}
        />
        {(q.data?.meta.pagination.total ?? 0) > 25 && (
          <p style={{ padding: 16 }}><Link href={`/sales/orders?f.customer_id=${customer.id}`}>View all {q.data?.meta.pagination.total} orders →</Link></p>
        )}
      </CardBody>
    </Card>
  );
}

export default function CustomerDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const can = useCan();
  const q = customers.useOne(id);
  const del = customers.useDelete({ onSuccess: () => router.replace('/sales/customers') });
  const [editing, setEditing] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [tab, setTab] = useState('overview');
  const canManage = can('customers.manage');

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard lines={6} />;
  const c = q.data;

  return (
    <div className={styles.stack}>
      <PageHeader
        title={c.name}
        description={[c.code, c.email, [c.billing_address.city, c.billing_address.country].filter(Boolean).join(', ')].filter(Boolean).join(' · ')}
        backHref="/sales/customers"
        backLabel="Customers"
        meta={<StatusBadge status={c.is_active ? 'active' : 'inactive'} />}
        actions={
          canManage ? (
            <>
              <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setDeleting(true)}>Delete</Button>
              <Button variant="secondary" leftIcon={<Pencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>
            </>
          ) : undefined
        }
      />

      <Tabs value={tab} onValueChange={setTab}>
        <TabList aria-label="Customer sections">
          <Tab value="overview">Overview</Tab>
          <Tab value="contacts" count={c.contacts?.length ?? 0}>Contacts</Tab>
          <Tab value="communications">Communications</Tab>
          <Tab value="quotations">Quotations</Tab>
          <Tab value="orders">Orders</Tab>
        </TabList>
        <TabPanel value="overview"><Overview customer={c} /></TabPanel>
        <TabPanel value="contacts"><ContactsPanel customerId={c.id} contacts={c.contacts ?? []} canManage={canManage} /></TabPanel>
        <TabPanel value="communications"><CommunicationsPanel customerId={c.id} canManage={canManage} /></TabPanel>
        <TabPanel value="quotations"><CustomerQuotations customer={c} /></TabPanel>
        <TabPanel value="orders"><CustomerOrders customer={c} /></TabPanel>
      </Tabs>

      <CustomerForm key={editing ? `edit-${c.id}-${c.updated_at}` : 'closed'} open={editing} customer={c} onClose={() => setEditing(false)} />
      <ConfirmDialog
        open={deleting}
        onClose={() => setDeleting(false)}
        title={`Delete ${c.name}?`}
        description="The customer will be archived. Issued quotations and orders keep their history."
        destructive
        confirmLabel="Delete customer"
        onConfirm={async () => {
          await del.mutateAsync(c.id);
          setDeleting(false);
        }}
      />
    </div>
  );
}
