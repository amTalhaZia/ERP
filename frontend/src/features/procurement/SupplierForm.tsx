'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Alert, Button, CurrencyInput, Drawer, FormField, FormGrid, FormSection, Input, Select, Switch, Textarea } from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { suppliers, type Supplier } from './api';

const optional = (max: number) => z.string().trim().max(max).optional().or(z.literal(''));
const intRange = (min: number, max: number) =>
  z
    .string()
    .trim()
    .optional()
    .refine((v) => !v || (/^\d+$/.test(v) && Number(v) >= min && Number(v) <= max), `Whole number between ${min} and ${max}`);
const coord = (min: number, max: number) =>
  z
    .string()
    .trim()
    .optional()
    .refine((v) => !v || (!Number.isNaN(Number(v)) && Number(v) >= min && Number(v) <= max), `Must be between ${min} and ${max}`);

const schema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(150),
  code: z.string().trim().max(20).regex(/^[A-Za-z0-9_-]*$/, 'Letters, numbers, - and _ only').optional().or(z.literal('')),
  type: z.enum(['company', 'individual']),
  email: z.email('Enter a valid email').optional().or(z.literal('')),
  phone: optional(40),
  website: z.url('Enter a full URL, e.g. https://example.com').optional().or(z.literal('')),
  tax_number: optional(60),
  currency: z.string().trim().regex(/^[A-Za-z]{3}$/, 'Use a 3-letter currency code'),
  payment_terms_days: intRange(0, 365),
  credit_limit: z.string().nullable(),
  address_line1: optional(200),
  address_line2: optional(200),
  city: optional(100),
  state: optional(100),
  postal_code: optional(20),
  country: z.string().trim().length(2, 'Use a 2-letter country code').optional().or(z.literal('')),
  latitude: coord(-90, 90),
  longitude: coord(-180, 180),
  bank_name: optional(150),
  bank_account_number: optional(64),
  rating: z.string(),
  lead_time_days: intRange(0, 365),
  categories: optional(900),
  notes: optional(2000),
  is_active: z.boolean(),
});
type Values = z.infer<typeof schema>;

const toValues = (s: Supplier | null, currency: string): Values => ({
  name: s?.name ?? '',
  code: s?.code ?? '',
  type: s?.type ?? 'company',
  email: s?.email ?? '',
  phone: s?.phone ?? '',
  website: s?.website ?? '',
  tax_number: s?.tax_number ?? '',
  currency: s?.currency ?? currency,
  payment_terms_days: s ? String(s.payment_terms_days ?? '') : '30',
  credit_limit: s?.credit_limit ?? null,
  address_line1: s?.address.line1 ?? '',
  address_line2: s?.address.line2 ?? '',
  city: s?.address.city ?? '',
  state: s?.address.state ?? '',
  postal_code: s?.address.postal_code ?? '',
  country: s?.address.country ?? '',
  latitude: s?.latitude ?? '',
  longitude: s?.longitude ?? '',
  bank_name: s?.bank_name ?? '',
  bank_account_number: '',
  rating: s?.rating ? String(s.rating) : '',
  lead_time_days: s?.lead_time_days != null ? String(s.lead_time_days) : '',
  categories: s?.categories?.join(', ') ?? '',
  notes: s?.notes ?? '',
  is_active: s?.is_active ?? true,
});

const nul = (v: string | undefined) => (v && v.trim() ? v.trim() : null);

