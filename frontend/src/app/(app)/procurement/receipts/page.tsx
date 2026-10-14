'use client';

import { Suspense, useState } from 'react';
import { useRouter } from 'next/navigation';
import { PackagePlus } from 'lucide-react';
import { Button, DateInput, FormField, Modal, Select, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate } from '@/lib/format';
import { GR_STATUSES, goodsReceipts, purchaseOrders, type GoodsReceipt } from '@/features/procurement/api';
import { DocStatus } from '@/features/procurement/components';
import styles from '@/components/crud/crud.module.scss';

function PickOrderModal({ onClose }: { onClose: () => void }) {
  const router = useRouter();
  const orders = purchaseOrders.useList({ 'filter[status]': 'approved,partially_received', per_page: 100, sort: '-order_date' });
  const [po, setPo] = useState('');
  return (
    <Modal
      open
      onClose={onClose}
      size="sm"
      title="Receive goods"
      description="Goods are always received against an approved purchase order."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button disabled={!po} onClick={() => router.push(`/procurement/receipts/new?po=${po}`)}>Continue</Button>
        </>
      }
    >
      <FormField label="Purchase order" required hint={orders.data && orders.data.data.length === 0 ? 'No approved orders are awaiting delivery.' : undefined}>
        <Select
          value={po}
          placeholder={orders.isLoading ? 'Loading…' : 'Select an order'}
          onChange={(e) => setPo(e.target.value)}
          options={(orders.data?.data ?? []).map((o) => ({ value: String(o.id), label: `${o.number} — ${o.supplier?.name ?? ''} (${formatDate(o.order_date)})` }))}
        />
      </FormField>
    </Modal>
  );
}

function ReceiptsInner() {
  const router = useRouter();
  const can = useCan();
  const list = useListState({ sort: '-received_date' });
  const query = goodsReceipts.useList(list.apiParams);
  const [picking, setPicking] = useState(false);
  const canManage = can('goods_receipts.manage');

  const columns: DataTableColumn<GoodsReceipt>[] = [
    { id: 'number', accessorKey: 'number', header: 'Number', enableSorting: true, cell: ({ row }) => <span className={styles.mono}>{row.original.number}</span> },
    { id: 'po', header: 'Purchase order', cell: ({ row }) => <span className={styles.mono}>{row.original.purchase_order?.number ?? '—'}</span> },
    { id: 'received_date', accessorKey: 'received_date', header: 'Received', enableSorting: true, cell: ({ row }) => formatDate(row.original.received_date) },
    { id: 'receiver', header: 'Received by', cell: ({ row }) => row.original.receiver?.name ?? '—' },
    { id: 'note', header: 'Delivery note', cell: ({ row }) => row.original.supplier_delivery_note ?? <span className={styles.muted}>—</span> },
    { id: 'lines', header: 'Lines', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.lines_count ?? 0 },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <DocStatus status={row.original.status} /> },
  ];

  return (
    <>
      <ListPage<GoodsReceipt>
        title="Goods receipts"
        description="Deliveries checked in against purchase orders. Confirming a receipt updates received quantities and stock."
        actions={canManage && <Button leftIcon={<PackagePlus size={16} />} onClick={() => setPicking(true)}>Receive goods</Button>}
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search number or delivery note"
        filters={
          <>
            <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={GR_STATUSES} />
            <DateInput aria-label="Received from" value={list.state.from ?? ''} onValueChange={(v) => list.update({ from: v ?? undefined })} />
            <DateInput aria-label="Received to" value={list.state.to ?? ''} onValueChange={(v) => list.update({ to: v ?? undefined })} />
          </>
        }
        emptyTitle="No goods received yet"
        emptyDescription="Receive deliveries from an approved purchase order."
        emptyAction={canManage ? <Button leftIcon={<PackagePlus size={16} />} onClick={() => setPicking(true)}>Receive goods</Button> : undefined}
        onRowClick={(g) => router.push(`/procurement/receipts/${g.id}`)}
      />
      {picking && <PickOrderModal onClose={() => setPicking(false)} />}
    </>
  );
}

export default function ReceiptsPage() {
  return (
    <Suspense>
      <ReceiptsInner />
    </Suspense>
  );
}
