'use client';

import { useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { format, parseISO } from 'date-fns';
import { CalendarClock, Mail, MessageSquare, Pencil, Phone, Plus, Star, Trash2, Users } from 'lucide-react';
import {
  Alert, Avatar, Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, EmptyState, ErrorState, FormField, FormGrid, IconButton, Input, Menu, MenuItem, Modal, Select, Skeleton, Switch, Textarea, useToast,
} from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import { formatDateTime, formatRelative, humanize } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { salesApi, COMMUNICATION_TYPES, type CommunicationType, type CustomerCommunication, type CustomerContact } from './api';
import { enumOptions, nullable } from './shared';
import styles from './sales.module.scss';

function useInvalidateCustomers() {
  const qc = useQueryClient();
  return () => qc.invalidateQueries({ predicate: (q) => q.queryKey.includes('customers') });
}

// ---------------------------------------------------------------------------
// Contacts
// ---------------------------------------------------------------------------

const contactSchema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(150),
  title: z.string().trim().max(100).optional().or(z.literal('')),
  email: z.email('Enter a valid email').optional().or(z.literal('')),
  phone: z.string().trim().max(40).optional().or(z.literal('')),
  is_primary: z.boolean(),
});
type ContactValues = z.infer<typeof contactSchema>;

function ContactModal({ customerId, contact, onClose }: { customerId: number; contact: CustomerContact | null; onClose: () => void }) {
  const toast = useToast();
  const invalidate = useInvalidateCustomers();
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, control, setError, formState } = useForm<ContactValues>({
    resolver: zodResolver(contactSchema),
    defaultValues: { name: contact?.name ?? '', title: contact?.title ?? '', email: contact?.email ?? '', phone: contact?.phone ?? '', is_primary: contact?.is_primary ?? false },
  });

  const onSubmit = async (v: ContactValues) => {
    setFormError(null);
    const body = { name: v.name, title: nullable(v.title), email: nullable(v.email), phone: nullable(v.phone), is_primary: v.is_primary };
    try {
      if (contact) await salesApi.updateContact(customerId, contact.id, body);
      else await salesApi.createContact(customerId, body);
      await invalidate();
      toast.success(contact ? 'Contact saved' : 'Contact added');
      onClose();
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(contactSchema.shape)));
    }
  };

  const e = formState.errors;
  return (
    <Modal
      open
      onClose={onClose}
      title={contact ? `Edit ${contact.name}` : 'Add contact'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="contact-form" loading={formState.isSubmitting}>{contact ? 'Save contact' : 'Add contact'}</Button>
        </>
      }
    >
      <form id="contact-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          <FormField label="Name" required error={e.name?.message}><Input {...register('name')} autoFocus /></FormField>
          <FormField label="Job title" error={e.title?.message}><Input {...register('title')} /></FormField>
          <FormField label="Email" error={e.email?.message}><Input type="email" {...register('email')} /></FormField>
          <FormField label="Phone" error={e.phone?.message}><Input type="tel" {...register('phone')} /></FormField>
        </FormGrid>
        <div style={{ marginTop: 16 }}>
          <Controller control={control} name="is_primary" render={({ field }) => (
            <Switch checked={field.value} onCheckedChange={field.onChange} label="Primary contact" description="Used by default on documents and communications." />
          )} />
        </div>
      </form>
    </Modal>
  );
}

