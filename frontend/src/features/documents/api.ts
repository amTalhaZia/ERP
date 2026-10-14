'use client';

import { useMutation, useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query';
import { api, get } from '@/lib/api/client';
import type { ApiError } from '@/lib/api/errors';
import type { Paginated, Single } from '@/lib/api/types';
import { useAppSelector } from '@/store';
import { useToast } from '@/components/ui';

// ---------------------------------------------------------------------------
// Enums (mirror app/Enums/Documents)
// ---------------------------------------------------------------------------

export const DOCUMENT_CATEGORIES = ['contract', 'policy', 'invoice', 'purchase', 'employee', 'legal', 'certificate', 'report', 'other'] as const;
export type DocumentCategory = (typeof DOCUMENT_CATEGORIES)[number];

export const DOCUMENT_VISIBILITIES = ['company', 'restricted', 'private'] as const;
export type DocumentVisibility = (typeof DOCUMENT_VISIBILITIES)[number];

export const DOCUMENTABLE_TYPES = ['project', 'task', 'employee', 'invoice', 'bill', 'purchase_order', 'customer', 'supplier'] as const;
export type DocumentableType = (typeof DOCUMENTABLE_TYPES)[number];

export const VISIBILITY_HELP: Record<DocumentVisibility, string> = {
  company: 'Everyone with document access in this company.',
  restricted: 'You, document managers, and the selected roles.',
  private: 'Only you.',
};

// ---------------------------------------------------------------------------
// Upload limits (mirror backend config/erp.php → uploads)
// ---------------------------------------------------------------------------

export const UPLOAD_ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'] as const;
export const UPLOAD_MAX_KB = 10240;
export const UPLOAD_ACCEPT = UPLOAD_ALLOWED_EXTENSIONS.map((e) => `.${e}`).join(',');

export function formatBytes(bytes: number | null | undefined): string {
  if (bytes === null || bytes === undefined) return '—';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/** Client-side pre-check; the server re-validates (mimes + extensions + size). */
export function validateUpload(file: File): string | null {
  const ext = file.name.includes('.') ? file.name.split('.').pop()!.toLowerCase() : '';
  if (!(UPLOAD_ALLOWED_EXTENSIONS as readonly string[]).includes(ext)) {
    return `“.${ext || '?'}” files aren’t allowed. Allowed: ${UPLOAD_ALLOWED_EXTENSIONS.join(', ')}.`;
  }
  if (file.size > UPLOAD_MAX_KB * 1024) {
    return `File is ${formatBytes(file.size)}; the limit is ${formatBytes(UPLOAD_MAX_KB * 1024)}.`;
  }
  if (file.size === 0) return 'The file is empty.';
  return null;
}

// ---------------------------------------------------------------------------
// Types (mirror app/Http/Resources/Documents)
// ---------------------------------------------------------------------------

export interface DocumentVersion {
  id: number;
  document_id: number;
  version: number;
  original_name: string;
  mime: string | null;
  size_bytes: number;
  checksum_sha256: string | null;
  uploaded_by: number | null;
  uploader?: { id: number; name: string } | null;
  note: string | null;
  created_at: string | null;
}

export interface DocumentRecord {
  id: number;
  title: string;
  description: string | null;
  category: DocumentCategory | null;
  tags: string[];
  folder_id: number | null;
  folder?: { id: number; name: string } | null;
  documentable_type: DocumentableType | null;
  documentable_id: number | null;
  visibility: DocumentVisibility;
  allowed_role_ids: number[];
  owner_id: number | null;
  owner?: { id: number; name: string } | null;
  current_version?: DocumentVersion | null;
  versions?: DocumentVersion[];
  versions_count?: number;
  download_url: string;
  retention_until: string | null;
  is_archived: boolean;
  archived_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface DocumentFolder {
  id: number;
  parent_id: number | null;
  name: string;
  is_private: boolean;
  owner_id: number | null;
  documents_count?: number;
  children_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface DocumentMetadataInput {
  title?: string;
  description?: string | null;
  category?: DocumentCategory;
  tags?: string[] | null;
  folder_id?: number | null;
  documentable_type?: DocumentableType | null;
  documentable_id?: number | null;
  visibility?: DocumentVisibility;
  allowed_role_ids?: number[] | null;
  retention_until?: string | null;
  is_archived?: boolean;
}

export interface UploadInput extends DocumentMetadataInput {
  file: File;
  note?: string | null;
}

export interface ShareLink {
  url: string;
  expires_at: string;
  requires_authentication: boolean;
}

export interface FolderInput {
  name: string;
  parent_id?: number | null;
  is_private?: boolean;
}

// ---------------------------------------------------------------------------
// Raw API
// ---------------------------------------------------------------------------

function toFormData(input: UploadInput): FormData {
  const fd = new FormData();
  fd.append('file', input.file);
  const scalar = (k: string, v: string | number | null | undefined) => {
    if (v !== null && v !== undefined && v !== '') fd.append(k, String(v));
  };
  scalar('title', input.title);
  scalar('description', input.description);
  scalar('category', input.category);
  scalar('folder_id', input.folder_id);
  scalar('documentable_type', input.documentable_type);
  scalar('documentable_id', input.documentable_id);
  scalar('visibility', input.visibility);
  scalar('retention_until', input.retention_until);
  scalar('note', input.note);
  (input.tags ?? []).forEach((t, i) => fd.append(`tags[${i}]`, t));
  (input.allowed_role_ids ?? []).forEach((r, i) => fd.append(`allowed_role_ids[${i}]`, String(r)));
  return fd;
}

function saveBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function filenameFrom(disposition: unknown, fallback: string) {
  if (typeof disposition !== 'string') return fallback;
  const star = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
  if (star?.[1]) return decodeURIComponent(star[1]);
  const plain = /filename="?([^";]+)"?/i.exec(disposition);
  return plain?.[1] ?? fallback;
}

export const documentsApi = {
  list: (params: Record<string, unknown>) => get<Paginated<DocumentRecord>>('/documents', params),
  show: (id: number) => get<Single<DocumentRecord>>(`/documents/${id}`).then((r) => r.data),
  upload: (input: UploadInput) => api.post<Single<DocumentRecord>>('/documents', toFormData(input)).then((r) => r.data.data),
  update: (id: number, body: DocumentMetadataInput) => api.put<Single<DocumentRecord>>(`/documents/${id}`, body).then((r) => r.data.data),
  remove: (id: number) => api.delete(`/documents/${id}`).then(() => undefined),
  versions: (id: number) => get<{ data: DocumentVersion[] }>(`/documents/${id}/versions`).then((r) => r.data),
  addVersion: (id: number, file: File, note?: string | null) => {
    const fd = new FormData();
    fd.append('file', file);
    if (note) fd.append('note', note);
    return api.post<Single<DocumentVersion>>(`/documents/${id}/versions`, fd).then((r) => r.data.data);
  },
  shareLink: (id: number, minutes: number, version?: number) =>
    api.post<Single<ShareLink>>(`/documents/${id}/share-link`, version ? { minutes, version } : { minutes }).then((r) => r.data.data),
  async download(doc: Pick<DocumentRecord, 'id' | 'title'>, version?: Pick<DocumentVersion, 'version' | 'original_name'>) {
    const res = await api.get<Blob>(`/documents/${doc.id}/download`, {
      params: version ? { version: version.version } : undefined,
      responseType: 'blob',
    });
    saveBlob(res.data, filenameFrom(res.headers['content-disposition'], version?.original_name ?? doc.title));
  },
  folders: () => get<Paginated<DocumentFolder>>('/document-folders', { per_page: 100 }).then((r) => r.data),
  createFolder: (body: FolderInput) => api.post<Single<DocumentFolder>>('/document-folders', body).then((r) => r.data.data),
  updateFolder: (id: number, body: Partial<FolderInput>) => api.put<Single<DocumentFolder>>(`/document-folders/${id}`, body).then((r) => r.data.data),
  deleteFolder: (id: number) => api.delete(`/document-folders/${id}`).then(() => undefined),
};

// ---------------------------------------------------------------------------
// Hooks
// ---------------------------------------------------------------------------

const KEY = 'documents';

export function useCompanyId() {
  return useAppSelector((s) => s.session.current_company?.id ?? 0);
}

export function useDocumentList(params: Record<string, unknown>, opts: { enabled?: boolean } = {}) {
  const companyId = useCompanyId();
  return useQuery({
    queryKey: [companyId, KEY, 'list', params],
    queryFn: () => documentsApi.list(params),
    placeholderData: keepPreviousData,
    enabled: opts.enabled ?? true,
  });
}

export function useDocument(id: number | null) {
  const companyId = useCompanyId();
  return useQuery({
    queryKey: [companyId, KEY, 'one', id],
    queryFn: () => documentsApi.show(id!),
    enabled: id !== null,
  });
}

export function useFolders(enabled = true) {
  const companyId = useCompanyId();
  return useQuery({ queryKey: [companyId, KEY, 'folders'], queryFn: documentsApi.folders, staleTime: 30_000, enabled });
}

/** Mutation that refreshes every documents query on success. */
export function useDocumentsMutation<TVars, TRes = unknown>(
  fn: (vars: TVars) => Promise<TRes>,
  opts: { success?: string | ((r: TRes) => string); errorTitle?: string; onSuccess?: (r: TRes, vars: TVars) => void; toastOn422?: boolean } = {},
) {
  const qc = useQueryClient();
  const toast = useToast();
  return useMutation<TRes, ApiError, TVars>({
    mutationFn: fn,
    onSuccess: (r, vars) => {
      void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes(KEY) });
      if (opts.success) toast.success(typeof opts.success === 'function' ? opts.success(r) : opts.success);
      opts.onSuccess?.(r, vars);
    },
    onError: (e) => {
      if (e.status !== 422 || opts.toastOn422) toast.error(opts.errorTitle ?? 'Action failed', e.message);
    },
  });
}

/** First 422 message for a field (or any). */
export function fieldError(e: ApiError | null | undefined, field?: string): string | null {
  if (!e) return null;
  if (e.status !== 422 || !e.errors) return e.message;
  if (field) return e.errors[field]?.[0] ?? null;
  return Object.values(e.errors)[0]?.[0] ?? e.message;
}
