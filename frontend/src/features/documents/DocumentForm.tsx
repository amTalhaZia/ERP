'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
  Alert, Button, Checkbox, DateInput, Drawer, FormField, FormGrid, FormSection, Input, Select, Skeleton, Textarea,
} from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { humanize } from '@/lib/format';
import {
  DOCUMENTABLE_TYPES, DOCUMENT_CATEGORIES, VISIBILITY_HELP, documentsApi, useDocumentsMutation, useFolders, validateUpload,
  type DocumentFolder, type DocumentRecord, type DocumentVisibility, type DocumentableType,
} from './api';
import { FileDropZone, useRoleOptions } from './components';
import styles from './documents.module.scss';

const schema = z
  .object({
    file: z.custom<File | null>((v) => v === null || (typeof File !== 'undefined' && v instanceof File)),
    title: z.string().trim().max(200),
    description: z.string().trim().max(5000),
    category: z.enum(DOCUMENT_CATEGORIES),
    tags: z.string().trim().max(1000),
    folder_id: z.string(),
    visibility: z.enum(['company', 'restricted', 'private']),
    allowed_role_ids: z.array(z.number()),
    retention_until: z.string(),
    link_type: z.string(),
    link_id: z.string().trim().regex(/^\d*$/, 'Use a numeric record id'),
    note: z.string().trim().max(500),
  })
  .superRefine((v, ctx) => {
    const tags = splitTags(v.tags);
    if (tags.length > 20) ctx.addIssue({ code: 'custom', path: ['tags'], message: 'At most 20 tags.' });
    if (tags.some((t) => t.length > 40)) ctx.addIssue({ code: 'custom', path: ['tags'], message: 'Each tag can be at most 40 characters.' });
    if (v.visibility === 'restricted' && v.allowed_role_ids.length === 0) {
      ctx.addIssue({ code: 'custom', path: ['allowed_role_ids'], message: 'Choose at least one role.' });
    }
    if (v.link_type && !v.link_id) ctx.addIssue({ code: 'custom', path: ['link_id'], message: 'Enter the record id.' });
    if (!v.link_type && v.link_id) ctx.addIssue({ code: 'custom', path: ['link_type'], message: 'Choose the record type.' });
  });
type Values = z.infer<typeof schema>;

export function splitTags(raw: string): string[] {
  const seen = new Set<string>();
  for (const t of raw.split(',')) {
    const tag = t.trim().toLowerCase();
    if (tag) seen.add(tag);
  }
  return [...seen];
}

/** Folders flattened depth-first with indentation, for <Select>. */
export function folderOptions(folders: DocumentFolder[]) {
  const byParent = new Map<number | null, DocumentFolder[]>();
  for (const f of folders) {
    const key = f.parent_id && folders.some((p) => p.id === f.parent_id) ? f.parent_id : null;
    byParent.set(key, [...(byParent.get(key) ?? []), f]);
  }
  const out: { value: string; label: string }[] = [];
  const walk = (parent: number | null, depth: number) => {
    for (const f of (byParent.get(parent) ?? []).sort((a, b) => a.name.localeCompare(b.name))) {
      out.push({ value: String(f.id), label: `${'  '.repeat(depth)}${depth ? '└ ' : ''}${f.name}${f.is_private ? ' (private)' : ''}` });
      if (depth < 20) walk(f.id, depth + 1);
    }
  };
  walk(null, 0);
  return out;
}

export interface DocumentFormProps {
  open: boolean;
  onClose: () => void;
  /** Edit metadata of an existing document; omit to upload a new one. */
  document?: DocumentRecord | null;
  defaultFolderId?: number | null;
  /** Pre-filled (and locked) link to a record. */
  link?: { type: DocumentableType; id: number; label?: string } | null;
  onSaved?: (doc: DocumentRecord) => void;
}