export function ContactsPanel({ customerId, contacts, canManage }: { customerId: number; contacts: CustomerContact[]; canManage: boolean }) {
  const toast = useToast();
  const invalidate = useInvalidateCustomers();
  const [editing, setEditing] = useState<CustomerContact | 'new' | null>(null);
  const [deleting, setDeleting] = useState<CustomerContact | null>(null);
  const del = useMutation({
    mutationFn: (id: number) => salesApi.deleteContact(customerId, id),
    onSuccess: async () => {
      await invalidate();
      toast.success('Contact removed');
    },
    onError: (e: ApiError) => toast.error('Could not remove contact', e.message),
  });

  return (
    <Card>
      <CardHeader
        title="Contacts"
        description="People you deal with at this customer."
        actions={canManage ? <Button size="sm" leftIcon={<Plus size={14} />} onClick={() => setEditing('new')}>Add contact</Button> : undefined}
      />
      <CardBody>
        {contacts.length === 0 ? (
          <EmptyState size="sm" icon={<Users size={20} />} title="No contacts yet" description="Add the buyers, accountants and decision makers you work with." />
        ) : (
          <div className={styles.contactGrid}>
            {[...contacts].sort((a, b) => Number(b.is_primary) - Number(a.is_primary)).map((c) => (
              <div key={c.id} className={styles.contactCard}>
                <Avatar name={c.name} size="md" />
                <div>
                  <strong>
                    {c.name} {c.is_primary && <Badge size="sm" variant="primary" icon={<Star size={10} />}>Primary</Badge>}
                  </strong>
                  {c.title && <span>{c.title}</span>}
                  {c.email && <a href={`mailto:${c.email}`}>{c.email}</a>}
                  {c.phone && <a href={`tel:${c.phone}`}>{c.phone}</a>}
                </div>
                {canManage && (
                  <Menu trigger={<IconButton icon={<Pencil size={14} />} size="sm" aria-label={`Actions for ${c.name}`} />} align="end">
                    <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(c)}>Edit</MenuItem>
                    <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(c)}>Remove</MenuItem>
                  </Menu>
                )}
              </div>
            ))}
          </div>
        )}
      </CardBody>
      {editing !== null && (
        <ContactModal key={editing === 'new' ? 'new' : editing.id} customerId={customerId} contact={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      )}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Remove ${deleting?.name}?`}
        destructive
        confirmLabel="Remove contact"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </Card>
  );
}

// ---------------------------------------------------------------------------
// Communications timeline
// ---------------------------------------------------------------------------

const ICONS: Record<CommunicationType, typeof Phone> = { call: Phone, email: Mail, meeting: Users, note: MessageSquare };

/** ISO datetime → value for <input type="datetime-local"> in the viewer's timezone. */
const toLocalInput = (iso: string | null | undefined) => (iso ? format(parseISO(iso), "yyyy-MM-dd'T'HH:mm") : '');
const fromLocalInput = (v: string | undefined) => (v ? new Date(v).toISOString() : null);

const commSchema = z
  .object({
    type: z.enum(COMMUNICATION_TYPES),
    subject: z.string().trim().min(1, 'Subject is required').max(200),
    body: z.string().max(10000).optional().or(z.literal('')),
    happened_at: z.string().min(1, 'When did it happen?'),
    follow_up_at: z.string().optional().or(z.literal('')),
  });
type CommValues = z.infer<typeof commSchema>;

function CommunicationModal({ customerId, item, onClose }: { customerId: number; item: CustomerCommunication | null; onClose: () => void }) {
  const toast = useToast();
  const qc = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, formState } = useForm<CommValues>({
    resolver: zodResolver(commSchema),
    defaultValues: {
      type: item?.type ?? 'call',
      subject: item?.subject ?? '',
      body: item?.body ?? '',
      happened_at: toLocalInput(item?.happened_at ?? new Date().toISOString()),
      follow_up_at: toLocalInput(item?.follow_up_at),
    },
  });

  const onSubmit = async (v: CommValues) => {
    setFormError(null);
    const body = { type: v.type, subject: v.subject, body: nullable(v.body), happened_at: fromLocalInput(v.happened_at), follow_up_at: fromLocalInput(v.follow_up_at) };
    try {
      if (item) await salesApi.updateCommunication(customerId, item.id, body);
      else await salesApi.createCommunication(customerId, body);
      await qc.invalidateQueries({ predicate: (q) => q.queryKey.includes('communications') });
      toast.success(item ? 'Entry updated' : 'Logged');
      onClose();
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(commSchema.shape)));
    }
  };

  const e = formState.errors;
  return (
    <Modal
      open
      onClose={onClose}
      title={item ? 'Edit communication' : 'Log communication'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="comm-form" loading={formState.isSubmitting}>{item ? 'Save' : 'Log it'}</Button>
        </>
      }
    >
      <form id="comm-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          <FormField label="Type" required error={e.type?.message}><Select {...register('type')} options={enumOptions(COMMUNICATION_TYPES)} /></FormField>
          <FormField label="When" required error={e.happened_at?.message}><Input type="datetime-local" {...register('happened_at')} /></FormField>
          <FormField label="Subject" required span="full" error={e.subject?.message}><Input {...register('subject')} autoFocus maxLength={200} /></FormField>
          <FormField label="Details" span="full" error={e.body?.message}><Textarea rows={5} {...register('body')} /></FormField>
          <FormField label="Follow up" hint="Optional reminder date & time" error={e.follow_up_at?.message}><Input type="datetime-local" {...register('follow_up_at')} /></FormField>
        </FormGrid>
      </form>
    </Modal>
  );
}

export function CommunicationsPanel({ customerId, canManage }: { customerId: number; canManage: boolean }) {
  const toast = useToast();
  const qc = useQueryClient();
  const companyId = useAppSelector((s) => s.session.current_company?.id ?? 0);
  const [type, setType] = useState('');
  const [editing, setEditing] = useState<CustomerCommunication | 'new' | null>(null);
  const [deleting, setDeleting] = useState<CustomerCommunication | null>(null);
  const params: Record<string, unknown> = { per_page: 50, sort: '-happened_at' };
  if (type) params['filter[type]'] = type;
  const q = useQuery({
    queryKey: [companyId, 'customers', customerId, 'communications', params],
    queryFn: () => salesApi.communications(customerId, params),
  });
  const del = useMutation({
    mutationFn: (id: number) => salesApi.deleteCommunication(customerId, id),
    onSuccess: async () => {
      await qc.invalidateQueries({ predicate: (x) => x.queryKey.includes('communications') });
      toast.success('Entry deleted');
    },
    onError: (e: ApiError) => toast.error('Could not delete entry', e.message),
  });
  const items = q.data?.data ?? [];
  const total = q.data?.meta?.pagination?.total ?? 0;
  const [now] = useState(() => Date.now());

  return (
    <Card>
      <CardHeader
        title="Communications"
        description="Calls, emails, meetings and notes — newest first."
        actions={
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <Select size="sm" aria-label="Filter by type" placeholder="All types" value={type} onChange={(e) => setType(e.target.value)} options={enumOptions(COMMUNICATION_TYPES)} />
            {canManage && <Button size="sm" leftIcon={<Plus size={14} />} onClick={() => setEditing('new')}>Log</Button>}
          </div>
        }
      />
      <CardBody>
        {q.error ? (
          <ErrorState error={q.error} onRetry={() => void q.refetch()} size="sm" />
        ) : q.isLoading ? (
          <Skeleton variant="text" lines={6} />
        ) : items.length === 0 ? (
          <EmptyState size="sm" icon={<MessageSquare size={20} />} title={type ? `No ${humanize(type).toLowerCase()} entries` : 'Nothing logged yet'} description="Log calls, emails and meetings to keep the whole team in context." />
        ) : (
          <>
            <ol className={styles.timeline}>
              {items.map((c) => {
                const Icon = ICONS[c.type] ?? MessageSquare;
                const due = c.follow_up_at && new Date(c.follow_up_at).getTime() < now;
                return (
                  <li key={c.id} className={styles.timelineItem}>
                    <span className={styles.timelineIcon} aria-hidden><Icon size={14} /></span>
                    <div className={styles.timelineBody}>
                      <h4>{c.subject}</h4>
                      <div className={styles.timelineMeta}>
                        <Badge size="sm">{humanize(c.type)}</Badge>
                        <time dateTime={c.happened_at ?? undefined} title={formatDateTime(c.happened_at)}>{formatRelative(c.happened_at)}</time>
                        {c.user && <span>by {c.user.name}</span>}
                        {c.follow_up_at && (
                          <Badge size="sm" variant={due ? 'warning' : 'info'} icon={<CalendarClock size={10} />}>
                            Follow up {formatDateTime(c.follow_up_at)}
                          </Badge>
                        )}
                      </div>
                      {c.body && <p>{c.body}</p>}
                    </div>
                    {canManage && (
                      <Menu trigger={<IconButton icon={<Pencil size={14} />} size="sm" aria-label={`Actions for ${c.subject}`} />} align="end">
                        <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(c)}>Edit</MenuItem>
                        <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(c)}>Delete</MenuItem>
                      </Menu>
                    )}
                  </li>
                );
              })}
            </ol>
            {total > items.length && <p className={styles.previewNote}>Showing the latest {items.length} of {total} entries.</p>}
          </>
        )}
      </CardBody>
      {editing !== null && (
        <CommunicationModal key={editing === 'new' ? 'new' : editing.id} customerId={customerId} item={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      )}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title="Delete this entry?"
        description={deleting?.subject}
        destructive
        confirmLabel="Delete"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </Card>
  );
}
