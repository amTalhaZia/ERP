'use client';

import { use, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { Boxes, Layers, Pencil, Plus, Trash2 } from 'lucide-react';
import {
  Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, EmptyState, ErrorState, IconButton, PageHeader, Skeleton, SkeletonCard, StatusBadge,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDateTime, humanize } from '@/lib/format';
import { locations, stockApi, warehouses, type StorageLocation } from '@/features/inventory/api';
import { WarehouseForm } from '@/features/inventory/WarehouseForm';
import { LocationForm } from '@/features/inventory/LocationForm';
import { useCompanyId } from '@/features/inventory/shared';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/inventory/inventory.module.scss';

function LocationsCard({ warehouseId, canManage }: { warehouseId: number; canManage: boolean }) {
  const companyId = useCompanyId();
  const q = useQuery({
    queryKey: [companyId, 'locations', 'warehouse', warehouseId, 'all'],
    queryFn: () => stockApi.warehouseLocations(warehouseId, { per_page: 100, sort: 'code' }),
  });
  const del = locations.useDelete();
  const [editing, setEditing] = useState<StorageLocation | 'new' | null>(null);
  const [deleting, setDeleting] = useState<StorageLocation | null>(null);
  const rows = q.data?.data ?? [];
  const total = q.data?.meta?.pagination?.total ?? 0;

  return (
    <Card>
      <CardHeader
        title="Storage locations"
        description={total > rows.length ? `Showing ${rows.length} of ${total}` : 'Zones, aisles, racks and bins inside this warehouse.'}
        actions={canManage && <Button size="sm" leftIcon={<Plus size={14} />} onClick={() => setEditing('new')}>Add location</Button>}
      />
      <CardBody padding="none">
        {q.error ? (
          <ErrorState error={q.error} onRetry={() => void q.refetch()} size="sm" />
        ) : q.isLoading ? (
          <div style={{ padding: 16 }}><Skeleton variant="text" lines={4} /></div>
        ) : rows.length === 0 ? (
          <EmptyState
            size="sm"
            icon={<Layers size={20} />}
            title="No storage locations"
            description="Stock is tracked at warehouse level until you add locations."
            action={canManage ? <Button size="sm" leftIcon={<Plus size={14} />} onClick={() => setEditing('new')}>Add location</Button> : undefined}
          />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines} style={{ minWidth: 520 }}>
              <thead>
                <tr><th>Code</th><th>Name</th><th>Type</th><th>Parent</th><th>Status</th>{canManage && <th aria-label="Actions" style={{ width: 88 }} />}</tr>
              </thead>
              <tbody>
                {rows.map((l) => (
                  <tr key={l.id}>
                    <td><span className={crud.mono}>{l.code}</span></td>
                    <td>{l.name}</td>
                    <td>{humanize(l.type)}</td>
                    <td>{l.parent ? <span className={crud.mono}>{l.parent.code}</span> : <span className={crud.muted}>—</span>}</td>
                    <td><StatusBadge status={l.is_active ? 'active' : 'inactive'} /></td>
                    {canManage && (
                      <td>
                        <span className={styles.inline}>
                          <IconButton size="sm" icon={<Pencil size={14} />} aria-label={`Edit ${l.code}`} onClick={() => setEditing(l)} />
                          <IconButton size="sm" icon={<Trash2 size={14} />} aria-label={`Delete ${l.code}`} onClick={() => setDeleting(l)} />
                        </span>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>

      {canManage && (
        <LocationForm
          key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id}
          open={editing !== null}
          warehouseId={warehouseId}
          location={editing === 'new' ? null : editing}
          siblings={rows}
          onClose={() => setEditing(null)}
        />
      )}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete location ${deleting?.code}?`}
        description="Locations that hold stock or have child locations cannot be deleted."
        destructive
        confirmLabel="Delete location"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </Card>
  );
}

export default function WarehousePage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const can = useCan();
  const q = warehouses.useOne(id);
  const del = warehouses.useDelete({ onSuccess: () => router.replace('/inventory/warehouses') });
  const [editing, setEditing] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const canManage = can('warehouses.manage');

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const w = q.data;
  const address = [w.address.line1, w.address.line2, w.address.city, w.address.state, w.address.postal_code, w.address.country].filter(Boolean).join(', ');

  return (
    <div className={styles.stack}>
      <PageHeader
        title={w.name}
        description={<span className={crud.mono}>{w.code}</span>}
        backHref="/inventory/warehouses"
        backLabel="Warehouses"
        meta={
          <span className={styles.inline}>
            <StatusBadge status={w.is_active ? 'active' : 'inactive'} />
            {w.is_default && <Badge variant="primary">Default</Badge>}
          </span>
        }
        actions={
          <>
            {can('inventory.view') && <Button variant="secondary" leftIcon={<Boxes size={16} />} href={`/inventory/stock?f.warehouse_id=${w.id}`}>View stock</Button>}
            {canManage && <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setDeleting(true)}>Delete</Button>}
            {canManage && <Button leftIcon={<Pencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>}
          </>
        }
      />

      <div className={crud.detailGrid}>
        <LocationsCard warehouseId={w.id} canManage={canManage} />
        <Card>
          <CardHeader title="Details" />
          <CardBody>
            <dl className={crud.dl}>
              <dt>Branch</dt><dd>{w.branch ? <Link href="/company/branches">{w.branch.name}</Link> : 'Company-wide'}</dd>
              <dt>Address</dt><dd>{address || '—'}</dd>
              <dt>Coordinates</dt><dd>{w.latitude && w.longitude ? <Link href="/company/map">{`${w.latitude}, ${w.longitude}`}</Link> : '—'}</dd>
              <dt>Locations</dt><dd>{w.locations_count ?? 0}</dd>
              <dt>Updated</dt><dd>{formatDateTime(w.updated_at)}</dd>
            </dl>
          </CardBody>
        </Card>
      </div>

      {canManage && <WarehouseForm key={editing ? 'open' : 'closed'} open={editing} warehouse={w} onClose={() => setEditing(false)} />}
      <ConfirmDialog
        open={deleting}
        onClose={() => setDeleting(false)}
        title={`Delete ${w.name}?`}
        description="Warehouses that still hold stock cannot be deleted — transfer the stock out first."
        destructive
        confirmLabel="Delete warehouse"
        onConfirm={async () => {
          await del.mutateAsync(w.id).catch(() => undefined);
          setDeleting(false);
        }}
      />
    </div>
  );
}
