'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Archive, ArchiveRestore, Copy, Download, Link2, Pencil, Trash2, UploadCloud } from 'lucide-react';
import {
  Alert, Badge, Button, ConfirmDialog, Divider, Drawer, ErrorState, FormField, IconButton, Input, Select, Skeleton,
} from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime, formatRelative, humanize } from '@/lib/format';
import {
  documentsApi, fieldError, formatBytes, useDocument, useDocumentsMutation,
  type DocumentRecord, type DocumentVersion, type ShareLink,
} from './api';
import { FileDropZone, VisibilityBadge, useRoleOptions } from './components';
import styles from './documents.module.scss';
import crud from '@/components/crud/crud.module.scss';

const SHARE_MINUTES = [5, 15, 30, 60];

function linkedHref(doc: DocumentRecord): string | null {
  if (!doc.documentable_type || !doc.documentable_id) return null;
  switch (doc.documentable_type) {
    case 'project':
      return `/projects/${doc.documentable_id}`;
    case 'customer':
      return `/sales/customers/${doc.documentable_id}`;
    case 'supplier':
      return `/procurement/suppliers/${doc.documentable_id}`;
    case 'purchase_order':
      return `/procurement/orders/${doc.documentable_id}`;
    default:
      return null;
  }
}

export function DocumentDrawer({
  documentId,
  open,
  onClose,
  onEdit,
}: {
  documentId: number;
  open: boolean;
  onClose: () => void;
  onEdit?: (doc: DocumentRecord) => void;
}) {
  const can = useCan();
  const canManage = can('documents.manage');
  const q = useDocument(documentId);
  const roles = useRoleOptions();
  const [confirm, setConfirm] = useState<'delete' | 'archive' | null>(null);
  const [newFile, setNewFile] = useState<File | null>(null);
  const [fileError, setFileError] = useState<string | null>(null);
  const [note, setNote] = useState('');
  const [minutes, setMinutes] = useState('15');
  const [shareVersion, setShareVersion] = useState('');
  const [share, setShare] = useState<ShareLink | null>(null);
  const [copied, setCopied] = useState(false);
  const [downloading, setDownloading] = useState<number | 'current' | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const addVersion = useDocumentsMutation(
    ({ file, note: n }: { file: File; note: string }) => documentsApi.addVersion(documentId, file, n || null),
    {
      success: (v: DocumentVersion) => `Version ${v.version} uploaded`,
      errorTitle: 'Upload failed',
      onSuccess: () => {
        setNewFile(null);
        setNote('');
      },
    },
  );
  const archive = useDocumentsMutation((archived: boolean) => documentsApi.update(documentId, { is_archived: archived }), {
    success: (d: DocumentRecord) => (d.is_archived ? 'Document archived' : 'Document restored'),
    errorTitle: 'Could not update document',
    toastOn422: true,
  });
  const del = useDocumentsMutation(() => documentsApi.remove(documentId), { success: 'Document deleted', errorTitle: 'Could not delete document', onSuccess: onClose });
  const createShare = useDocumentsMutation(
    () => documentsApi.shareLink(documentId, Number(minutes), shareVersion ? Number(shareVersion) : undefined),
    { errorTitle: 'Could not create link', toastOn422: true, onSuccess: (s: ShareLink) => { setShare(s); setCopied(false); } },
  );

  const doc = q.data;
  const versions = [...(doc?.versions ?? [])].sort((a, b) => b.version - a.version);

  const download = async (version?: DocumentVersion) => {
    if (!doc) return;
    setDownloading(version ? version.id : 'current');
    setActionError(null);
    try {
      await documentsApi.download(doc, version);
    } catch (e) {
      setActionError((e as ApiError).message);
    } finally {
      setDownloading(null);
    }
  };

  const copy = async () => {
    if (!share) return;
    try {
      await navigator.clipboard.writeText(share.url);
      setCopied(true);
    } catch {
      setActionError('Copy failed — select the link and copy it manually.');
    }
  };

  const href = doc ? linkedHref(doc) : null;
  const roleNames = doc?.allowed_role_ids.map((id) => roles.roles.find((r) => r.id === id)?.name ?? `Role #${id}`) ?? [];

  return (
    <Drawer
      open={open}
      onClose={onClose}
      size="lg"
      title={doc?.title ?? 'Document'}
      description={doc ? <span className={styles.row}><VisibilityBadge visibility={doc.visibility} />{doc.is_archived && <Badge size="sm" variant="neutral">Archived</Badge>}{doc.category && <Badge size="sm" variant="primary" outline>{humanize(doc.category)}</Badge>}</span> : undefined}
      footer={
        doc && (
          <>
            {canManage && <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirm('delete')}>Delete</Button>}
            {canManage && (
              <Button variant="ghost" leftIcon={doc.is_archived ? <ArchiveRestore size={16} /> : <Archive size={16} />} onClick={() => (doc.is_archived ? archive.mutate(false) : setConfirm('archive'))} loading={archive.isPending}>
                {doc.is_archived ? 'Restore' : 'Archive'}
              </Button>
            )}
            <span className={styles.spacer} />
            {canManage && onEdit && <Button variant="secondary" leftIcon={<Pencil size={16} />} onClick={() => onEdit(doc)}>Edit details</Button>}
            <Button leftIcon={<Download size={16} />} loading={downloading === 'current'} onClick={() => void download()} disabled={!doc.current_version}>Download</Button>
          </>
        )
      }
    >
      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : !doc ? (
        <Skeleton lines={8} />
      ) : (
        <div className={styles.stack}>
          {actionError && <Alert variant="danger" dismissible onDismiss={() => setActionError(null)}>{actionError}</Alert>}
          {doc.description && <p style={{ margin: 0, whiteSpace: 'pre-wrap' }}>{doc.description}</p>}
          <dl className={crud.dl}>
            <dt>File</dt>
            <dd>{doc.current_version ? `${doc.current_version.original_name} · ${formatBytes(doc.current_version.size_bytes)}` : '—'}</dd>
            <dt>Folder</dt><dd>{doc.folder?.name ?? 'No folder'}</dd>
            <dt>Visibility</dt>
            <dd>
              {humanize(doc.visibility)}
              {doc.visibility === 'restricted' && (roleNames.length ? ` · ${roleNames.join(', ')}` : ' · no roles')}
            </dd>
            <dt>Tags</dt>
            <dd>{doc.tags.length ? <span className={styles.tags}>{doc.tags.map((t) => <Badge key={t} size="sm" variant="neutral">{t}</Badge>)}</span> : '—'}</dd>
            <dt>Linked to</dt>
            <dd>
              {doc.documentable_type ? (
                href ? <Link href={href}>{humanize(doc.documentable_type)} #{doc.documentable_id}</Link> : `${humanize(doc.documentable_type)} #${doc.documentable_id}`
              ) : '—'}
            </dd>
            <dt>Owner</dt><dd>{doc.owner?.name ?? '—'}</dd>
            <dt>Retain until</dt><dd>{formatDate(doc.retention_until)}</dd>
            <dt>Uploaded</dt><dd>{formatDateTime(doc.created_at)}</dd>
            <dt>Updated</dt><dd>{formatRelative(doc.updated_at)}</dd>
            {doc.is_archived && (<><dt>Archived</dt><dd>{formatDateTime(doc.archived_at)}</dd></>)}
            {doc.current_version?.checksum_sha256 && (<><dt>SHA-256</dt><dd className={crud.mono} style={{ fontSize: 11 }}>{doc.current_version.checksum_sha256}</dd></>)}
          </dl>

          <Divider />
          <h3 className={styles.sectionTitle}>Versions ({versions.length})</h3>
          {versions.length === 0 ? (
            <p className={styles.note}>No versions.</p>
          ) : (
            <ul className={styles.versionList}>
              {versions.map((v) => (
                <li key={v.id}>
                  <span className={styles.versionNo}>v{v.version}</span>
                  <div className={styles.versionMain}>
                    <strong title={v.original_name}>{v.original_name}</strong>
                    <span>
                      {formatBytes(v.size_bytes)} · {formatDateTime(v.created_at)}{v.uploader ? ` · ${v.uploader.name}` : ''}
                      {v.note ? ` · “${v.note}”` : ''}
                    </span>
                  </div>
                  {doc.current_version?.id === v.id && <Badge size="sm" variant="success">Current</Badge>}
                  <IconButton size="sm" variant="ghost" icon={<Download size={15} />} aria-label={`Download version ${v.version}`} loading={downloading === v.id} onClick={() => void download(v)} />
                </li>
              ))}
            </ul>
          )}
          {canManage && (
            <div className={styles.stackSm}>
              <FileDropZone
                compact
                file={newFile}
                label="Upload a new version (drop or browse)"
                error={fileError ?? (addVersion.error ? fieldError(addVersion.error, 'file') ?? fieldError(addVersion.error) : null)}
                onFile={(f, err) => {
                  addVersion.reset();
                  setNewFile(err ? null : f);
                  setFileError(err);
                }}
              />
              {newFile && (
                <div className={styles.row}>
                  <Input size="sm" aria-label="Version note" placeholder="What changed? (optional)" maxLength={500} value={note} onChange={(e) => setNote(e.target.value)} style={{ flex: 1, minWidth: 160 }} />
                  <Button size="sm" leftIcon={<UploadCloud size={14} />} loading={addVersion.isPending} onClick={() => addVersion.mutate({ file: newFile, note })}>Upload version</Button>
                </div>
              )}
            </div>
          )}

          <Divider />
          <h3 className={styles.sectionTitle}>Share link</h3>
          <p className={styles.note}>
            Creates a short-lived link for opening this file in a new tab. Recipients must be signed in to this company and allowed to see the document — it is never a public link.
          </p>
          <div className={styles.row}>
            <FormField label="Expires after" inline>
              <Select size="sm" value={minutes} onChange={(e) => setMinutes(e.target.value)} options={SHARE_MINUTES.map((m) => ({ value: String(m), label: `${m} minutes` }))} />
            </FormField>
            {versions.length > 1 && (
              <FormField label="Version" inline>
                <Select size="sm" value={shareVersion} onChange={(e) => setShareVersion(e.target.value)} options={[{ value: '', label: 'Current' }, ...versions.map((v) => ({ value: String(v.version), label: `v${v.version}` }))]} />
              </FormField>
            )}
            <Button size="sm" variant="secondary" leftIcon={<Link2 size={14} />} loading={createShare.isPending} onClick={() => createShare.mutate(undefined)}>Create link</Button>
          </div>
          {share && (
            <div className={styles.stackSm}>
              <div className={styles.shareBox}>
                <Input size="sm" readOnly value={share.url} aria-label="Share link" onFocus={(e) => e.currentTarget.select()} />
                <Button size="sm" leftIcon={<Copy size={14} />} onClick={() => void copy()}>{copied ? 'Copied' : 'Copy'}</Button>
              </div>
              <span className={styles.note} role="status">
                Expires {formatDateTime(share.expires_at)}{share.requires_authentication ? ' · recipients must be signed in' : ''}.
              </span>
            </div>
          )}
        </div>
      )}

      <ConfirmDialog
        open={confirm === 'archive'}
        onClose={() => setConfirm(null)}
        title={`Archive “${doc?.title}”?`}
        description="Archived documents are hidden from the default list but can be restored at any time."
        confirmLabel="Archive"
        onConfirm={async () => {
          await archive.mutateAsync(true).catch(() => undefined);
          setConfirm(null);
        }}
      />
      <ConfirmDialog
        open={confirm === 'delete'}
        onClose={() => setConfirm(null)}
        title={`Delete “${doc?.title}”?`}
        description="The document and all its versions are removed from the library. Stored files are kept for recovery by an administrator."
        destructive
        confirmLabel="Delete document"
        onConfirm={async () => {
          await del.mutateAsync(undefined).catch(() => undefined);
          setConfirm(null);
        }}
      />
    </Drawer>
  );
}
