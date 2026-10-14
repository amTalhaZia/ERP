'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Alert, Button, FormField, FormGrid, Input, Modal, Switch, useToast } from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { procurementApi, type SupplierContact } from './api';

const schema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(150),
  title: z.string().trim().max(100).optional().or(z.literal('')),
  email: z.email('Enter a valid email').optional().or(z.literal('')),
  phone: z.string().trim().max(40).optional().or(z.literal('')),
  is_primary: z.boolean(),
});
type Values = z.infer<typeof schema>;

/** Invalidates every supplier query (detail embeds contacts). */
export function useInvalidateSuppliers() {
  const qc = useQueryClient();
  return () => qc.invalidateQueries({ predicate: (q) => q.queryKey.includes('suppliers') });
}

export function ContactForm({ supplierId, contact, open, onClose }: { supplierId: number; contact: SupplierContact | null; open: boolean; onClose: () => void }) {
  const toast = useToast();
  const invalidate = useInvalidateSuppliers();
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, control, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { name: contact?.name ?? '', title: contact?.title ?? '', email: contact?.email ?? '', phone: contact?.phone ?? '', is_primary: contact?.is_primary ?? false },
  });
  const save = useMutation<SupplierContact, ApiError, Record<string, unknown>>({
    mutationFn: (body) => (contact ? procurementApi.updateContact(supplierId, contact.id, body) : procurementApi.createContact(supplierId, body)),
    onSuccess: () => {
      void invalidate();
      toast.success(contact ? 'Contact saved' : 'Contact added');
      onClose();
    },
  });

  const onSubmit = async (v: Values) => {
    setFormError(null);
    try {
      await save.mutateAsync({ name: v.name, title: v.title || null, email: v.email || null, phone: v.phone || null, is_primary: v.is_primary });
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  const e = formState.errors;
  return (
    <Modal
      open={open}
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
        <div style={{ marginTop: 12 }}>
          <Controller control={control} name="is_primary" render={({ field }) => (
            <Switch checked={field.value} onCheckedChange={field.onChange} label="Primary contact" description="Replaces the current primary contact." />
          )} />
        </div>
      </form>
    </Modal>
  );
}
