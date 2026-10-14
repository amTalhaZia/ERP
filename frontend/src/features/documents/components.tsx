'use client';

import { useId, useRef, useState, type DragEvent } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Download, FileText, Lock, Trash2, UploadCloud, Users, X } from 'lucide-react';
import {
  Alert, Badge, Button, ConfirmDialog, EmptyState, ErrorState, IconButton, Input, Skeleton, type BadgeVariant,
} from '@/components/ui';
import { get } from '@/lib/api/client';
import type { ApiError } from '@/lib/api/errors';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, humanize } from '@/lib/format';
import {
  UPLOAD_ACCEPT, UPLOAD_ALLOWED_EXTENSIONS, UPLOAD_MAX_KB, documentsApi, fieldError, formatBytes, useCompanyId, useDocumentList,
  useDocumentsMutation, validateUpload, type DocumentRecord, type DocumentVisibility, type DocumentableType,
} from './api';
import styles from './documents.module.scss';

// ---------------------------------------------------------------------------
// Badges
// ---------------------------------------------------------------------------

const VIS_VARIANT: Record<DocumentVisibility, BadgeVariant> = { company: 'neutral', restricted: 'info', private: 'warning' };

export function VisibilityBadge({ visibility }: { visibility: DocumentVisibility }) {
  return (
    <Badge size="sm" variant={VIS_VARIANT[visibility]} icon={visibility === 'private' ? <Lock size={11} /> : visibility === 'restricted' ? <Users size={11} /> : undefined}>
      {humanize(visibility)}
    </Badge>
  );
}

// ---------------------------------------------------------------------------
// Roles (restricted visibility)
// ---------------------------------------------------------------------------

export interface RoleOption {
  id: number;
  name: string;
  slug: string;
}

/** Company roles for restricted documents. Needs roles.view; otherwise `allowed` is false. */
export function useRoleOptions() {
  const can = useCan();
  const companyId = useCompanyId();
  const allowed = can('roles.view');
  const q = useQuery({
    queryKey: [companyId, 'roles', 'documents-picker'],
    queryFn: () => get<{ data: RoleOption[] }>('/roles', { per_page: 100 }).then((r) => r.data),
    enabled: allowed,
    staleTime: 5 * 60_000,
  });
  return { roles: q.data ?? [], allowed, isLoading: allowed && q.isLoading, error: q.error };
}

// ---------------------------------------------------------------------------
// File drop zone
// ---------------------------------------------------------------------------

