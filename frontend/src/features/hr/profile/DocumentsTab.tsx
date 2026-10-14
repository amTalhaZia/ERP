'use client';

import { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Download, FileText, Trash2, Upload } from 'lucide-react';
import {
  Alert, Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, DateInput, Drawer, EmptyState, ErrorState, FormField, FormGrid, IconButton, Input, Select,
  SkeletonTable, Switch, useToast,
} from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { formatDate, humanize } from '@/lib/format';
import { DOCUMENT_CATEGORIES, UPLOAD_ACCEPT, UPLOAD_MAX_BYTES, hrApi, useCompanyId, useInvalidate, type EmployeeDocument, type Employee } from '../api';
import { ConfidentialBadge, formatBytes } from '../components';
import crud from '@/components/crud/crud.module.scss';

const schema = z.object({
  title: z.string().trim().min(1, 'Title is required').max(150),
  category: z.enum(['contract', 'id', 'certificate', 'visa', 'other']),
  expires_at: z.string(),
  is_confidential: z.boolean(),
  file: z.custom<File | null>().refine((f) => f instanceof File && f.size >= 0, 'Choose a file').refine((f) => !(f instanceof File) || f.size <= UPLOAD_MAX_BYTES, 'The file is larger than 10 MB'),
});
type Values = z.infer<typeof schema>;

function UploadDrawer({ open, employee, onClose }: { open: boolean; employee: Employee; onClose: () => void }) {
  const invalidate = useInvalidate();
  const toast = useToast();
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, control, setError, setValue, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { title: '', category: 'other', expires_at: '', is_confidential: false, file: null },
  });
  const e = formState.errors;

  const onSubmit = async (v: Values) => {
    setFormError(null);
    try {
      await hrApi.uploadDocument(employee.id, { title: v.title, category: v.category, file: v.file as File, expires_at: v.expires_at || null, is_confidential: v.is_confidential });
      void invalidate('employee-documents');
      toast.success('Document uploaded');
      onClose();
    } catch (err) {
      setFormError(applyServerErrors(err as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  return (
    <Drawer open={open} onClose={onClose} title="Upload document" description={employee.name} size="sm"
      footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button type="submit" form="document-form" leftIcon={<Upload size={16} />} loading={formState.isSubmitting}>Upload</Button></>}>
      <form id="document-form" noValidate onSubmit={handleSubmit(onSubmit)}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={1}>
          <FormField label="File" required error={e.file?.message} hint="PDF, Office, image, CSV or text — up to 10 MB">
            <Input type="file" accept={UPLOAD_ACCEPT} onChange={(ev) => {
              const f = ev.target.files?.[0] ?? null;
              setValue('file', f, { shouldValidate: true, shouldDirty: true });
              if (f) setValue('title', f.name.replace(/\.[^.]+$/, ''), { shouldDirty: true });
            }} />
          </FormField>
          <FormField label="Title" required error={e.title?.message}><Input {...register('title')} /></FormField>
          <FormField label="Category" required error={e.category?.message}><Select {...register('category')} options={DOCUMENT_CATEGORIES} /></FormField>
          <FormField label="Expires on" hint="For IDs, visas and certificates" error={e.expires_at?.message}>
            <Controller control={control} name="expires_at" render={({ field }) => <DateInput value={field.value || null} onValueChange={(d) => field.onChange(d ?? '')} />} />
          </FormField>
          <Controller control={control} name="is_confidential" render={({ field }) => (
            <Switch checked={field.value} onCheckedChange={field.onChange} label="Confidential" description="Only people with “View employee documents” can see it; downloads are audited." />
          )} />
        </FormGrid>
      </form>
    </Drawer>
  );
}

export function DocumentsTab({ employee, canManage }: { employee: Employee; canManage: boolean }) {
  const companyId = useCompanyId();
  const toast = useToast();
  const invalidate = useInvalidate();
  const q = useQuery({ queryKey: [companyId, 'employee-documents', employee.id], queryFn: () => hrApi.documents(employee.id) });
  const [uploading, setUploading] = useState(false);
  const [deleting, setDeleting] = useState<EmployeeDocument | null>(null);
  const download = useMutation<void, ApiError, EmployeeDocument>({
    mutationFn: (d) => hrApi.downloadDocument(d),
    onError: (err) => toast.error('Download failed', err.message),
  });
  const remove = useMutation<void, ApiError, EmployeeDocument>({
    mutationFn: (d) => hrApi.deleteDocument(d.id),
    onSuccess: () => { void invalidate('employee-documents'); toast.success('Document deleted'); },
    onError: (err) => toast.error('Could not delete document', err.message),
  });
  const rows = q.data ?? [];

  return (
    <Card>
      <CardHeader
        title="Documents"
        description="IDs, certificates, visas and signed paperwork."
        actions={canManage && <Button size="sm" variant="secondary" leftIcon={<Upload size={14} />} onClick={() => setUploading(true)}>Upload</Button>}
      />
      <CardBody padding="none">
        {q.error ? (
          <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} />
        ) : q.isLoading ? (
          <div style={{ padding: 16 }}><SkeletonTable rows={3} cols={5} /></div>
        ) : rows.length === 0 ? (
          <EmptyState size="sm" icon={<FileText size={20} />} title="No documents" description={canManage ? 'Upload ID copies, certificates or signed contracts.' : undefined} />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines}>
              <thead><tr><th>Title</th><th>Category</th><th>Expires</th><th>Size</th><th>Uploaded</th><th aria-label="Actions" /></tr></thead>
              <tbody>
                {rows.map((d) => (
                  <tr key={d.id}>
                    <td>
                      <div className={crud.cellMain}>
                        <strong>{d.title} {d.is_confidential && <ConfidentialBadge />}</strong>
                        <span>{d.mime ?? ''}</span>
                      </div>
                    </td>
                    <td>{d.category === 'id' ? 'ID' : humanize(d.category)}</td>
                    <td>{d.expires_at ? <>{formatDate(d.expires_at)} {d.is_expired && <Badge size="sm" variant="danger">Expired</Badge>}</> : '—'}</td>
                    <td>{formatBytes(d.size_bytes)}</td>
                    <td><div className={crud.cellMain}><strong style={{ fontWeight: 400 }}>{formatDate(d.created_at)}</strong><span>{d.uploader?.name ?? ''}</span></div></td>
                    <td className={crud.r} style={{ whiteSpace: 'nowrap' }}>
                      <IconButton size="sm" icon={<Download size={14} />} aria-label={`Download ${d.title}`} loading={download.isPending && download.variables?.id === d.id} onClick={() => download.mutate(d)} />
                      {canManage && <IconButton size="sm" icon={<Trash2 size={14} />} aria-label={`Delete ${d.title}`} onClick={() => setDeleting(d)} />}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>
      <UploadDrawer key={uploading ? 'open' : 'closed'} open={uploading} employee={employee} onClose={() => setUploading(false)} />
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete “${deleting?.title ?? ''}”?`}
        description="The file is removed permanently."
        destructive
        confirmLabel="Delete document"
        onConfirm={async () => {
          if (deleting) await remove.mutateAsync(deleting).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </Card>
  );
}
