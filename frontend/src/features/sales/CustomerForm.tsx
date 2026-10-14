'use client';

import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Alert, Button, Checkbox, CurrencyInput, Drawer, FormField, FormGrid, FormSection, Input, Select, Switch, Textarea } from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { customers, CUSTOMER_TYPES, type Customer, type PostalAddress } from './api';
import { enumOptions, nullable, useUserOptions } from './shared';

const opt = (max: number) => z.string().trim().max(max).optional().or(z.literal(''));
const country = z.string().trim().length(2, 'Use a 2-letter country code').optional().or(z.literal(''));
const coord = (min: number, max: number) =>
  z
    .string()
    .trim()
    .optional()
    .refine((v) => !v || (!Number.isNaN(Number(v)) && Number(v) >= min && Number(v) <= max), `Must be between ${min} and ${max}`);

const schema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(150),
  code: z.string().trim().max(20).regex(/^[A-Za-z0-9_-]*$/, 'Letters, numbers, - and _ only').optional().or(z.literal('')),
  type: z.enum(CUSTOMER_TYPES),
  email: z.email('Enter a valid email').optional().or(z.literal('')),
  phone: opt(40),
  website: opt(200),
  tax_number: opt(60),
  currency: z.string().trim().regex(/^[A-Za-z]{3}$/, 'Use a 3-letter currency code'),
  payment_terms_days: z.string().trim().regex(/^\d{1,3}$/, 'Whole days, 0–365').refine((v) => Number(v) <= 365, 'Whole days, 0–365'),
  credit_limit: z.string().nullable(),
  billing_address_line1: opt(200),
  billing_address_line2: opt(200),
  billing_city: opt(100),
  billing_state: opt(100),
  billing_postal_code: opt(20),
  billing_country: country,
  same_as_billing: z.boolean(),
  shipping_address_line1: opt(200),
  shipping_address_line2: opt(200),
  shipping_city: opt(100),
  shipping_state: opt(100),
  shipping_postal_code: opt(20),
  shipping_country: country,
  latitude: coord(-90, 90),
  longitude: coord(-180, 180),
  sales_rep_id: z.string(),
  source: opt(40),
  tags: z
    .string()
    .optional()
    .refine((v) => !v || v.split(',').filter((t) => t.trim()).length <= 20, 'At most 20 tags')
    .refine((v) => !v || v.split(',').every((t) => t.trim().length <= 40), 'Each tag up to 40 characters'),
  notes: opt(5000),
  is_active: z.boolean(),
});
type Values = z.infer<typeof schema>;

const ADDRESS_KEYS = ['address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country'] as const;
const addrField = (a: PostalAddress, k: (typeof ADDRESS_KEYS)[number]) => (k === 'address_line1' ? a.line1 : k === 'address_line2' ? a.line2 : a[k]) ?? '';
const sameAddress = (a: PostalAddress, b: PostalAddress) => ADDRESS_KEYS.every((k) => addrField(a, k) === addrField(b, k));

const toValues = (c: Customer | null, currency: string): Values => ({
  name: c?.name ?? '',
  code: c?.code ?? '',
  type: c?.type ?? 'company',
  email: c?.email ?? '',
  phone: c?.phone ?? '',
  website: c?.website ?? '',
  tax_number: c?.tax_number ?? '',
  currency: c?.currency ?? currency,
  payment_terms_days: String(c?.payment_terms_days ?? 30),
  credit_limit: c?.credit_limit ?? null,
  billing_address_line1: c?.billing_address.line1 ?? '',
  billing_address_line2: c?.billing_address.line2 ?? '',
  billing_city: c?.billing_address.city ?? '',
  billing_state: c?.billing_address.state ?? '',
  billing_postal_code: c?.billing_address.postal_code ?? '',
  billing_country: c?.billing_address.country ?? '',
  same_as_billing: c ? sameAddress(c.billing_address, c.shipping_address) : true,
  shipping_address_line1: c?.shipping_address.line1 ?? '',
  shipping_address_line2: c?.shipping_address.line2 ?? '',
  shipping_city: c?.shipping_address.city ?? '',
  shipping_state: c?.shipping_address.state ?? '',
  shipping_postal_code: c?.shipping_address.postal_code ?? '',
  shipping_country: c?.shipping_address.country ?? '',
  latitude: c?.latitude ?? '',
  longitude: c?.longitude ?? '',
  sales_rep_id: c?.sales_rep_id ? String(c.sales_rep_id) : '',
  source: c?.source ?? '',
  tags: (c?.tags ?? []).join(', '),
  notes: c?.notes ?? '',
  is_active: c?.is_active ?? true,
});