export function DocumentForm({ open, onClose, document, defaultFolderId, link, onSaved }: DocumentFormProps) {
  const editing = !!document;
  const folders = useFolders(open);
  const roles = useRoleOptions();
  const [formError, setFormError] = useState<string | null>(null);

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      file: null,
      title: document?.title ?? '',
      description: document?.description ?? '',
      category: document?.category ?? 'other',
      tags: (document?.tags ?? []).join(', '),
      folder_id: document ? (document.folder_id ? String(document.folder_id) : '') : defaultFolderId ? String(defaultFolderId) : '',
      visibility: document?.visibility ?? 'company',
      allowed_role_ids: document?.allowed_role_ids ?? [],
      retention_until: document?.retention_until ?? '',
      link_type: link?.type ?? document?.documentable_type ?? '',
      link_id: link ? String(link.id) : document?.documentable_id ? String(document.documentable_id) : '',
      note: '',
    },
  });
  const { register, handleSubmit, control, setError, formState, watch } = form;
  const e = formState.errors;
  const visibility = watch('visibility');

  const save = useDocumentsMutation(
    (v: Values) => {
      const meta = {
        title: v.title || undefined,
        description: v.description || null,
        category: v.category,
        tags: splitTags(v.tags),
        folder_id: v.folder_id ? Number(v.folder_id) : null,
        visibility: v.visibility as DocumentVisibility,
        allowed_role_ids: v.visibility === 'restricted' ? v.allowed_role_ids : null,
        retention_until: v.retention_until || null,
        documentable_type: (v.link_type || null) as DocumentableType | null,
        documentable_id: v.link_id ? Number(v.link_id) : null,
      };
      if (document) return documentsApi.update(document.id, meta);
      return documentsApi.upload({ ...meta, file: v.file as File, note: v.note || null });
    },
    { success: document ? 'Document saved' : (d: DocumentRecord) => `Uploaded “${d.title}”`, errorTitle: document ? 'Could not save document' : 'Upload failed' },
  );

  const onSubmit = async (v: Values) => {
    setFormError(null);
    if (!editing) {
      if (!v.file) {
        setError('file', { type: 'required', message: 'Choose a file to upload.' });
        return;
      }
      const err = validateUpload(v.file);
      if (err) {
        setError('file', { type: 'client', message: err });
        return;
      }
    }
    try {
      const doc = await save.mutateAsync(v);
      onSaved?.(doc);
      onClose();
    } catch (err) {
      const apiErr = err as ApiError;
      if (apiErr.status === 422 && apiErr.errors) {
        // Map documentable_* onto the link fields; tags.N / allowed_role_ids.N onto their parents.
        const remapped: Record<string, string[]> = {};
        for (const [k, msgs] of Object.entries(apiErr.errors)) {
          const base = k.split('.')[0]!;
          const target = base === 'documentable_type' ? 'link_type' : base === 'documentable_id' ? 'link_id' : base;
          remapped[target] = msgs;
        }
        setFormError(applyServerErrors({ ...apiErr, errors: remapped }, setError as never, Object.keys(schema.shape)));
      } else {
        setFormError(apiErr.message);
      }
    }
  };

  const visibilityOptions = [
    { value: 'company', label: 'Company — everyone with document access' },
    ...(roles.allowed || visibility === 'restricted' ? [{ value: 'restricted', label: 'Restricted — selected roles' }] : []),
    { value: 'private', label: 'Private — only me' },
  ];

  return (
    <Drawer
      open={open}
      onClose={onClose}
      size="md"
      title={editing ? `Edit “${document?.title}”` : 'Upload document'}
      description={editing ? 'Update the document’s details. Upload a new version from the document panel.' : 'Files are stored privately and only downloadable through the app.'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="document-form" loading={formState.isSubmitting}>{editing ? 'Save changes' : 'Upload'}</Button>
        </>
      }
    >
      <form id="document-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        {!editing && (
          <FormSection title="File">
            <Controller
              control={control}
              name="file"
              render={({ field, fieldState }) => (
                <FileDropZone
                  file={field.value}
                  error={fieldState.error?.message}
                  onFile={(f, err) => {
                    field.onChange(err ? null : f);
                    if (err) setError('file', { type: 'client', message: err });
                    else form.clearErrors('file');
                    if (f && !err && !form.getValues('title')) form.setValue('title', f.name.replace(/\.[^.]+$/, ''));
                  }}
                />
              )}
            />
          </FormSection>
        )}
        <FormSection title="Details">
          <FormGrid columns={2}>
            <FormField label="Title" span="full" error={e.title?.message} hint={editing ? undefined : 'Defaults to the file name'}>
              <Input maxLength={200} {...register('title')} />
            </FormField>
            <FormField label="Description" span="full" error={e.description?.message}>
              <Textarea rows={3} maxLength={5000} {...register('description')} />
            </FormField>
            <FormField label="Category" error={e.category?.message}>
              <Select {...register('category')} options={DOCUMENT_CATEGORIES.map((c) => ({ value: c, label: humanize(c) }))} />
            </FormField>
            <FormField label="Folder" error={e.folder_id?.message}>
              {folders.isLoading ? <Skeleton height={36} /> : (
                <Select {...register('folder_id')} options={[{ value: '', label: 'No folder' }, ...folderOptions(folders.data ?? [])]} />
              )}
            </FormField>
            <FormField label="Tags" span="full" error={e.tags?.message} hint="Comma-separated, e.g. q3, signed, vendor">
              <Input {...register('tags')} />
            </FormField>
            <FormField label="Retain until" error={e.retention_until?.message} hint="Retention date for compliance">
              {(p) => (
                <Controller control={control} name="retention_until" render={({ field }) => (
                  <DateInput {...p} value={field.value} onValueChange={(v) => field.onChange(v ?? '')} />
                )} />
              )}
            </FormField>
            {!editing && (
              <FormField label="Version note" error={e.note?.message}>
                <Input maxLength={500} {...register('note')} placeholder="Optional" />
              </FormField>
            )}
          </FormGrid>
        </FormSection>
        <FormSection title="Access">
          <FormGrid columns={1}>
            <FormField label="Visibility" error={e.visibility?.message} hint={VISIBILITY_HELP[visibility as DocumentVisibility]}>
              <Select {...register('visibility')} options={visibilityOptions} />
            </FormField>
            {visibility === 'restricted' && (
              <FormField label="Roles with access" error={e.allowed_role_ids?.message}>
                {() => roles.isLoading ? <Skeleton height={80} /> : !roles.allowed ? (
                  <Alert variant="warning">You can’t view company roles, so the role list can’t be changed here.</Alert>
                ) : (
                  <Controller control={control} name="allowed_role_ids" render={({ field }) => (
                    <div className={styles.roleList} role="group" aria-label="Roles with access">
                      {roles.roles.map((r) => (
                        <Checkbox
                          key={r.id}
                          label={r.name}
                          checked={field.value.includes(r.id)}
                          onChange={(ev) => field.onChange(ev.target.checked ? [...field.value, r.id] : field.value.filter((x) => x !== r.id))}
                        />
                      ))}
                      {roles.roles.length === 0 && <span className={styles.note}>No roles found.</span>}
                    </div>
                  )} />
                )}
              </FormField>
            )}
          </FormGrid>
        </FormSection>
        <FormSection title="Linked record" description={link ? undefined : 'Optionally attach this document to a record elsewhere in the ERP.'}>
          {link ? (
            <p className={styles.note}>Attached to {humanize(link.type).toLowerCase()} {link.label ?? `#${link.id}`}.</p>
          ) : (
            <FormGrid columns={2}>
              <FormField label="Record type" error={e.link_type?.message}>
                <Select {...register('link_type')} options={[{ value: '', label: 'Not linked' }, ...DOCUMENTABLE_TYPES.map((t) => ({ value: t, label: humanize(t) }))]} />
              </FormField>
              <FormField label="Record id" error={e.link_id?.message}>
                <Input inputMode="numeric" {...register('link_id')} />
              </FormField>
            </FormGrid>
          )}
        </FormSection>
      </form>
    </Drawer>
  );
}