export function FileDropZone({
  file,
  onFile,
  error,
  compact,
  label = 'Drop a file here, or click to browse',
}: {
  file: File | null;
  onFile: (file: File | null, clientError: string | null) => void;
  error?: string | null;
  compact?: boolean;
  label?: string;
}) {
  const id = useId();
  const inputRef = useRef<HTMLInputElement>(null);
  const [over, setOver] = useState(false);

  const accept = (f: File | undefined | null) => {
    if (!f) return;
    onFile(f, validateUpload(f));
  };

  const onDrop = (e: DragEvent<HTMLLabelElement>) => {
    e.preventDefault();
    setOver(false);
    accept(e.dataTransfer.files?.[0]);
  };

  return (
    <div className={styles.stackSm}>
      {file ? (
        <div className={styles.fileChip}>
          <FileText size={16} className={styles.fileIcon} aria-hidden />
          <span title={file.name}>{file.name}</span>
          <small>{formatBytes(file.size)}</small>
          <IconButton
            size="sm"
            variant="ghost"
            icon={<X size={14} />}
            aria-label="Remove selected file"
            onClick={() => {
              if (inputRef.current) inputRef.current.value = '';
              onFile(null, null);
            }}
          />
        </div>
      ) : (
        <label
          htmlFor={id}
          className={styles.dropZone}
          data-over={over}
          data-invalid={!!error}
          data-compact={compact}
          onDragOver={(e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            if (!over) setOver(true);
          }}
          onDragLeave={(e) => {
            if (!e.currentTarget.contains(e.relatedTarget as Node | null)) setOver(false);
          }}
          onDrop={onDrop}
        >
          <UploadCloud size={compact ? 18 : 24} aria-hidden />
          <strong>{label}</strong>
          {!compact && <small>Up to {formatBytes(UPLOAD_MAX_KB * 1024)}</small>}
          <input
            ref={inputRef}
            id={id}
            type="file"
            accept={UPLOAD_ACCEPT}
            aria-invalid={!!error || undefined}
            aria-describedby={`${id}-allowed`}
            onChange={(e) => accept(e.target.files?.[0])}
          />
        </label>
      )}
      <span id={`${id}-allowed`} className={styles.allowed}>
        Allowed: {UPLOAD_ALLOWED_EXTENSIONS.join(', ')} · max {formatBytes(UPLOAD_MAX_KB * 1024)}
      </span>
      {error && <span role="alert" style={{ color: 'var(--color-danger-text)', fontSize: 12 }}>{error}</span>}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Documents linked to a record (project / task attachments)
// ---------------------------------------------------------------------------

export function LinkedDocuments({
  type,
  id,
  title = 'Files',
  emptyText = 'No files attached yet.',
  compact,
}: {
  type: DocumentableType;
  id: number;
  title?: string;
  emptyText?: string;
  compact?: boolean;
}) {
  const can = useCan();
  const canView = can('documents.view');
  const canManage = can('documents.manage');
  const q = useDocumentList({ documentable_type: type, documentable_id: id, per_page: 100, sort: '-created_at' }, { enabled: canView });
  const [file, setFile] = useState<File | null>(null);
  const [clientError, setClientError] = useState<string | null>(null);
  const [docTitle, setDocTitle] = useState('');
  const [deleting, setDeleting] = useState<DocumentRecord | null>(null);
  const [downloading, setDownloading] = useState<number | null>(null);
  const [downloadError, setDownloadError] = useState<string | null>(null);

  const upload = useDocumentsMutation(
    (f: File) => documentsApi.upload({ file: f, title: docTitle.trim() || undefined, documentable_type: type, documentable_id: id, visibility: 'company' }),
    {
      success: (d) => `Uploaded “${d.title}”`,
      errorTitle: 'Upload failed',
      onSuccess: () => {
        setFile(null);
        setDocTitle('');
      },
    },
  );
  const del = useDocumentsMutation((docId: number) => documentsApi.remove(docId), { success: 'File deleted', errorTitle: 'Could not delete file' });

  if (!canView) {
    return <Alert variant="info">You need document access to see attached files.</Alert>;
  }

  const serverError = upload.error ? fieldError(upload.error, 'file') ?? fieldError(upload.error) : null;
  const rows = q.data?.data ?? [];

  const download = async (d: DocumentRecord) => {
    setDownloading(d.id);
    setDownloadError(null);
    try {
      await documentsApi.download(d);
    } catch (e) {
      setDownloadError((e as ApiError).message ?? 'Download failed');
    } finally {
      setDownloading(null);
    }
  };

  return (
    <div className={styles.stack}>
      {title && <h3 className={styles.sectionTitle}>{title}</h3>}
      {downloadError && <Alert variant="danger" dismissible onDismiss={() => setDownloadError(null)}>{downloadError}</Alert>}
      {q.error ? (
        <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} />
      ) : q.isLoading ? (
        <Skeleton height={44} lines={2} />
      ) : rows.length === 0 ? (
        compact ? <p className={styles.note}>{emptyText}</p> : <EmptyState size="sm" icon={<FileText size={20} />} title={emptyText} />
      ) : (
        <ul className={styles.linkedList}>
          {rows.map((d) => (
            <li key={d.id}>
              <FileText size={16} className={styles.fileIcon} aria-hidden />
              <div className={styles.versionMain}>
                <strong title={d.title}>{d.title}</strong>
                <span>
                  {d.current_version ? `${d.current_version.original_name} · ${formatBytes(d.current_version.size_bytes)}` : 'No file'} · {formatDate(d.created_at)}
                  {d.owner ? ` · ${d.owner.name}` : ''}
                </span>
              </div>
              <IconButton size="sm" variant="ghost" icon={<Download size={15} />} aria-label={`Download ${d.title}`} loading={downloading === d.id} onClick={() => void download(d)} />
              {canManage && <IconButton size="sm" variant="ghost" icon={<Trash2 size={15} />} aria-label={`Delete ${d.title}`} onClick={() => setDeleting(d)} />}
            </li>
          ))}
        </ul>
      )}

      {canManage && (
        <div className={styles.stackSm}>
          <FileDropZone
            compact={compact}
            file={file}
            error={clientError ?? serverError}
            label={compact ? 'Attach a file (drop or browse)' : undefined}
            onFile={(f, err) => {
              upload.reset();
              setFile(err ? null : f);
              setClientError(err);
            }}
          />
          {file && (
            <div className={styles.row}>
              <Input size="sm" aria-label="File title" placeholder={`Title (defaults to ${file.name})`} maxLength={200} value={docTitle} onChange={(e) => setDocTitle(e.target.value)} style={{ flex: 1, minWidth: 160 }} />
              <Button size="sm" leftIcon={<UploadCloud size={14} />} loading={upload.isPending} onClick={() => upload.mutate(file)}>Upload</Button>
            </div>
          )}
        </div>
      )}

      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete “${deleting?.title}”?`}
        description="The document is archived and hidden from lists; administrators can still recover the file."
        destructive
        confirmLabel="Delete file"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </div>
  );
}