/** Customer create/edit drawer. Remount with `key` to reset between records. */
export function CustomerForm({ open, customer, onClose, onSaved }: { open: boolean; customer: Customer | null; onClose: () => void; onSaved?: (c: Customer) => void }) {
  const companyCurrency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, control, watch, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: toValues(customer, companyCurrency),
  });
  const reps = useUserOptions([customer?.sales_rep]);
  const done = (c: Customer) => {
    onSaved?.(c);
    onClose();
  };
  const create = customers.useCreate({ onSuccess: done });
  const update = customers.useUpdate({ onSuccess: done });
  const same = watch('same_as_billing');
  const currency = watch('currency');

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const ship = (k: (typeof ADDRESS_KEYS)[number]) => {
      const src = v.same_as_billing ? v[`billing_${k}`] : v[`shipping_${k}`];
      return k === 'country' ? (nullable(src)?.toUpperCase() ?? null) : nullable(src);
    };
    const body: Record<string, unknown> = {
      name: v.name.trim(),
      type: v.type,
      email: nullable(v.email),
      phone: nullable(v.phone),
      website: nullable(v.website),
      tax_number: nullable(v.tax_number),
      currency: v.currency.toUpperCase(),
      payment_terms_days: Number(v.payment_terms_days),
      credit_limit: v.credit_limit,
      billing_address_line1: nullable(v.billing_address_line1),
      billing_address_line2: nullable(v.billing_address_line2),
      billing_city: nullable(v.billing_city),
      billing_state: nullable(v.billing_state),
      billing_postal_code: nullable(v.billing_postal_code),
      billing_country: nullable(v.billing_country)?.toUpperCase() ?? null,
      shipping_address_line1: ship('address_line1'),
      shipping_address_line2: ship('address_line2'),
      shipping_city: ship('city'),
      shipping_state: ship('state'),
      shipping_postal_code: ship('postal_code'),
      shipping_country: ship('country'),
      latitude: v.latitude ? Number(v.latitude) : null,
      longitude: v.longitude ? Number(v.longitude) : null,
      sales_rep_id: v.sales_rep_id ? Number(v.sales_rep_id) : null,
      source: nullable(v.source),
      tags: (v.tags ?? '').split(',').map((t) => t.trim()).filter(Boolean),
      notes: nullable(v.notes),
      is_active: v.is_active,
    };
    if (v.code?.trim()) body.code = v.code.trim();
    try {
      if (customer) await update.mutateAsync({ id: customer.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      const err = e as ApiError;
      if (err.errors) {
        // Shipping errors surface on billing fields when "same as billing" hides them.
        for (const k of Object.keys(err.errors)) {
          if (v.same_as_billing && k.startsWith('shipping_')) err.errors[k.replace('shipping_', 'billing_')] ??= err.errors[k]!;
        }
      }
      setFormError(applyServerErrors(err, setError as never, Object.keys(schema.shape)));
    }
  };

  const e = formState.errors;
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={customer ? `Edit ${customer.name}` : 'New customer'}
      description={customer ? customer.code : 'A code is generated automatically if left blank.'}
      size="lg"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="customer-form" loading={formState.isSubmitting}>{customer ? 'Save changes' : 'Create customer'}</Button>
        </>
      }
    >
      <form id="customer-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormSection title="Details">
          <FormGrid columns={2}>
            <FormField label="Name" required error={e.name?.message}><Input {...register('name')} autoFocus /></FormField>
            <FormField label="Code" hint="Optional — e.g. CUST00012" error={e.code?.message}><Input {...register('code')} maxLength={20} /></FormField>
            <FormField label="Type" error={e.type?.message}><Select {...register('type')} options={enumOptions(CUSTOMER_TYPES)} /></FormField>
            <FormField label="Tax number" error={e.tax_number?.message}><Input {...register('tax_number')} /></FormField>
            <FormField label="Email" error={e.email?.message}><Input type="email" {...register('email')} /></FormField>
            <FormField label="Phone" error={e.phone?.message}><Input type="tel" {...register('phone')} /></FormField>
            <FormField label="Website" span="full" error={e.website?.message}><Input type="url" {...register('website')} placeholder="https://" /></FormField>
          </FormGrid>
        </FormSection>

        <FormSection title="Commercial terms">
          <FormGrid columns={2}>
            <FormField label="Currency" required error={e.currency?.message}><Input {...register('currency')} maxLength={3} style={{ textTransform: 'uppercase' }} /></FormField>
            <FormField label="Payment terms (days)" required error={e.payment_terms_days?.message}><Input inputMode="numeric" {...register('payment_terms_days')} /></FormField>
            <FormField label="Credit limit" hint="Leave empty for no limit" error={e.credit_limit?.message}>
              <Controller control={control} name="credit_limit" render={({ field }) => (
                <CurrencyInput currency={/^[A-Za-z]{3}$/.test(currency) ? currency.toUpperCase() : companyCurrency} value={field.value} onValueChange={field.onChange} onBlur={field.onBlur} />
              )} />
            </FormField>
            <FormField label="Sales rep" error={e.sales_rep_id?.message} hint={reps.limited ? 'Only you are listed (no access to the user directory).' : undefined}>
              <Select {...register('sales_rep_id')} placeholder="Unassigned" options={reps.options} />
            </FormField>
            <FormField label="Source" error={e.source?.message}><Input {...register('source')} maxLength={40} placeholder="e.g. referral" /></FormField>
            <FormField label="Tags" hint="Comma-separated" error={e.tags?.message}><Input {...register('tags')} placeholder="vip, wholesale" /></FormField>
          </FormGrid>
          <Controller control={control} name="is_active" render={({ field }) => (
            <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" description="Inactive customers are hidden from document pickers." />
          )} />
        </FormSection>

        <FormSection title="Billing address">
          <FormGrid columns={2}>
            <FormField label="Address line 1" span="full" error={e.billing_address_line1?.message}><Input {...register('billing_address_line1')} /></FormField>
            <FormField label="Address line 2" span="full" error={e.billing_address_line2?.message}><Input {...register('billing_address_line2')} /></FormField>
            <FormField label="City" error={e.billing_city?.message}><Input {...register('billing_city')} /></FormField>
            <FormField label="State / region" error={e.billing_state?.message}><Input {...register('billing_state')} /></FormField>
            <FormField label="Postal code" error={e.billing_postal_code?.message}><Input {...register('billing_postal_code')} /></FormField>
            <FormField label="Country" hint="ISO code, e.g. PK, US" error={e.billing_country?.message}><Input maxLength={2} {...register('billing_country')} /></FormField>
          </FormGrid>
        </FormSection>

        <FormSection title="Shipping address">
          <Checkbox {...register('same_as_billing')} label="Same as billing address" />
          {!same && (
            <FormGrid columns={2}>
              <FormField label="Address line 1" span="full" error={e.shipping_address_line1?.message}><Input {...register('shipping_address_line1')} /></FormField>
              <FormField label="Address line 2" span="full" error={e.shipping_address_line2?.message}><Input {...register('shipping_address_line2')} /></FormField>
              <FormField label="City" error={e.shipping_city?.message}><Input {...register('shipping_city')} /></FormField>
              <FormField label="State / region" error={e.shipping_state?.message}><Input {...register('shipping_state')} /></FormField>
              <FormField label="Postal code" error={e.shipping_postal_code?.message}><Input {...register('shipping_postal_code')} /></FormField>
              <FormField label="Country" error={e.shipping_country?.message}><Input maxLength={2} {...register('shipping_country')} /></FormField>
            </FormGrid>
          )}
        </FormSection>

        <FormSection title="Location" description="Saved coordinates place the customer on the map. This is the address location, not live tracking.">
          <FormGrid columns={2}>
            <FormField label="Latitude" error={e.latitude?.message}><Input inputMode="decimal" {...register('latitude')} /></FormField>
            <FormField label="Longitude" error={e.longitude?.message}><Input inputMode="decimal" {...register('longitude')} /></FormField>
          </FormGrid>
        </FormSection>

        <FormSection title="Notes">
          <FormField label="Internal notes" hideLabel error={e.notes?.message}><Textarea rows={4} {...register('notes')} /></FormField>
        </FormSection>
      </form>
    </Drawer>
  );
}
