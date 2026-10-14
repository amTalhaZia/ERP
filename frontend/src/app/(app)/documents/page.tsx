'use client';

import { Suspense, useMemo, useState } from 'react';
import { Download, Eye, FileText, Pencil, Upload } from 'lucide-react';
import {
  Alert, Badge, Button, DataTable, EmptyState, FilterBar, MenuItem, PageHeader, SearchInput, Select, Switch, paginationFromMeta,
  type DataTableColumn,
} from '@/components/ui';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatRelative, humanize } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import {
  DOCUMENT_CATEGORIES, documentsApi, formatBytes, useDocumentList, useFolders, type DocumentRecord,
} from '@/features/documents/api';
import { VisibilityBadge } from '@/features/documents/components';
import { DocumentForm } from '@/features/documents/DocumentForm';
import { DocumentDrawer } from '@/features/documents/DocumentDrawer';
import { FolderTree } from '@/features/documents/FolderTree';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/documents/documents.module.scss';

function DocumentsInner() {
  const can = useCan();
  const canManage = can('documents.manage');
  const list = useListState({ sort: '-created_at' });
  const { state, update, clearFilters, activeFilterCount } = list;
  const folderId = state.filters.folder ? Number(state.filters.folder) : null;
  const archived = state.filters.archived === '1';
  const folders = useFolders();

  const params = useMemo(() => {
    const p: Record<string, unknown> = { ...list.apiParams };
    delete p['filter[folder]'];
    delete p['filter[archived]'];
    if (folderId) p.folder_id = folderId;
    p.archived = archived ? '1' : '0';
    return p;
  }, [list.apiParams, folderId, archived]);

  const query = useDocumentList(params);
  const [viewing, setViewing] = useState<number | null>(null);
  const [form, setForm] = useState<{ doc: DocumentRecord | null } | null>(null);
  const [downloadError, setDownloadError] = useState<string | null>(null);

  const meta = query.data?.meta?.pagination;
  const folderName = folderId ? folders.data?.find((f) => f.id === folderId)?.name : null;
  const filterCount = activeFilterCount - (state.filters.folder ? 1 : 0);
  const filtered = !!state.search || filterCount > 0;

  const download = async (d: DocumentRecord) => {
    setDownloadError(null);
    try {
      await documentsApi.download(d);
    } catch (e) {
      setDownloadError((e as ApiError).message);
    }
  };

  const columns: DataTableColumn<DocumentRecord>[] = [
    {
      id: 'title',
      accessorKey: 'title',
      header: 'Document',
      enableSorting: true,
      cell: ({ row }) => {
        const d = row.original;
        return (
          <div className={crud.cellMain}>
            <strong title={d.title}>{d.title}</strong>
            <span>
              {d.current_version ? `${d.current_version.original_name} · ${formatBytes(d.current_version.size_bytes)}` : 'No file'}
              {d.tags.length ? ` · ${d.tags.map((t) => `#${t}`).join(' ')}` : ''}
            </span>
          </div>
        );
      },
    },
    {
      id: 'category',
      accessorKey: 'category',
      header: 'Category',
      enableSorting: true,
      cell: ({ row }) => (row.original.category ? humanize(row.original.category) : <span className={crud.muted}>—</span>),
    },
    { id: 'folder', header: 'Folder', cell: ({ row }) => row.original.folder?.name ?? <span className={crud.muted}>—</span> },
    {
      id: 'visibility',
      header: 'Visibility',
      cell: ({ row }) => (
        <span className={styles.row}>
          <VisibilityBadge visibility={row.original.visibility} />
          {row.original.is_archived && <Badge size="sm" variant="neutral">Archived</Badge>}
        </span>
      ),
    },
    { id: 'owner', header: 'Owner', cell: ({ row }) => row.original.owner?.name ?? '—' },
    {
      id: 'retention_until',
      accessorKey: 'retention_until',
      header: 'Retain until',
      enableSorting: true,
      cell: ({ row }) => formatDate(row.original.retention_until),
    },
    {
      id: 'updated_at',
      accessorKey: 'updated_at',
      header: 'Updated',
      enableSorting: true,
      cell: ({ row }) => <span title={formatDate(row.original.updated_at)}>{formatRelative(row.original.updated_at)}</span>,
    },
  ];

  return (
    <div className={crud.page}>
      <PageHeader
        title="Documents"
        description="Company files with versions, access control and retention dates."
        actions={canManage && <Button leftIcon={<Upload size={16} />} onClick={() => setForm({ doc: null })}>Upload</Button>}
      />
      <div className={styles.layout}>
        <FolderTree selected={folderId} onSelect={(id) => update({ filters: { folder: id ? String(id) : undefined } })} />
        <div className={styles.main}>
          {downloadError && <Alert variant="danger" dismissible onDismiss={() => setDownloadError(null)}>{downloadError}</Alert>}
          <div className={crud.toolbar}>
            <SearchInput
              className={crud.search}
              placeholder="Search title, description or tag"
              aria-label="Search documents"
              defaultValue={state.search}
              onDebouncedChange={(v: string) => update({ search: v })}
            />
            <FilterBar activeCount={filterCount} onClear={() => (folderId ? update({ filters: { category: undefined, visibility: undefined, archived: undefined } }) : clearFilters())}>
              <Select
                aria-label="Category"
                placeholder="Any category"
                value={state.filters.category ?? ''}
                onChange={(e) => update({ filters: { category: e.target.value || undefined } })}
                options={DOCUMENT_CATEGORIES.map((c) => ({ value: c, label: humanize(c) }))}
              />
              <Select
                aria-label="Visibility"
                placeholder="Any visibility"
                value={state.filters.visibility ?? ''}
                onChange={(e) => update({ filters: { visibility: e.target.value || undefined } })}
                options={[{ value: 'company', label: 'Company' }, { value: 'restricted', label: 'Restricted' }, { value: 'private', label: 'Private' }]}
              />
              <Switch
                size="sm"
                checked={archived}
                onCheckedChange={(v) => update({ filters: { archived: v ? '1' : undefined } })}
                label="Archived only"
              />
            </FilterBar>
          </div>
          {folderName && <p className={styles.note} style={{ margin: 0 }}>In folder <strong>{folderName}</strong></p>}
          <DataTable<DocumentRecord>
            aria-label="Documents"
            columns={columns}
            data={query.data?.data ?? []}
            isLoading={query.isLoading || (query.isFetching && query.isPlaceholderData)}
            error={query.error}
            onRetry={() => void query.refetch()}
            sort={state.sort}
            onSortChange={(sort) => update({ sort })}
            pagination={
              meta
                ? { ...paginationFromMeta(meta), onPageChange: (page: number) => update({ page }, false), onPerPageChange: (perPage: number) => update({ perPage }) }
                : undefined
            }
            onRowClick={(d) => setViewing(d.id)}
            rowActions={(d) => (
              <>
                <MenuItem icon={<Eye size={16} />} onSelect={() => setViewing(d.id)}>Details & versions</MenuItem>
                <MenuItem icon={<Download size={16} />} onSelect={() => void download(d)} disabled={!d.current_version}>Download</MenuItem>
                {canManage && <MenuItem icon={<Pencil size={16} />} onSelect={() => setForm({ doc: d })}>Edit details</MenuItem>}
              </>
            )}
            enableColumnVisibility
            emptyState={
              filtered ? (
                <EmptyState icon={<FileText size={22} />} title="No matching documents" description="Try a different search or clear the filters." />
              ) : (
                <EmptyState
                  icon={<FileText size={22} />}
                  title={archived ? 'No archived documents' : folderId ? 'This folder is empty' : 'No documents yet'}
                  description={canManage ? 'Upload contracts, policies and other files to keep them in one place.' : undefined}
                  action={canManage && !archived ? <Button leftIcon={<Upload size={16} />} onClick={() => setForm({ doc: null })}>Upload a document</Button> : undefined}
                />
              )
            }
          />
        </div>
      </div>

      {viewing !== null && (
        <DocumentDrawer
          key={viewing}
          documentId={viewing}
          open
          onClose={() => setViewing(null)}
          onEdit={(doc) => {
            setViewing(null);
            setForm({ doc });
          }}
        />
      )}
      {form && (
        <DocumentForm
          key={form.doc ? `edit-${form.doc.id}` : `new-${folderId ?? 'root'}`}
          open
          document={form.doc}
          defaultFolderId={folderId}
          onClose={() => setForm(null)}
          onSaved={(d) => { if (form.doc) setViewing(d.id); }}
        />
      )}
    </div>
  );
}

export default function DocumentsPage() {
  return (
    <Suspense>
      <DocumentsInner />
    </Suspense>
  );
}
