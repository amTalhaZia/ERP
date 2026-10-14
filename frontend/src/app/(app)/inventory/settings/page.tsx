'use client';

import { useState } from 'react';
import { FolderTree, Pencil, Plus, Ruler, Trash2 } from 'lucide-react';
import { Button, Card, CardBody, CardHeader, ConfirmDialog, EmptyState, ErrorState, IconButton, PageHeader, Skeleton, StatusBadge } from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { categories, units, type CategoryNode, type ProductCategory, type UnitOfMeasure } from '@/features/inventory/api';
import { CategoryForm, UomForm } from '@/features/inventory/CatalogForms';
import { useCategoryTree } from '@/features/inventory/ProductForm';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/inventory/inventory.module.scss';

/** Depth-first order from the tree so children render under their parent. */
function treeOrder(tree: CategoryNode[] | undefined) {
  const order = new Map<number, { index: number; depth: number }>();
  let i = 0;
  const walk = (nodes: CategoryNode[], depth: number) => {
    for (const n of nodes) {
      order.set(n.id, { index: i++, depth });
      walk(n.children, depth + 1);
    }
  };
  walk(tree ?? [], 0);
  return order;
}

function CategoriesCard({ canManage }: { canManage: boolean }) {
  const q = categories.useList({ per_page: 100, sort: 'name' });
  const tree = useCategoryTree();
  const del = categories.useDelete();
  const [editing, setEditing] = useState<ProductCategory | 'new' | null>(null);
  const [deleting, setDeleting] = useState<ProductCategory | null>(null);

  const order = treeOrder(tree.data);
  // Inactive categories are absent from the tree: append them at the end, unindented.
  const rows = [...(q.data?.data ?? [])].sort((a, b) => (order.get(a.id)?.index ?? 1e9) - (order.get(b.id)?.index ?? 1e9));
  const total = q.data?.meta?.pagination?.total ?? 0;

  return (
    <Card>
      <CardHeader
        title="Product categories"
        description={total > rows.length ? `Showing ${rows.length} of ${total}` : 'Group products for filtering and reporting.'}
        actions={canManage && <Button size="sm" leftIcon={<Plus size={14} />} onClick={() => setEditing('new')}>Add category</Button>}
      />
      <CardBody padding="none">
        {q.error ? (
          <ErrorState error={q.error} onRetry={() => void q.refetch()} size="sm" />
        ) : q.isLoading ? (
          <div style={{ padding: 16 }}><Skeleton variant="text" lines={5} /></div>
        ) : rows.length === 0 ? (
          <EmptyState size="sm" icon={<FolderTree size={20} />} title="No categories" description="Create categories such as Raw materials or Finished goods." />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines} style={{ minWidth: 460 }}>
              <thead><tr><th>Name</th><th>Code</th><th className={crud.r}>Products</th><th>Status</th>{canManage && <th aria-label="Actions" style={{ width: 88 }} />}</tr></thead>
              <tbody>
                {rows.map((c) => {
                  const depth = order.get(c.id)?.depth ?? 0;
                  return (
                    <tr key={c.id}>
                      <td style={{ paddingLeft: 12 + depth * 18 }}>{depth > 0 && <span className={crud.muted}>└ </span>}{c.name}</td>
                      <td><span className={crud.mono}>{c.code}</span></td>
                      <td className={crud.r}>{c.products_count ?? 0}</td>
                      <td><StatusBadge status={c.is_active ? 'active' : 'inactive'} /></td>
                      {canManage && (
                        <td>
                          <span className={styles.inline}>
                            <IconButton size="sm" icon={<Pencil size={14} />} aria-label={`Edit ${c.name}`} onClick={() => setEditing(c)} />
                            <IconButton size="sm" icon={<Trash2 size={14} />} aria-label={`Delete ${c.name}`} disabled={(c.children_count ?? 0) > 0 || (c.products_count ?? 0) > 0} onClick={() => setDeleting(c)} />
                          </span>
                        </td>
                      )}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>
      {canManage && <CategoryForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} open={editing !== null} category={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="Categories with sub-categories or products cannot be deleted."
        destructive
        confirmLabel="Delete category"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </Card>
  );
}

function UnitsCard({ canManage }: { canManage: boolean }) {
  const q = units.useList({ per_page: 100, sort: 'name' });
  const del = units.useDelete();
  const [editing, setEditing] = useState<UnitOfMeasure | 'new' | null>(null);
  const [deleting, setDeleting] = useState<UnitOfMeasure | null>(null);
  const rows = q.data?.data ?? [];

  return (
    <Card>
      <CardHeader
        title="Units of measure"
        description="How quantities are counted: pieces, kilograms, metres…"
        actions={canManage && <Button size="sm" leftIcon={<Plus size={14} />} onClick={() => setEditing('new')}>Add unit</Button>}
      />
      <CardBody padding="none">
        {q.error ? (
          <ErrorState error={q.error} onRetry={() => void q.refetch()} size="sm" />
        ) : q.isLoading ? (
          <div style={{ padding: 16 }}><Skeleton variant="text" lines={5} /></div>
        ) : rows.length === 0 ? (
          <EmptyState size="sm" icon={<Ruler size={20} />} title="No units" description="Add units such as pcs, kg or m." />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines} style={{ minWidth: 460 }}>
              <thead><tr><th>Name</th><th>Symbol</th><th className={crud.r}>Decimals</th><th className={crud.r}>Products</th><th>Status</th>{canManage && <th aria-label="Actions" style={{ width: 88 }} />}</tr></thead>
              <tbody>
                {rows.map((u) => (
                  <tr key={u.id}>
                    <td>{u.name}</td>
                    <td><span className={crud.mono}>{u.symbol}</span></td>
                    <td className={crud.r}>{u.precision}</td>
                    <td className={crud.r}>{u.products_count ?? 0}</td>
                    <td><StatusBadge status={u.is_active ? 'active' : 'inactive'} /></td>
                    {canManage && (
                      <td>
                        <span className={styles.inline}>
                          <IconButton size="sm" icon={<Pencil size={14} />} aria-label={`Edit ${u.name}`} onClick={() => setEditing(u)} />
                          <IconButton size="sm" icon={<Trash2 size={14} />} aria-label={`Delete ${u.name}`} disabled={(u.products_count ?? 0) > 0} onClick={() => setDeleting(u)} />
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
      {canManage && <UomForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} open={editing !== null} uom={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="Units used by products cannot be deleted — deactivate them instead."
        destructive
        confirmLabel="Delete unit"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </Card>
  );
}

export default function InventorySettingsPage() {
  const can = useCan();
  const canManage = can('products.manage');
  return (
    <div className={styles.stack}>
      <PageHeader
        title="Categories & units"
        description="Catalogue settings shared by every product."
        backHref="/inventory/products"
        backLabel="Products"
      />
      <div className={styles.cardsGrid}>
        <CategoriesCard canManage={canManage} />
        <UnitsCard canManage={canManage} />
      </div>
    </div>
  );
}
