'use client';

import { useMemo, useState } from 'react';
import dynamic from 'next/dynamic';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { List, Map as MapIcon } from 'lucide-react';
import { Alert, Card, CardBody, Checkbox, EmptyState, PageHeader, SegmentedControl, Skeleton } from '@/components/ui';
import { get } from '@/lib/api/client';
import { useCan } from '@/lib/auth/useCan';
import { useAppSelector } from '@/store';
import { branches } from '@/features/company/api';
import type { MapPoint } from '@/components/maps/LocationsMap';
import styles from '@/components/crud/crud.module.scss';

const LocationsMap = dynamic(() => import('@/components/maps/LocationsMap'), {
  ssr: false,
  loading: () => <Skeleton style={{ height: 520 }} />,
});

const COLORS = { branch: '#4f46e5', warehouse: '#0d9488', customer: '#d97706', supplier: '#0284c7' } as const;

interface GeoRow {
  id: number;
  name: string;
  code?: string;
  city?: string | null;
  latitude: string | number | null;
  longitude: string | number | null;
  address?: { city: string | null; country: string | null };
}

function toPoints(rows: GeoRow[] | undefined, kind: MapPoint['kind'], base: string): MapPoint[] {
  return (rows ?? [])
    .filter((r) => r.latitude !== null && r.longitude !== null && r.latitude !== '' && r.longitude !== '')
    .map((r) => ({
      id: `${kind}-${r.id}`,
      lat: Number(r.latitude),
      lng: Number(r.longitude),
      title: r.name,
      subtitle: [r.code, r.address?.city ?? r.city].filter(Boolean).join(' · '),
      kind,
      href: `${base}/${r.id}`,
    }));
}

export default function LocationsMapPage() {
  const can = useCan();
  const companyId = useAppSelector((s) => s.session.current_company?.id);
  const [view, setView] = useState<'map' | 'list'>('map');
  const [layers, setLayers] = useState({ branch: true, warehouse: true, customer: false, supplier: false });

  const b = branches.useAll('all');
  const w = useQuery({ queryKey: [companyId, 'warehouses', 'map'], queryFn: () => get<{ data: GeoRow[] }>('/warehouses', { per_page: 100 }).then((r) => r.data), enabled: can('warehouses.view') });
  const c = useQuery({ queryKey: [companyId, 'customers', 'map'], queryFn: () => get<{ data: GeoRow[] }>('/customers/map').then((r) => r.data), enabled: can('customers.view') && layers.customer });
  const s = useQuery({ queryKey: [companyId, 'suppliers', 'map'], queryFn: () => get<{ data: GeoRow[] }>('/suppliers/map').then((r) => r.data), enabled: can('suppliers.view') && layers.supplier });

  const points = useMemo(
    () => [
      ...(layers.branch ? toPoints(b.data as GeoRow[] | undefined, 'branch', '/company/branches') : []),
      ...(layers.warehouse ? toPoints(w.data, 'warehouse', '/inventory/warehouses') : []),
      ...(layers.customer ? toPoints(c.data, 'customer', '/sales/customers') : []),
      ...(layers.supplier ? toPoints(s.data, 'supplier', '/procurement/suppliers') : []),
    ],
    [layers, b.data, w.data, c.data, s.data],
  );

  const totalBranches = (b.data ?? []).length;
  const missing = totalBranches - toPoints(b.data as GeoRow[] | undefined, 'branch', '').length;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <PageHeader
        title="Locations map"
        description="Branches, warehouses, customers and suppliers with saved coordinates."
        actions={
          <SegmentedControl
            aria-label="View"
            value={view}
            onChange={setView}
            size="sm"
            options={[
              { value: 'map', label: 'Map', icon: <MapIcon size={14} /> },
              { value: 'list', label: 'List', icon: <List size={14} /> },
            ]}
          />
        }
      />

      <Card>
        <CardBody>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 20 }}>
            {(
              [
                ['branch', 'Branches', true],
                ['warehouse', 'Warehouses', can('warehouses.view')],
                ['customer', 'Customers', can('customers.view')],
                ['supplier', 'Suppliers', can('suppliers.view')],
              ] as const
            )
              .filter(([, , allowed]) => allowed)
              .map(([key, label]) => (
                <Checkbox
                  key={key}
                  checked={layers[key]}
                  onChange={(e) => setLayers((l) => ({ ...l, [key]: e.target.checked }))}
                  label={
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                      <span style={{ width: 10, height: 10, borderRadius: 3, background: COLORS[key] }} aria-hidden />
                      {label}
                    </span>
                  }
                />
              ))}
          </div>
        </CardBody>
      </Card>

      {missing > 0 && (
        <Alert variant="info">
          {missing} branch{missing === 1 ? '' : 'es'} {missing === 1 ? 'has' : 'have'} no coordinates and {missing === 1 ? 'is' : 'are'} not shown. Add latitude/longitude on the{' '}
          <Link href="/company/branches">branch</Link> to place {missing === 1 ? 'it' : 'them'} on the map. Positions are the saved addresses, not live tracking.
        </Alert>
      )}

      {view === 'map' ? (
        <Card>
          <CardBody padding="sm">
            {b.isLoading ? <Skeleton style={{ height: 520 }} /> : <LocationsMap points={points} />}
          </CardBody>
        </Card>
      ) : points.length === 0 ? (
        <EmptyState title="No locations with coordinates" description="Add latitude and longitude to branches, warehouses, customers or suppliers." />
      ) : (
        <Card>
          <CardBody padding="none">
            <div className={styles.tableScroll}>
              <table className={styles.lines}>
                <thead>
                  <tr><th>Name</th><th>Type</th><th>Details</th><th className={styles.r}>Latitude</th><th className={styles.r}>Longitude</th></tr>
                </thead>
                <tbody>
                  {points.map((p) => (
                    <tr key={p.id}>
                      <td>{p.href ? <Link href={p.href}>{p.title}</Link> : p.title}</td>
                      <td style={{ textTransform: 'capitalize' }}>{p.kind}</td>
                      <td>{p.subtitle}</td>
                      <td className={styles.r}>{p.lat.toFixed(5)}</td>
                      <td className={styles.r}>{p.lng.toFixed(5)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