/** Create / edit supplier drawer. Remount with a `key` to reset between records. */
export function SupplierForm({ open, supplier, onClose, onSaved }: { open: boolean; supplier: Supplier | null; onClose: () => void; onSaved?: (s: Supplier) => void }) {
  const companyCurrency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, control, formState } = useForm<Values>({ resolver: zodResolver(schema), defaultValues: toValues(supplier, companyCurrency) });
  const done = (s: Supplier) => {
    onSaved?.(s);
    onClose();
  };
  const create = suppliers.useCreate({ onSuccess: done });
  const update = suppliers.useUpdate({ onSuccess: done });

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const body: Record<string, unknown> = {
      name: v.name,
      type: v.type,
      email: nul(v.email),
      phone: nul(v.phone),
      website: nul(v.website),
      tax_number: nul(v.tax_number),
      currency: v.currency.toUpperCase(),
      payment_terms_days: v.payment_terms_days ? Number(v.payment_terms_days) : 0,
      credit_limit: v.credit_limit || null,
      address_line1: nul(v.address_line1),
      address_line2: nul(v.address_line2),
      city: nul(v.city),
      state: nul(v.state),
      postal_code: nul(v.postal_code),
      country: v.country ? v.country.toUpperCase() : null,
      latitude: v.latitude ? Number(v.latitude) : null,
      longitude: v.longitude ? Number(v.longitude) : null,
      bank_name: nul(v.bank_name),
      rating: v.rating ? Number(v.rating) : null,
      lead_time_days: v.lead_time_days ? Number(v.lead_time_days) : null,
      categories: (v.categories ?? '').split(',').map((c) => c.trim()).filter(Boolean).slice(0, 20),
      notes: nul(v.notes),
      is_active: v.is_active,
    };
    if (v.code) body.code = v.code;
    // The account number is write-only (encrypted at rest); only send when re-entered.
    if (v.bank_account_number) body.bank_account_number = v.bank_account_number.trim();
    try {
      if (supplier) await update.mutateAsync({ id: supplier.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  const e = formState.errors;
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={supplier ? `Edit ${supplier.name}` : 'New supplier'}
      description={supplier ? supplier.code : 'Leave the code empty to auto-number (SUP-00001).'}
      size="lg"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="supplier-form" loading={formState.isSubmitting}>
            {supplier ? 'Save changes' : 'Create supplier'}
          </Button>
        </>
      }
    >
      <form id="supplier-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormSection title="Details">
          <FormGrid columns={2}>
            <FormField label="Name" required error={e.name?.message}>
              <Input {...register('name')} autoFocus />
            </FormField>
            <FormField label="Code" error={e.code?.message} hint="Unique within the company">
              <Input {...register('code')} placeholder="Auto" />
            </FormField>
            <FormField label="Type" error={e.type?.message}>
              <Select {...register('type')} options={[{ value: 'company', label: 'Company' }, { value: 'individual', label: 'Individual' }]} />
            </FormField>
            <FormField label="Tax number" error={e.tax_number?.message}>
              <Input {...register('tax_number')} />
            </FormField>
            <FormField label="Email" error={e.email?.message}>
              <Input type="email" {...register('email')} />
            </FormField>
            <FormField label="Phone" error={e.phone?.message}>
              <Input type="tel" {...register('phone')} />
            </FormField>
            <FormField label="Website" span="full" error={e.website?.message}>
              <Input type="url" placeholder="https://" {...register('website')} />
            </FormField>
            <FormField label="Categories" span="full" hint="Comma-separated, e.g. Packaging, Raw materials" error={e.categories?.message}>
              <Input {...register('categories')} />
            </FormField>
          </FormGrid>
          <div style={{ marginTop: 12 }}>
            <Controller control={control} name="is_active" render={({ field }) => (
              <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" description="Inactive suppliers are hidden from purchase order selectors." />
            )} />
          </div>
        </FormSection>

        <FormSection title="Commercial terms">
          <FormGrid columns={2}>
            <FormField label="Currency" required error={e.currency?.message}>
              <Input maxLength={3} {...register('currency')} style={{ textTransform: 'uppercase' }} />
            </FormField>
            <FormField label="Payment terms (days)" error={e.payment_terms_days?.message}>
              <Input inputMode="numeric" {...register('payment_terms_days')} />
            </FormField>
            <FormField label="Credit limit" error={e.credit_limit?.message}>
              <Controller control={control} name="credit_limit" render={({ field }) => (
                <CurrencyInput currency={companyCurrency} value={field.value} onValueChange={field.onChange} />
              )} />
            </FormField>
            <FormField label="Lead time (days)" hint="Promised delivery time" error={e.lead_time_days?.message}>
              <Input inputMode="numeric" {...register('lead_time_days')} />
            </FormField>
            <FormField label="Rating" error={e.rating?.message}>
              <Select {...register('rating')} placeholder="Not rated" options={[1, 2, 3, 4, 5].map((n) => ({ value: String(n), label: `${'★'.repeat(n)} (${n})` }))} />
            </FormField>
          </FormGrid>
        </FormSection>

        <FormSection title="Bank details" description="The account number is encrypted and never shown again; only the last 4 digits are displayed.">
          <FormGrid columns={2}>
            <FormField label="Bank name" error={e.bank_name?.message}>
              <Input {...register('bank_name')} />
            </FormField>
            <FormField
              label="Account number / IBAN"
              error={e.bank_account_number?.message}
              hint={supplier?.bank_account_last4 ? `On file: •••• ${supplier.bank_account_last4}. Leave empty to keep.` : undefined}
            >
              <Input autoComplete="off" {...register('bank_account_number')} />
            </FormField>
          </FormGrid>
        </FormSection>

        <FormSection title="Address & location">
          <FormGrid columns={2}>
            <FormField label="Address line 1" span="full" error={e.address_line1?.message}><Input {...register('address_line1')} /></FormField>
            <FormField label="Address line 2" span="full" error={e.address_line2?.message}><Input {...register('address_line2')} /></FormField>
            <FormField label="City" error={e.city?.message}><Input {...register('city')} /></FormField>
            <FormField label="State / region" error={e.state?.message}><Input {...register('state')} /></FormField>
            <FormField label="Postal code" error={e.postal_code?.message}><Input {...register('postal_code')} /></FormField>
            <FormField label="Country" hint="ISO code, e.g. PK, US" error={e.country?.message}><Input maxLength={2} {...register('country')} /></FormField>
            <FormField label="Latitude" error={e.latitude?.message}><Input inputMode="decimal" {...register('latitude')} /></FormField>
            <FormField label="Longitude" error={e.longitude?.message}><Input inputMode="decimal" {...register('longitude')} /></FormField>
          </FormGrid>
        </FormSection>

        <FormSection title="Notes">
          <FormField label="Internal notes" error={e.notes?.message}>
            <Textarea rows={3} {...register('notes')} />
          </FormField>
        </FormSection>
      </form>
    </Drawer>
  );
}
