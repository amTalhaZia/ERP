'use client';

import { useEffect, useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Alert, Button, Drawer, FormField, FormGrid, FormSection, Input, Switch } from '@/components/ui';
import { applyServerErrors } from '@/lib/crud/resource';
import type { ApiError } from '@/lib/api/errors';
import { branches, type Branch } from './api';

const optional = z.string().trim().max(200).optional().or(z.literal(''));
const coord = (min: number, max: number) =>
  z
    .string()
    .trim()
    .optional()
    .refine((v) => !v || (!Number.isNaN(Number(v)) && Number(v) >= min && Number(v) <= max), `Must be between ${min} and ${max}`);

const schema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(150),
  code: z.string().trim().min(1, 'Code is required').max(20).regex(/^[A-Za-z0-9_-]+$/, 'Letters, numbers, - and _ only'),
  is_head_office: z.boolean(),
  is_active: z.boolean(),
  email: z.email('Enter a valid email').optional().or(z.literal('')),
  phone: optional,
  address_line1: optional,
  city: optional,
  state: optional,
  postal_code: optional,
  country: z.string().trim().length(2, 'Use a 2-letter country code').optional().or(z.literal('')),
  latitude: coord(-90, 90),
  longitude: coord(-180, 180),
});
type Values = z.infer<typeof schema>;

const toValues = (b: Branch | null): Values => ({
  name: b?.name ?? '',
  code: b?.code ?? '',
  is_head_office: b?.is_head_office ?? false,
  is_active: b?.is_active ?? true,
  email: b?.email ?? '',
  phone: b?.phone ?? '',
  address_line1: b?.address.line1 ?? '',
  city: b?.address.city ?? '',
  state: b?.address.state ?? '',
  postal_code: b?.address.postal_code ?? '',
  country: b?.address.country ?? '',
  latitude: b?.latitude ?? '',
  longitude: b?.longitude ?? '',
});

export function BranchForm({ open, branch, onClose }: { open: boolean; branch: Branch | null; onClose: () => void }) {
  const [formError, setFormError] = useState<string | null>(null);
  const form = useForm<Values>({ resolver: zodResolver(schema), defaultValues: toValues(branch) });
  const { register, handleSubmit, reset, setError, control, formState } = form;
  const create = branches.useCreate({ onSuccess: onClose });
  const update = branches.useUpdate({ onSuccess: onClose });

  useEffect(() => {
    if (open) {
      reset(toValues(branch));
    }
  }, [open, branch, reset]);

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const body = {
      ...v,
      email: v.email || null,
      country: v.country ? v.country.toUpperCase() : null,
      latitude: v.latitude ? Number(v.latitude) : null,
      longitude: v.longitude ? Number(v.longitude) : null,
    };
    try {
      if (branch) await update.mutateAsync({ id: branch.id, body });
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
      title={branch ? `Edit ${branch.name}` : 'New branch'}
      size="md"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="branch-form" loading={formState.isSubmitting}>
            {branch ? 'Save changes' : 'Create branch'}
          </Button>
        </>
      }
    >
      <form id="branch-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormSection title="Details">
          <FormGrid columns={2}>
            <FormField label="Name" required error={e.name?.message}>
              <Input {...register('name')} autoFocus />
            </FormField>
            <FormField label="Code" required error={e.code?.message} hint="Unique within the company">
              <Input {...register('code')} />
            </FormField>
            <FormField label="Email" error={e.email?.message}>
              <Input type="email" {...register('email')} />
            </FormField>
            <FormField label="Phone" error={e.phone?.message}>
              <Input type="tel" {...register('phone')} />
            </FormField>
          </FormGrid>
          <FormGrid columns={2}>
            <Controller control={control} name="is_head_office" render={({ field }) => (
              <Switch checked={field.value} onCheckedChange={field.onChange} label="Head office" description="Only one branch can be the head office." />
            )} />
            <Controller control={control} name="is_active" render={({ field }) => (
              <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" />
            )} />
          </FormGrid>
        </FormSection>
        <FormSection title="Address & location">
          <FormGrid columns={2}>
            <FormField label="Address" span="full" error={e.address_line1?.message}>
              <Input {...register('address_line1')} />
            </FormField>
            <FormField label="City" error={e.city?.message}><Input {...register('city')} /></FormField>
            <FormField label="State / region" error={e.state?.message}><Input {...register('state')} /></FormField>
            <FormField label="Postal code" error={e.postal_code?.message}><Input {...register('postal_code')} /></FormField>
            <FormField label="Country" hint="ISO code, e.g. PK, US" error={e.country?.message}><Input maxLength={2} {...register('country')} /></FormField>
            <FormField label="Latitude" hint="Shown on the locations map" error={e.latitude?.message}><Input inputMode="decimal" {...register('latitude')} /></FormField>
            <FormField label="Longitude" error={e.longitude?.message}><Input inputMode="decimal" {...register('longitude')} /></FormField>
          </FormGrid>
        </FormSection>
      </form>
    </Drawer>
  );
}
